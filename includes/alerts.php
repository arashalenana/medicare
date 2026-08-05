<?php

//   Family Medicare Alert System
//   Handles email notifications for low stock and expiring medicines.
 
function sendLowStockAlert($medicine_name, $current_stock) {
    global $base_url;
    $admin_email = "admin@family_medicare.com"; // Admin's email
    $subject = "🚨 LOW STOCK ALERT: $medicine_name";
    
    $message = "
    <html>
    <head>
        <title>Family Medicare Low Stock Alert</title>
    </head>
    <body>
        <h2 style='color: #8b0000;'>Family Medicare Inventory Alert</h2>
        <p>The following medicine has reached a low stock level:</p>
        <table border='1' cellpadding='10' style='border-collapse: collapse;'>
            <tr style='background-color: #f8f9fa;'>
                <th>Medicine Name</th>
                <th>Current Stock</th>
                <th>Threshold</th>
            </tr>
            <tr>
                <td>$medicine_name</td>
                <td style='color: #8b0000; font-weight: bold;'>$current_stock</td>
                <td>10</td>
            </tr>
        </table>
        <p><a href='<?php echo $base_url; ?>pages/medicines.php' style='background-color: #8b0000; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Update Inventory</a></p>
        <hr>
        <p style='font-size: 0.8em; color: #777;'>This is an automated alert from Family Medicare POS System.</p>
    </body>
    </html>
    ";

    // Set content-type header for sending HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: alerts@family_medicare.com" . "\r\n";

    if (!@mail($admin_email, $subject, $message, $headers)) {
        error_log("Alert: Low stock for $medicine_name ($current_stock). Email could not be sent (check SMTP settings).");
    }
}

function checkStockAndAlert($medicine_id, $conn) {
    // query the DB and call sendLowStockAlert if stock < 10
}
?>
