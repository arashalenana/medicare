<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
require_once dirname(__FILE__) . '/config/db.php';

$action       = $_GET['action'] ?? $_POST['action'] ?? '';
$current_user = (int)$_SESSION['user_id'];

switch ($action) {

    case 'get_users':
        try {
            $stmt = $conn->prepare(
                "SELECT u.id,
                        u.username,
                        COALESCE(u.full_name, u.username) as full_name,
                        u.role,
                        (SELECT COUNT(*) FROM messages
                         WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                 FROM users u
                 WHERE u.id != ?
                 ORDER BY u.role ASC, u.username ASC"
            );
            $stmt->execute([$current_user, $current_user]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'users' => $users]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'get_messages':
        $with_user = (int)($_GET['with'] ?? 0);
        if (!$with_user) {
            echo json_encode(['status' => 'error', 'message' => 'No user specified']);
            exit();
        }
        try {
            $stmt = $conn->prepare(
                "UPDATE messages SET is_read = 1
                 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0"
            );
            $stmt->execute([$with_user, $current_user]);

            $stmt = $conn->prepare(
                "SELECT m.*,
                        COALESCE(u.full_name, u.username) as sender_name
                 FROM messages m
                 JOIN users u ON m.sender_id = u.id
                 WHERE (m.sender_id = ? AND m.receiver_id = ?)
                    OR (m.sender_id = ? AND m.receiver_id = ?)
                 ORDER BY m.created_at ASC
                 LIMIT 100"
            );
            $stmt->execute([$current_user, $with_user, $with_user, $current_user]);
            $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'messages' => $msgs, 'current_user' => $current_user]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'send':
        $receiver_id = (int)($_POST['receiver_id'] ?? 0);
        $message     = trim($_POST['message'] ?? '');
        if (!$receiver_id || $message === '') {
            echo json_encode(['status' => 'error', 'message' => 'Missing receiver or message']);
            exit();
        }
        try {
            $stmt = $conn->prepare(
                "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)"
            );
            $stmt->execute([$current_user, $receiver_id, $message]);
            echo json_encode(['status' => 'success', 'id' => $conn->lastInsertId()]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'unread_count':
        try {
            $stmt = $conn->prepare(
                "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0"
            );
            $stmt->execute([$current_user]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'success', 'count' => (int)$row['total']]);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'count' => 0]);
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}