<?php
// Chatbot API
 
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Load paths config and database connection
if (!defined('PHARMALYNX_PATHS_LOADED')) {
    require_once dirname(__FILE__) . '/../config/paths.php';
}
require_once dirname(__FILE__) . '/../config/db.php';
require_once dirname(__FILE__) . '/../config/rbac.php';

// Chatbot is restricted to Admins only
if (!hasPermission('chatbot')) {
    echo json_encode(['status' => 'error', 'message' => 'Access Denied: Chatbot is restricted to Admins only.']);
    exit();
}

header('Content-Type: application/json');

// Set timezone to EAT (Africa/Nairobi)
date_default_timezone_set('Africa/Nairobi');

// Image Generation (Pollinations.ai) 
function generateMedicineImage($medicineName) {
    $prompt = "A pharmaceutical product photo of " . $medicineName
            . " medicine.";

    // URL-encode the prompt for the API
    $encoded_prompt = urlencode($prompt);

    // Pollinations.ai free image generation endpoint
    $image_url = "https://image.pollinations.ai/prompt/" . $encoded_prompt . "?width=1024&height=1024&nologo=true";

    // Verify the URL is valid by checking if it's properly formatted
    if (!empty($image_url) && strlen($image_url) > 10) {
        return ['success' => true, 'url' => $image_url];
    }

    return ['success' => false, 'error' => 'Failed to generate image URL'];
}

