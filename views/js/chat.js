document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('optic-chat-toggle');
    const chatContainer = document.getElementById('optic-chat-container');
    const closeBtn = document.getElementById('optic-chat-close');
    const sendBtn = document.getElementById('optic-chat-send');
    const inputField = document.getElementById('optic-chat-input');
    const messagesArea = document.getElementById('optic-chat-messages');

    // 1. Load History on Start
    loadChatState();

    // 2. Events
    toggleBtn.addEventListener('click', openChat);
    closeBtn.addEventListener('click', closeChat);
    sendBtn.addEventListener('click', sendMessage);
    
    inputField.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    // Quick Replies Click Event
    document.querySelectorAll('.quick-reply-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            inputField.value = this.getAttribute('data-msg');
            sendMessage();
        });
    });

    // Auto-Open Logic (μετά από 8 δευτερόλεπτα αν δεν έχει κλείσει ρητά)
    setTimeout(function() {
        if (localStorage.getItem('optic_chat_manual_close') !== 'true' && 
            localStorage.getItem('optic_chat_is_open') !== 'true') {
            openChat();
        }
    }, 8000);

    // --- Functions ---

    function openChat() {
        chatContainer.classList.remove('optic-chat-closed');
        chatContainer.classList.add('optic-chat-open');
        toggleBtn.style.display = 'none';
        localStorage.setItem('optic_chat_is_open', 'true');
        scrollToBottom();
    }

    function closeChat() {
        chatContainer.classList.remove('optic-chat-open');
        chatContainer.classList.add('optic-chat-closed');
        toggleBtn.style.display = 'block';
        localStorage.setItem('optic_chat_is_open', 'false');
        localStorage.setItem('optic_chat_manual_close', 'true'); 
    }

    function sendMessage() {
        const message = inputField.value.trim();
        if (message === '') return;

        addMessageToChat(message, 'user-message');
        inputField.value = '';

        const loadingId = createMessageElement('Thinking...', 'bot-message typing-indicator');
        scrollToBottom();

        // --- ΛΗΨΗ ΙΣΤΟΡΙΚΟΥ ---
        let history = JSON.parse(localStorage.getItem('optic_chat_history')) || [];
        let recentHistory = history.slice(-6); // Στέλνουμε τα τελευταία 6

        fetch(optic_chat_ajax_url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            // Στέλνουμε ΚΑΙ το history
            body: 'ajax=1&action=displayAjaxMessage&message=' + encodeURIComponent(message) + '&history=' + encodeURIComponent(JSON.stringify(recentHistory))
        })
        .then(response => response.json())
        .then(data => {
            const loadingMsg = document.getElementById(loadingId);
            if (loadingMsg) loadingMsg.remove();
            
            if (data.status === 'success') {
                addMessageToChat(data.reply, 'bot-message');
            } else {
                addMessageToChat(data.reply || 'Error.', 'bot-message');
            }
        })
        .catch(error => {
            const loadingMsg = document.getElementById(loadingId);
            if (loadingMsg) loadingMsg.remove();
            addMessageToChat('Connection Error.', 'bot-message');
        });
    }

    function addMessageToChat(text, className) {
        createMessageElement(text, className);
        saveMessageToStorage(text, className);
        scrollToBottom();
    }

    function createMessageElement(text, className) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message ' + className;
        msgDiv.id = 'msg-' + Date.now() + Math.random(); 
        msgDiv.innerHTML = text; 
        messagesArea.appendChild(msgDiv);
        return msgDiv.id;
    }

    function scrollToBottom() {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function saveMessageToStorage(text, className) {
        let history = JSON.parse(localStorage.getItem('optic_chat_history')) || [];
        history.push({ text: text, class: className });
        if (history.length > 50) history = history.slice(-50);
        localStorage.setItem('optic_chat_history', JSON.stringify(history));
    }

    function loadChatState() {
        if (localStorage.getItem('optic_chat_is_open') === 'true') {
            chatContainer.classList.remove('optic-chat-closed');
            chatContainer.classList.add('optic-chat-open');
            toggleBtn.style.display = 'none';
        }
        
        const history = JSON.parse(localStorage.getItem('optic_chat_history')) || [];
        history.forEach(msg => createMessageElement(msg.text, msg.class));
        
        if (history.length === 0) {
             addMessageToChat("Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω;", "bot-message");
        }
    }
});