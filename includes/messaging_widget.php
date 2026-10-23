<?php
// Messaging Widget — included in footer.php for all logged-in users
if (!isset($_SESSION['user_id'])) return;

// Build absolute API URL robustly — works on localhost, InfinityFree, and Hostinger
$_protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
              ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ||
              ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https' : 'http';
$_host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_script    = $_SERVER['SCRIPT_NAME'] ?? '';

// Walk up from current script to find the project root (where messaging_api.php lives)
$_dir = dirname($_script);
// If we're inside /pages/ or /pages/api/, go up accordingly
if (strpos($_script, '/pages/api/') !== false) {
    $_root = dirname(dirname($_dir));
} elseif (strpos($_script, '/pages/') !== false) {
    $_root = dirname($_dir);
} else {
    $_root = $_dir;
}
$_root = rtrim(str_replace('\\', '/', $_root), '/');
$_msg_api_url = $_protocol . '://' . $_host . $_root . '/messaging_api.php';

$current_username = htmlspecialchars($_SESSION['username'] ?? '');
$current_user_id  = (int)($_SESSION['user_id'] ?? 0);
?>

<style>
/* ── Messaging Widget ── */
#msg-widget {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9998;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

#msg-toggle {
    width: 60px;
    height: 60px;
    background: #ED1C24;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 22px;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(237,28,36,0.4);
    border: none;
    transition: transform 0.2s;
    position: relative;
}

#msg-toggle:hover { transform: scale(1.1); }

#msg-unread-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    background: #000;
    color: #fff;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 700;
    width: 20px;
    height: 20px;
    display: none;
    align-items: center;
    justify-content: center;
    border: 2px solid #ED1C24;
}

#msg-panel {
    position: absolute;
    bottom: 75px;
    right: 0;
    width: 340px;
    height: 500px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18);
    display: none;
    flex-direction: row;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    animation: msgSlideUp 0.25s ease;
}

@keyframes msgSlideUp {
    from { transform: translateY(14px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}

/* ── Left: user list ── */
#msg-users {
    width: 110px;
    min-width: 110px;
    border-right: 1px solid #f0f0f0;
    display: flex;
    flex-direction: column;
    background: #f8f9fa;
}

#msg-users-header {
    padding: 12px 10px 8px;
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #888;
    border-bottom: 1px solid #eee;
    flex-shrink: 0;
}

#msg-users-list {
    overflow-y: auto;
    flex: 1;
}

.msg-user-item {
    padding: 10px 8px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.15s;
    position: relative;
}

.msg-user-item:hover,
.msg-user-item.active {
    background: #fff0f0;
}

.msg-user-item.active {
    border-left: 3px solid #ED1C24;
}

.msg-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    color: #fff;
    margin: 0 auto 4px;
}

.msg-user-name {
    font-size: 10px;
    font-weight: 600;
    color: #333;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.msg-user-role {
    font-size: 9px;
    color: #aaa;
    text-align: center;
}

.msg-user-unread {
    position: absolute;
    top: 6px;
    right: 6px;
    background: #ED1C24;
    color: #fff;
    border-radius: 50%;
    font-size: 9px;
    font-weight: 800;
    width: 16px;
    height: 16px;
    display: none;
    align-items: center;
    justify-content: center;
}

/* ── Right: chat area ── */
#msg-chat {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

#msg-chat-header {
    padding: 12px 14px;
    background: #000;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}

#msg-chat-header span { font-size: 10px; color: rgba(255,255,255,0.55); font-weight: 400; }

#msg-close {
    background: none;
    border: none;
    color: rgba(255,255,255,0.6);
    cursor: pointer;
    font-size: 16px;
    padding: 0;
    line-height: 1;
}
#msg-close:hover { color: #fff; }

#msg-chat-empty {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    color: #bbb;
    font-size: 12px;
    gap: 8px;
    padding: 20px;
    text-align: center;
}

#msg-chat-empty i { font-size: 30px; color: #e0e0e0; }

#msg-messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #fafafa;
}

.msg-bubble-wrap {
    display: flex;
    flex-direction: column;
}

.msg-bubble-wrap.mine  { align-items: flex-end; }
.msg-bubble-wrap.theirs { align-items: flex-start; }

.msg-bubble {
    max-width: 85%;
    padding: 8px 12px;
    border-radius: 14px;
    font-size: 12px;
    line-height: 1.45;
    word-break: break-word;
}