// External Medicine Info Function
function getExternalMedicineInfo($medicineName) {
    $info = [
        'name'        => $medicineName,
        'details'     => [],
        'is_external' => true
    ];

    $fda_url = "https://api.fda.gov/drug/drugsfda.json?search=openfda.brand_name:"
             . urlencode($medicineName) . "&limit=1";

    $context = stream_context_create([
        'http' => ['timeout' => 5, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $response = @file_get_contents($fda_url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['results'])) {
            $drug = $data['results'][0];
            $info['details'] = [
                'brand'        => $drug['proprietary_name'] ?? $medicineName,
                'generic_name' => $drug['nonproprietary_name'] ?? 'Information not available',
                'manufacturer' => $drug['sponsor_name'] ?? 'Information not available',
                'approvals'    => count($drug['applications'] ?? []),
                'route'        => $drug['applications'][0]['products'][0]['dosage_form'] ?? 'Not specified'
            ];
            return $info;
        }
    }

    $info['details'] = [
        'brand'        => $medicineName,
        'note'         => 'External medicine information (not in system)',
        'source'       => 'Online Pharmaceutical Database',
        'availability' => 'Check with healthcare provider or pharmacist'
    ];
    return $info;
}

// Initialize Response
$response      = "";
$status        = "success";
$response_type = "text";
$image_url     = null;

try {
    $input   = json_decode(file_get_contents('php://input'), true);
    $message = strtolower(trim($input['message'] ?? ''));

    if (empty($message)) {
        throw new Exception('Empty message');
    }

    // Image Generation Command
    if (preg_match('/\b(show image|generate image|image of|picture of|photo of|show me|visualize)\b.*?(medicine|drug|tablet|capsule|pill|syrup|injection)?/i', $message)
        || preg_match('/\b(image|picture|photo|show)\b/i', $message)) {

        // Extract medicine name from the request
        $medicine_name = preg_replace(
            '/\b(show|generate|image|picture|photo|of|me|the|a|an|please|can you|visualize|for)\b/i',
            '',
            $message
        );
        $medicine_name = trim(preg_replace('/\s+/', ' ', $medicine_name));

        if (strlen($medicine_name) > 2) {
            // Check if medicine exists in system first
            $stmt = $conn->prepare("SELECT name FROM medicines WHERE name LIKE ? LIMIT 1");
            $stmt->execute(["%$medicine_name%"]);
            $found = $stmt->fetch();
            $display_name = $found ? $found['name'] : ucwords($medicine_name);

            $img_result = generateMedicineImage($display_name);

            if ($img_result['success']) {
                $response_type = "image";
                $image_url     = $img_result['url'];
                $response      = "&#128247; Here is an AI-generated image of <strong>" . htmlspecialchars($display_name) . "</strong>:";
            } else {
                $response = "&#128247; Sorry, I couldn't generate an image for <strong>" . htmlspecialchars($display_name) . "</strong>.\n";
                $response .= "Reason: " . htmlspecialchars($img_result['error']) . "\n\n";
                $response .= "&#128161; Please try again or ask about a different medicine.";
            }
        } else {
            $response = "&#128247; Please specify a medicine name to generate an image.\n";
            $response .= "Example: 'Show image of Panadol' or 'Generate image of Amoxicillin'"; 
        }
    }

    // Time-Aware Greeting and Introduction
    elseif (preg_match('/\b(hello|hi|hey|greetings|good morning|good afternoon|good evening|morning|afternoon|evening|good day|welcome|start|begin|intro|introduction|what can you do|help me|assist|support)\b/i', $message)) {
        $current_hour = (int)date('H');
        $time_greeting = "";
        
        if ($current_hour >= 5 && $current_hour < 12) {
            $time_greeting = "Good Morning! &#9728;&#65039;";
        } elseif ($current_hour >= 12 && $current_hour < 18) {
            $time_greeting = "Good Afternoon! &#127764;&#65039;";
        } else {
            $time_greeting = "Good Evening! &#127769;";
        }

        // Determine if the user used a specific time greeting
        if (strpos($message, 'morning') !== false) {
            $response = "&#128075; {$time_greeting} ";
        } elseif (strpos($message, 'afternoon') !== false) {
            $response = "&#128075; {$time_greeting} ";
        } elseif (strpos($message, 'evening') !== false) {
            $response = "&#128075; {$time_greeting} ";
        } else {
            // Generic greeting without specific time mentioned
            $response = "&#128075; {$time_greeting} ";
        }

        $response .= "Welcome to Family Medicare AI Assistant.\n\n";
        $response .= "I can help you with:\n";
        $response .= "&#8226; Checking low stock items\n";
        $response .= "&#8226; View expiring medicines\n";
        $response .= "&#8226; Searching for specific medicines\n";
        $response .= "&#8226; Viewing today's sales summary\n";
        $response .= "&#8226; See top selling medicines\n";
        $response .= "&#8226; Get reorder suggestions\n";
        $response .= "&#8226; Generate medicine images (e.g. 'show image of Amoxicillin')\n\n";
        $response .= "Type 'help' for detailed commands or ask me anything!";
    }

    // Low Stock Alert
    elseif (strpos($message, 'low stock') !== false || strpos($message, 'below threshold') !== false) {
        $stmt  = $conn->query("SELECT name, quantity FROM medicines WHERE quantity < 10");
        $items = $stmt->fetchAll();
        if ($items) {
            $response = "&#128308; Low Stock Medicines:\n";
            foreach ($items as $index => $item) {
                $response .= ($index + 1) . ". " . $item['name'] . " - " . $item['quantity'] . " units\n";
            }
            $response .= "\n&#128161; Related Suggestions:\n";
            $response .= "&#8226; Type 'reorder suggestions' to get items that need restocking\n";
            $response .= "&#8226; Type 'expiring medicines' to check for items nearing expiry";
        } else {
            $response = "&#9989; All medicines are above the low stock threshold.";
        }
    }

    // Expiring Medicines Alert
    elseif (strpos($message, 'expiring') !== false || strpos($message, 'expiry') !== false) {
        $stmt  = $conn->query("SELECT name, expiry_date FROM medicines WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
        $items = $stmt->fetchAll();
        if ($items) {
            $response = "&#128992; Medicines Expiring Soon (30 Days):\n";
            foreach ($items as $index => $item) {
                $days      = (strtotime($item['expiry_date']) - strtotime(date('Y-m-d'))) / 86400;
                $response .= ($index + 1) . ". " . $item['name'] . " - expires in " . round($days) . " days (" . $item['expiry_date'] . ")\n";
            }
            $response .= "\n&#128161; Related Suggestions:\n";
            $response .= "&#8226; Type 'low stock' to check items below threshold\n";
            $response .= "&#8226; Type 'reorder suggestions' for items that need restocking";
        } else {
            $response = "&#9989; No medicines are expiring within the next 30 days.";
        }
    }

    // Medicine Search Command
    elseif (strpos($message, 'search') !== false || strpos($message, 'find') !== false || strpos($message, 'check stock') !== false) {
        $search_term = trim(str_replace(['search', 'find', 'check stock for'], '', $message));
        $stmt        = $conn->prepare("SELECT * FROM medicines WHERE name LIKE ?");
        $stmt->execute(["%$search_term%"]);
        $items = $stmt->fetchAll();
        if ($items) {
            foreach ($items as $item) {
                $response .= "&#128138; " . $item['name'] . "\n";
                $response .= "Stock: " . $item['quantity'] . " units\n";
                $response .= "Price: KSh " . number_format($item['selling_price'], 2) . "\n";
                $response .= "Expiry: " . $item['expiry_date'] . "\n\n";
            }
            $response .= "\n&#128161; Related Suggestions:\n";
            $response .= "&#8226; Type 'top medicines' to see best sellers\n";
            $response .= "&#8226; Type 'low stock' for items that need reordering";
        } else {
            $response  = "&#128269; No matching medicines found for '$search_term'.\n\n";
            $response .= "&#128161; Related Suggestions:\n";
            $response .= "&#8226; Type 'top medicines' to see best sellers\n";
            $response .= "&#8226; Type 'low stock' to check what needs restocking";
        }
    }

    // Sales Summary Command
    elseif (strpos($message, 'sales') !== false || strpos($message, 'revenue') !== false) {
        $stmt         = $conn->query("SELECT SUM(total_amount) as revenue, COUNT(*) as transactions FROM sales WHERE DATE(created_at) = CURDATE()");
        $res          = $stmt->fetch();
        $revenue      = $res['revenue'] ?? 0;
        $transactions = $res['transactions'] ?? 0;
        $response     = "&#128202; Today's Sales Summary:\n";
        $response    .= "Revenue: KSh " . number_format($revenue, 2) . "\n";
        $response    .= "Transactions: " . $transactions . "\n\n";
        $response    .= "&#128161; Related Suggestions:\n";
        $response    .= "&#8226; Type 'top medicines' to see best sellers\n";
        $response    .= "&#8226; Type 'low stock' to check inventory levels";
    }

    // Top Selling Medicines Command
    elseif (strpos($message, 'top') !== false || strpos($message, 'best selling') !== false) {
        $stmt  = $conn->query("SELECT m.name, SUM(si.quantity) as total_sold
                               FROM sale_items si
                               JOIN medicines m ON si.medicine_id = m.id
                               GROUP BY si.medicine_id
                               ORDER BY total_sold DESC LIMIT 5");
        $items = $stmt->fetchAll();
        if ($items) {
            $response = "&#127942; Top Selling Medicines:\n";
            foreach ($items as $index => $item) {
                $response .= ($index + 1) . ". " . $item['name'] . " (" . $item['total_sold'] . " sold)\n";
            }
            $response .= "\n&#128161; Related Suggestions:\n";
            $response .= "&#8226; Type 'today sales' to view revenue\n";
            $response .= "&#8226; Type 'low stock' to ensure we have enough inventory";
        } else {
            $response = "No sales data available yet.";
        }
    }

    // Reorder Suggestions Command
    elseif (strpos($message, 'reorder') !== false || strpos($message, 'restock') !== false) {
        $stmt  = $conn->query("SELECT name, quantity FROM medicines WHERE quantity < 10 ORDER BY quantity ASC");
        $items = $stmt->fetchAll();
        if ($items) {
            $response = "&#128230; Reorder Suggestions:\n";
            foreach ($items as $index => $item) {
                $response .= ($index + 1) . ". " . $item['name'] . " (Current Stock: " . $item['quantity'] . ")\n";
            }
            $response .= "\n&#128161; Related Suggestions:\n";
            $response .= "&#8226; Type 'expiring medicines' to check for near-expiry items\n";
            $response .= "&#8226; Type 'today sales' to see how much we're selling";
        } else {
            $response = "&#9989; Inventory levels are healthy. No reorders needed.";
        }
    }

    // Help Command
    elseif ($message == 'help') {
        $response  = "&#129302; FAMILY MEDICARE AI ASSISTANT - FULL COMMAND LIST\n";
        $response .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

        $response .= "&#128075; GREETING COMMANDS:\n";
        $response .= "  &#8226; Hello, Hi, Hey, Good morning/afternoon/evening\n\n";

        $response .= "&#128138; MEDICINE QUERIES:\n";
        $response .= "  &#8226; Type any medicine name (e.g., 'Aspirin', 'Amoxicillin')\n";
        $response .= "  &#8226; 'Show all medicines' - View system inventory\n";
        $response .= "  &#8226; 'What medicines are available?' - In-stock items\n";
        $response .= "  &#8226; 'Search [medicine]' - Find specific medicine\n\n";

        $response .= "&#128247; IMAGE GENERATION:\n";
        $response .= "  &#8226; 'Show image of Panadol' - Generate medicine image\n";
        $response .= "  &#8226; 'Generate image of Amoxicillin' - AI medicine photo\n";
        $response .= "  &#8226; 'Picture of [medicine name]' - Visual reference\n\n";

        $response .= "&#128230; INVENTORY MANAGEMENT:\n";
        $response .= "  &#8226; 'Low stock' - Medicines below threshold\n";
        $response .= "  &#8226; 'Expiring medicines' - Items with expiry alerts\n";
        $response .= "  &#8226; 'Reorder suggestions' - Items needing restock\n\n";

        $response .= "&#128202; SALES & ANALYTICS:\n";
        $response .= "  &#8226; 'Today sales' - Daily sales summary\n";
        $response .= "  &#8226; 'Top medicines' - Best-selling items\n\n";

        $response .= "&#127919; SMART FEATURES:\n";
        $response .= "  &#8226; Fuzzy matching - finds medicines with partial names\n";
        $response .= "  &#8226; Time-based greetings (EAT timezone)\n";
        $response .= "  &#8226; Real-time inventory tracking\n";
        $response .= "  &#8226; External medicine database lookup (OpenFDA)\n";
        $response .= "  &#8226; AI medicine image generation (OpenAI DALL-E)";
    }

    // Generic Medicine Query
    else {
        $stmt          = $conn->query("SELECT * FROM medicines ORDER BY name ASC");
        $all_medicines = $stmt->fetchAll();

        $medicine_found = false;
        $found_medicine = null;

        // Try exact match first
        foreach ($all_medicines as $med) {
            if (stripos($message, $med['name']) !== false) {
                $found_medicine = $med;
                $medicine_found = true;
                break;
            }
        }

        // Fuzzy match fallback
        if (!$medicine_found && count($all_medicines) > 0) {
            $best_match      = null;
            $best_similarity = 0;
            foreach ($all_medicines as $med) {
                similar_text(strtolower($message), strtolower($med['name']), $percent);
                if ($percent > $best_similarity) {
                    $best_similarity = $percent;
                    $best_match      = $med;
                }
            }
            if ($best_match && $best_similarity >= 60) {
                $found_medicine = $best_match;
                $medicine_found = true;
            }
        }

        // Medicine found in system
        if ($medicine_found && $found_medicine) {
            $med = $found_medicine;

            $today             = time();
            $expiry_time       = strtotime($med['expiry_date']);
            $days_until_expiry = ($expiry_time - $today) / 86400;

            $is_available        = $med['quantity'] > 0;
            $availability_status = $is_available ? "&#9989; IN STOCK" : "&#10060; OUT OF STOCK";

            if ($med['quantity'] == 0) {
                $stock_level = "&#128308; OUT OF STOCK - URGENT REORDER NEEDED";
            } elseif ($med['quantity'] < 5) {
                $stock_level = "&#128308; CRITICAL STOCK - IMMEDIATE REORDER REQUIRED";
            } elseif ($med['quantity'] < 10) {
                $stock_level = "&#128992; LOW STOCK - REORDER RECOMMENDED";
            } elseif ($med['quantity'] < 30) {
                $stock_level = "&#128993; MODERATE STOCK";
            } else {
                $stock_level = "&#9989; HEALTHY STOCK LEVEL";
            }

            if ($days_until_expiry < 0) {
                $expiry_status = "&#9888;&#65039; EXPIRED - REMOVE FROM INVENTORY";
            } elseif ($days_until_expiry <= 7) {
                $expiry_status = "&#128308; EXPIRING VERY SOON (" . round($days_until_expiry) . " days)";
            } elseif ($days_until_expiry <= 30) {
                $expiry_status = "&#128992; EXPIRING SOON (" . round($days_until_expiry) . " days)";
            } else {
                $expiry_status = "&#9989; FRESH - " . round($days_until_expiry) . " days remaining";
            }

            if ($med['quantity'] <= 5) {
                $reorder_status = "&#128680; YES - IMMEDIATELY";
            } elseif ($med['quantity'] <= 10) {
                $reorder_status = "&#9888;&#65039; YES - SOON";
            } else {
                $reorder_status = "&#9989; NO - Current stock is adequate";
            }

            $response  = "&#128138; MEDICINE DETAILS: " . strtoupper($med['name']) . "\n";
            $response .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

            $response .= "&#128230; AVAILABILITY &amp; STOCK:\n";
            $response .= "  &#8226; Availability: " . $availability_status . "\n";
            $response .= "  &#8226; Current Stock: " . $med['quantity'] . " units\n";
            $response .= "  &#8226; Stock Status: " . $stock_level . "\n\n";

            $response .= "&#128176; PRICING:\n";
            $response .= "  &#8226; Selling Price: KSh " . number_format($med['selling_price'], 2) . " per unit\n";
            $response .= "  &#8226; Cost Price: KSh " . number_format($med['buying_price'], 2) . " per unit\n";
            $response .= "  &#8226; Profit Margin: " . round((($med['selling_price'] - $med['buying_price']) / $med['selling_price']) * 100) . "%\n\n";

            $response .= "&#128197; EXPIRY INFORMATION:\n";
            $response .= "  &#8226; Expiry Date: " . $med['expiry_date'] . "\n";
            $response .= "  &#8226; Status: " . $expiry_status . "\n\n";

            $response .= "&#128260; REORDER NEEDED?\n";
            $response .= "  &#8226; Decision: " . $reorder_status . "\n";
            if ($med['quantity'] <= 10) {
                $suggested_reorder = max(50, 100 - $med['quantity']);
                $response .= "  &#8226; Suggested Quantity: " . $suggested_reorder . " units\n";
            }
            $response .= "\n";
            $response .= "&#128247; Type 'show image of " . $med['name'] . "' to see a visual reference\n";
            $response .= "&#128161; Quick Actions:\n";
            $response .= "&#8226; Type 'search all' to see other medicines\n";
            $response .= "&#8226; Type 'low stock' to check other items\n";
            $response .= "&#8226; Type 'expiring medicines' for expiry alerts";

        } else {
            // Check if user is asking about medicines in general
            if (preg_match('/\b(medicine|medicines|drug|drugs|product|products|stock|inventory|available|price|cost)\b/', $message)) {

                if (preg_match('/\b(all|list|show|view|search all)\b/', $message)) {
                    $response  = "&#128203; ALL MEDICINES IN SYSTEM:\n";
                    $response .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

                    $count       = 0;
                    $total_items = count($all_medicines);

                    foreach ($all_medicines as $med) {
                        $count++;
                        $status_tag = "";
                        if ($med['quantity'] == 0) {
                            $status_tag = " [OUT OF STOCK]";
                        } elseif ($med['quantity'] < 10) {
                            $status_tag = " [LOW STOCK]";
                        }
                        $response .= $count . ". " . $med['name'] . "\n";
                        $response .= "   Stock: " . $med['quantity'] . " units | Price: KSh " . number_format($med['selling_price'], 2) . $status_tag . "\n\n";
                        if ($count >= 20) {
                            $response .= "... and " . ($total_items - $count) . " more medicines\n";
                            $response .= "Total medicines in system: " . $total_items . "\n\n";
                            break;
                        }
                    }
                    $response .= "&#128161; To see details, type the medicine name!\n";
                    $response .= "&#8226; Type 'low stock' to focus on items needing reorder\n";
                    $response .= "&#8226; Type 'expiring medicines' to check expiry dates";

                } elseif (preg_match('/\b(available|stock|in stock|out of stock|out)\b/', $message)) {
                    if (preg_match('/\b(available|in stock)\b/', $message)) {
                        $response  = "&#9989; AVAILABLE MEDICINES (IN STOCK):\n";
                        $response .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $count = 0;
                        foreach ($all_medicines as $med) {
                            if ($med['quantity'] > 0) {
                                $count++;
                                $response .= $count . ". " . $med['name'] . " - " . $med['quantity'] . " units (KSh " . number_format($med['selling_price'], 2) . ")\n";
                                if ($count >= 15) break;
                            }
                        }
                        $response .= "\n&#128161; Ask about any medicine by name for full details!";
                    } else {
                        $response  = "&#10060; OUT OF STOCK MEDICINES:\n";
                        $response .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        $out_of_stock = [];
                        foreach ($all_medicines as $med) {
                            if ($med['quantity'] == 0) {
                                $out_of_stock[] = $med['name'];
                            }
                        }
                        if (count($out_of_stock) > 0) {
                            foreach ($out_of_stock as $index => $name) {
                                $response .= ($index + 1) . ". " . $name . "\n";
                            }
                            $response .= "\n&#128680; Total out of stock: " . count($out_of_stock) . " medicines";
                        } else {
                            $response = "&#9989; All medicines are currently in stock!";
                        }
                    }
                } else {
                    $response  = "&#128269; ABOUT MEDICINES - HOW CAN I HELP?\n\n";
                    $response .= "I can provide information about:\n";
                    $response .= "&#10003; Medicine Details (price, stock, expiry date)\n";
                    $response .= "&#10003; Availability Status (in stock/out of stock)\n";
                    $response .= "&#10003; Stock Levels &amp; Reorder Needs\n";
                    $response .= "&#10003; Expiry Dates &amp; Alerts\n";
                    $response .= "&#10003; Complete Medicine List\n";
                    $response .= "&#10003; AI Medicine Images (e.g. 'show image of Amoxicillin')\n\n";
                    $response .= "&#128161; JUST ASK:\n";
                    $response .= "&#8226; Type a medicine name (e.g., 'Aspirin')\n";
                    $response .= "&#8226; 'Show all medicines' - Complete inventory\n";
                    $response .= "&#8226; 'Low stock' - Items needing reorder\n";
                    $response .= "&#8226; 'Expiring medicines' - Expiry alerts";
                }

            } else {
                // External medicine lookup
                $potential_medicine = preg_replace('/\b(do|you|have|what|is|the|a|is|tell|me|about|how|much|information|on|for)\b/i', '', $message);
                $potential_medicine = trim($potential_medicine);

                if (strlen($potential_medicine) > 2) {
                    $external_med = getExternalMedicineInfo($potential_medicine);

                    $response  = "&#128138; EXTERNAL MEDICINE DETAILS: " . strtoupper($external_med['name']) . "\n";
                    $response .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                    $response .= "&#9888;&#65039; NOT IN SYSTEM - External Database Info\n\n";

                    if (!empty($external_med['details'])) {
                        foreach ($external_med['details'] as $key => $value) {
                            $display_key = ucwords(str_replace('_', ' ', $key));
                            $response   .= "&#8226; " . $display_key . ": " . $value . "\n";
                        }
                    }

                    $response .= "\n&#128221; NOTES:\n";
                    $response .= "&#8226; This medication is not in your system inventory\n";
                    $response .= "&#8226; Please consult your supplier for availability\n";
                    $response .= "&#128247; Type 'show image of " . ucwords($potential_medicine) . "' to see a visual reference\n";
                    $response .= "&#128161; Quick Links:\n";
                    $response .= "&#8226; Type 'show all medicines' to see system inventory\n";
                    $response .= "&#8226; Type 'help' for full command list";

                } else {
                    $response  = "&#129300; COMMAND NOT RECOGNIZED\n\n";
                    $response .= "&#128161; I'M HERE TO HELP WITH MEDICINES! Try:\n";
                    $response .= "&#8226; 'Hello' - Get started\n";
                    $response .= "&#8226; Medicine name (e.g., 'Panadol', 'Amoxicillin')\n";
                    $response .= "&#8226; 'Show all medicines' - View all inventory\n";
                    $response .= "&#8226; 'Low stock' - Items needing reorder\n";
                    $response .= "&#8226; 'Expiring medicines' - Expiry alerts\n";
                    $response .= "&#8226; 'Today sales' - Sales report\n";
                    $response .= "&#8226; 'Show image of [medicine]' - Generate medicine image\n";
                    $response .= "&#8226; 'Help' - Full command list";
                }
            }
        }
    }

} catch (Exception $e) {
    $status   = "error";
    $response = "&#10060; Error: " . $e->getMessage();
} catch (Throwable $t) {
    $status   = "error";
    $response = "&#10060; Server error occurred. Please try again.";
}

$output = [
    'status'   => $status,
    'response' => nl2br($response),
    'type'     => $response_type,
];

if ($image_url) {
    $output['image_url'] = $image_url;
}

echo json_encode($output);