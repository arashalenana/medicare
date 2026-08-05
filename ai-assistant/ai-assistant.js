// Frontend JavaScript
function initChatbot() {
    const toggle  = document.getElementById('chatbot-toggle');
    const panel   = document.getElementById('chatbot-panel');
    const sendBtn = document.getElementById('chatbot-send');
    const input   = document.getElementById('chatbot-input');
    const body    = document.getElementById('chatbot-body');
    const typing  = document.getElementById('chatbot-typing');

    // Use absolute API path set by server (window.CHATBOT_API_PATH from chatbot-ui.php)
    const apiPath = window.CHATBOT_API_PATH;
    
    if (!apiPath) {
        console.error('[Chatbot] ERROR: API path not set. window.CHATBOT_API_PATH is undefined.');
        return;
    }

    // Toggle Panel
    if (toggle && panel) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const isVisible = window.getComputedStyle(panel).display === 'flex';
            panel.style.display = isVisible ? 'none' : 'flex';
            if (panel.style.display === 'flex' && input) {
                input.focus();
            }
        });
    }

    // Send Message
    async function sendMessage() {
        if (!input) return;
        const message = input.value.trim();
        if (!message) return;

        addMessage(message, 'user');
        input.value = '';

        if (typing) {
            typing.style.display = 'block';
        }
        if (body) {
            body.scrollTop = body.scrollHeight;
        }

        try {
            // Use the absolute API path directly
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ message: message })
            });

            console.log('[Chatbot] Response status:', response.status);
            
            if (!response.ok) {
                throw new Error('HTTP ' + response.status + ': ' + response.statusText);
            }

            const data = await response.json();
            
            if (typing) typing.style.display = 'none';
            console.log('[Chatbot] Response data:', data);

            if (data && data.status === 'success') {
                // Handle image response (medicine image generation)
                if (data.type === 'image' && data.image_url) {
                    addImageMessage(data.response, data.image_url);
                } else {
                    addMessage(data.response, 'bot');
                }
            } else {
                const errorMsg = (data && data.message) ? data.message : 'An error occurred. Please try again.';
                addMessage('&#10060; ' + errorMsg, 'bot');
            }
        } catch (error) {
            if (typing) typing.style.display = 'none';
            console.error('[Chatbot] Fetch error:', error);
            addMessage('&#128533; Sorry, something went wrong. Error: ' + error.message, 'bot');
        }
    }

    // Add Text Message
    function addMessage(text, sender) {
        if (!body) return;
        const div  = document.createElement('div');
        div.className = 'message ' + sender + '-message';

        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        div.innerHTML = text + '<span class="message-time">' + time + '</span>';

        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }

    // Add Image Message (for medicine image generation)
    function addImageMessage(caption, imageUrl) {
        if (!body) return;
        const div = document.createElement('div');
        div.className = 'message bot-message';

        const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        // Create caption paragraph
        const captionEl = document.createElement('p');
        captionEl.style.cssText = 'margin:0 0 8px 0;';
        captionEl.innerHTML = caption;
        div.appendChild(captionEl);

        // Create image link wrapper for proper opening
        const imgLink = document.createElement('a');
        imgLink.href = imageUrl;
        imgLink.target = '_blank';
        imgLink.rel = 'noopener noreferrer';
        imgLink.style.cssText = 'display:inline-block;text-decoration:none;';

        // Create image element
        const img = document.createElement('img');
        img.src = imageUrl;
        img.alt = 'Medicine Image';
        img.style.cssText = 'max-width:100%;max-height:300px;border-radius:8px;display:block;cursor:pointer;transition:opacity 0.3s ease;opacity:0.7;';
        img.title = 'Click to open full size';

        // Improve opacity when loaded
        img.addEventListener('load', function() {
            img.style.opacity = '1';
        });

        // Handle load errors gracefully
        img.addEventListener('error', function() {
            img.style.display = 'none';
            const errorMsg = document.createElement('p');
            errorMsg.style.cssText = 'color:#999;font-size:12px;margin:0;';
            errorMsg.textContent = '(Image loading - click link to view)';
            imgLink.appendChild(errorMsg);
        });

        // Append image to link
        imgLink.appendChild(img);
        div.appendChild(imgLink);

        // Add timestamp
        const timeEl = document.createElement('span');
        timeEl.className = 'message-time';
        timeEl.textContent = time;
        div.appendChild(timeEl);

        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }

    // Event Listeners
    if (sendBtn && input) {
        sendBtn.addEventListener('click', sendMessage);
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendMessage();
        });
    }
}

// Initialize chatbot when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initChatbot);
} else {
    initChatbot();
}