.msg-bubble-wrap.mine .msg-bubble {
    background: #ED1C24;
    color: #fff;
    border-bottom-right-radius: 3px;
}

.msg-bubble-wrap.theirs .msg-bubble {
    background: #e9ecef;
    color: #222;
    border-bottom-left-radius: 3px;
}

.msg-time {
    font-size: 9px;
    color: #bbb;
    margin-top: 2px;
    padding: 0 4px;
}

#msg-input-area {
    padding: 10px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 6px;
    background: #fff;
    flex-shrink: 0;
}

#msg-input {
    flex: 1;
    border: 1px solid #ddd;
    border-radius: 20px;
    padding: 7px 12px;
    font-size: 12px;
    outline: none;
    resize: none;
    font-family: 'Segoe UI', sans-serif;
    line-height: 1.4;
    max-height: 80px;
    overflow-y: auto;
}

#msg-input:focus { border-color: #ED1C24; }

#msg-send {
    width: 34px;
    height: 34px;
    background: #ED1C24;
    border: none;
    border-radius: 50%;
    color: #fff;
    font-size: 13px;
    cursor: pointer;
    flex-shrink: 0;
    align-self: flex-end;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

#msg-send:hover { background: #c1121c; }

/* role colour map */
.role-admin       { background: #ED1C24; }
.role-receptionist { background: #00AEDB; }
.role-cashier     { background: #198754; }
.role-lab         { background: #fd7e14; }
.role-guest       { background: #6c757d; }
</style>

<div id="msg-widget">
    <!-- Panel -->
    <div id="msg-panel">
        <!-- User list -->
        <div id="msg-users">
            <div id="msg-users-header">Staff</div>
            <div id="msg-users-list">
                <div style="padding:20px 10px;text-align:center;color:#bbb;font-size:11px;">Loading...</div>
            </div>
        </div>
        <!-- Chat -->
        <div id="msg-chat">
            <div id="msg-chat-header">
                <div>
                    <div id="msg-chat-name">Messages</div>
                    <span id="msg-chat-role"></span>
                </div>
                <button id="msg-close" title="Close"><i class="fas fa-times"></i></button>
            </div>
            <div id="msg-chat-empty">
                <i class="fas fa-comments"></i>
                <p>Select a staff member<br>to start chatting</p>
            </div>
            <div id="msg-messages" style="display:none;"></div>
            <div id="msg-input-area" style="display:none;">
                <textarea id="msg-input" placeholder="Type a message..." rows="1"></textarea>
                <button id="msg-send"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <!-- Toggle button -->
    <button id="msg-toggle" title="Staff Messages">
        <i class="fas fa-comments"></i>
        <span id="msg-unread-badge"></span>
    </button>
</div>

<script>
(function () {
    const API      = '<?php echo $_msg_api_url; ?>';
    const ME       = <?php echo $current_user_id; ?>;
    const ROLE_CSS = { 'Admin':'role-admin','Receptionist':'role-receptionist','Cashier':'role-cashier','Lab Tech':'role-lab','Guest':'role-guest' };

    let activeUserId   = null;
    let pollInterval   = null;
    let badgeInterval  = null;
    let isOpen         = false;

    const panel      = document.getElementById('msg-panel');
    const toggle     = document.getElementById('msg-toggle');
    const closeBtn   = document.getElementById('msg-close');
    const usersList  = document.getElementById('msg-users-list');
    const messages   = document.getElementById('msg-messages');
    const emptyState = document.getElementById('msg-chat-empty');
    const inputArea  = document.getElementById('msg-input-area');
    const input      = document.getElementById('msg-input');
    const sendBtn    = document.getElementById('msg-send');
    const badge      = document.getElementById('msg-unread-badge');
    const chatName   = document.getElementById('msg-chat-name');
    const chatRole   = document.getElementById('msg-chat-role');

    // ── Toggle panel ──
    toggle.addEventListener('click', function () {
        isOpen = !isOpen;
        panel.style.display = isOpen ? 'flex' : 'none';
        if (isOpen) { loadUsers(); startBadgePoll(); }
        else { stopPoll(); }
    });

    closeBtn.addEventListener('click', function () {
        isOpen = false;
        panel.style.display = 'none';
        stopPoll();
    });

    // ── Load user list ──
    function loadUsers() {
        fetch(API + '?action=get_users')
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') return;
                usersList.innerHTML = '';
                if (!data.users.length) {
                    usersList.innerHTML = '<div style="padding:12px;font-size:11px;color:#aaa;text-align:center;">No other users</div>';
                    return;
                }
                data.users.forEach(u => {
                    const initials = (u.full_name || u.username).split(' ').map(w => w[0]).join('').substring(0,2).toUpperCase();
                    const roleClass = ROLE_CSS[u.role] || 'role-guest';
                    const div = document.createElement('div');
                    div.className = 'msg-user-item' + (u.id == activeUserId ? ' active' : '');
                    div.dataset.id   = u.id;
                    div.dataset.name = u.full_name || u.username;
                    div.dataset.role = u.role;
                    div.innerHTML = `
                        <div class="msg-user-avatar ${roleClass}">${initials}</div>
                        <div class="msg-user-name">${escHtml(u.full_name || u.username)}</div>
                        <div class="msg-user-role">${escHtml(u.role)}</div>
                        <span class="msg-user-unread" id="unread-${u.id}" style="${u.unread_count > 0 ? 'display:flex' : 'display:none'}">${u.unread_count}</span>
                    `;
                    div.addEventListener('click', () => openChat(u.id, u.full_name || u.username, u.role));
                    usersList.appendChild(div);
                });
            })
            .catch(() => {});
    }

    // ── Open chat with a user ──
    function openChat(userId, name, role) {
        activeUserId = userId;

        // Update header
        chatName.textContent = name;
        chatRole.textContent = role;

        // Highlight selected
        document.querySelectorAll('.msg-user-item').forEach(el => {
            el.classList.toggle('active', el.dataset.id == userId);
        });

        // Show chat area
        emptyState.style.display = 'none';
        messages.style.display   = 'flex';
        inputArea.style.display  = 'flex';

        fetchMessages();
        stopPoll();
        pollInterval = setInterval(fetchMessages, 3000);
        input.focus();
    }

    // ── Fetch messages ──
    function fetchMessages() {
        if (!activeUserId) return;
        fetch(API + '?action=get_messages&with=' + activeUserId)
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'success') return;
                const wasAtBottom = messages.scrollHeight - messages.clientHeight <= messages.scrollTop + 10;
                const prevCount   = messages.querySelectorAll('.msg-bubble-wrap').length;

                messages.innerHTML = '';
                data.messages.forEach(m => {
                    const mine = m.sender_id == ME;
                    const wrap = document.createElement('div');
                    wrap.className = 'msg-bubble-wrap ' + (mine ? 'mine' : 'theirs');
                    const time = new Date(m.created_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
                    wrap.innerHTML = `<div class="msg-bubble">${escHtml(m.message)}</div><div class="msg-time">${time}</div>`;
                    messages.appendChild(wrap);
                });

                // Scroll to bottom if was at bottom or new messages arrived
                if (wasAtBottom || data.messages.length > prevCount) {
                    messages.scrollTop = messages.scrollHeight;
                }

                // Refresh unread badges on user list
                loadUsers();
                updateGlobalBadge();
            })
            .catch(() => {});
    }

    // ── Send message ──
    function sendMessage() {
        const text = input.value.trim();
        if (!text || !activeUserId) return;
        input.value = '';
        input.style.height = 'auto';

        fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=send&receiver_id=' + activeUserId + '&message=' + encodeURIComponent(text)
        })
        .then(r => r.json())
        .then(data => { if (data.status === 'success') fetchMessages(); })
        .catch(() => {});
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    // Auto-resize textarea
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 80) + 'px';
    });

    // ── Global unread badge ──
    function updateGlobalBadge() {
        fetch(API + '?action=unread_count')
            .then(r => r.json())
            .then(data => {
                if (data.count > 0) {
                    badge.textContent     = data.count > 9 ? '9+' : data.count;
                    badge.style.display   = 'flex';
                } else {
                    badge.style.display   = 'none';
                }
            })
            .catch(() => {});
    }

    function startBadgePoll()  { badgeInterval = setInterval(updateGlobalBadge, 5000); updateGlobalBadge(); }
    function stopPoll()        { clearInterval(pollInterval); pollInterval = null; }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Poll for unread badge even when widget is closed
    updateGlobalBadge();
    setInterval(updateGlobalBadge, 10000);
})();
</script>