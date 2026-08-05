<?php
// Family Medicare AI Assistant UI Component
 
// Load centralized path configuration (safe, idempotent)
if (!defined('FAMILYMEDICARE_PATHS_LOADED')) {
    require_once dirname(__FILE__) . '/../config/paths.php';
}
?>

<!-- AI Assistant UI Component -->
<div id="chatbot-container">
    <!-- Toggle Button -->
    <button id="chatbot-toggle" type="button" aria-label="Toggle Family Medicare AI chat">
        <i class="fas fa-robot"></i>
    </button>

    <!-- Chat Panel -->
    <div id="chatbot-panel">
        <div class="chatbot-header">
            <img src="<?php echo $asset_path; ?>images/favicon.png" alt="AI Assistant">
            <h6>Family Medicare AI Assistant</h6>
            <div class="ms-auto" style="cursor:pointer" onclick="document.getElementById('chatbot-panel').style.display='none'" title="Close chat">
                <i class="fas fa-times"></i>
            </div>
        </div>

        <div class="chatbot-body" id="chatbot-body">
            <div class="message bot-message">
                Hello &#128075; I am Family Medicare AI Assistant. How can I help you today?
                <span class="message-time"><?php echo date('H:i'); ?></span>
            </div>
        </div>

        <div id="chatbot-typing" class="px-3 py-1 typing">AI is typing...</div>

        <div class="chatbot-footer">
            <input type="text" id="chatbot-input" placeholder="Ask me anything... (e.g. 'help', 'show image of Amoxicillin')" aria-label="Type your message here">
            <button id="chatbot-send" type="button" aria-label="Send message">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script>
// Pass the absolute API path to the AI Assistant JS (resolved server-side)
window.CHATBOT_API_PATH = '<?php echo $base_url; ?>ai-assistant/ai-assistant-api.php';
window.CHATBOT_DEBUG = <?php echo json_encode(['base_url' => $base_url, 'api_path' => $base_url . 'ai-assistant/ai-assistant-api.php']); ?>;
</script>
<script src="<?php echo $chatbot_path; ?>ai-assistant.js"></script>
