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

        // --- ΛΗΨΗ PAGE CONTEXT ---
        const pageContext = getPageContext();

        fetch(optic_chat_ajax_url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            // Στέλνουμε ΚΑΙ το history ΚΑΙ το page context
            body: 'ajax=1&action=displayAjaxMessage&message=' + encodeURIComponent(message) + 
                  '&history=' + encodeURIComponent(JSON.stringify(recentHistory)) +
                  '&page_context=' + encodeURIComponent(JSON.stringify(pageContext))
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

    function getPageContext() {
        const context = {
            url: window.location.href,
            title: document.title,
            type: 'unknown'
        };
        
        // Detect page type
        if (document.body.classList.contains('product')) {
            context.type = 'product';
            const productName = document.querySelector('.product-title, h1[itemprop="name"]');
            const productPrice = document.querySelector('.product-price, [itemprop="price"]');
            if (productName) context.productName = productName.textContent.trim();
            if (productPrice) context.productPrice = productPrice.textContent.trim();
        } else if (document.body.classList.contains('category')) {
            context.type = 'category';
            const categoryName = document.querySelector('.category-title, h1');
            if (categoryName) context.categoryName = categoryName.textContent.trim();
        } else if (document.body.classList.contains('cms')) {
            context.type = 'cms';
        } else if (document.body.classList.contains('index')) {
            context.type = 'home';
        } else if (document.body.classList.contains('cart')) {
            context.type = 'cart';
        }
        
        return context;
    }

    function addMessageToChat(data, className) {
        if (className === 'bot-message') {
            createBotMessage(data);
        } else {
            createUserMessage(data);
        }
        saveMessageToStorage(data, className);
        scrollToBottom();
    }

    function createUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message user-message';
        msgDiv.textContent = text;
        messagesArea.appendChild(msgDiv);
    }

    function createBotMessage(data) {
        const container = document.createElement('div');
        container.className = 'message bot-message';
        
        // Handle different response types
        if (typeof data === 'string') {
            // Legacy plain text
            container.innerHTML = escapeHtml(data);
        } else if (data.type === 'text') {
            // Simple text response
            container.innerHTML = escapeHtml(data.content);
        } else if (data.type === 'mixed') {
            // Mixed content (text + products)
            data.content.forEach(item => {
                if (item.type === 'text') {
                    const textDiv = document.createElement('div');
                    textDiv.className = 'bot-text';
                    textDiv.innerHTML = escapeHtml(item.text);
                    container.appendChild(textDiv);
                } else if (item.type === 'product') {
                    const productCard = createProductCard(item);
                    container.appendChild(productCard);
                }
            });
        }
        
        messagesArea.appendChild(container);
    }

    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <div class="product-image">
                <img src="${escapeHtml(product.image)}" alt="${escapeHtml(product.name)}" 
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'100\\' height=\\'100\\'%3E%3Crect fill=\\'%23f0f0f0\\' width=\\'100\\' height=\\'100\\'/%3E%3Ctext x=\\'50%25\\' y=\\'50%25\\' text-anchor=\\'middle\\' dy=\\'.3em\\' fill=\\'%23999\\'%3ENo Image%3C/text%3E%3C/svg%3E'">
            </div>
            <div class="product-info">
                <h4 class="product-name">${escapeHtml(product.name)}</h4>
                <div class="product-price">${escapeHtml(product.price)}€</div>
                <a href="${escapeHtml(product.url)}" target="_blank" class="product-link">
                    Δείτε το προϊόν →
                </a>
            </div>
        `;
        return card;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function createMessageElement(text, className) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message ' + className;
        msgDiv.id = 'msg-' + Date.now() + Math.random(); 
        msgDiv.textContent = text;
        messagesArea.appendChild(msgDiv);
        return msgDiv.id;
    }

    function scrollToBottom() {
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function saveMessageToStorage(data, className) {
        let history = JSON.parse(localStorage.getItem('optic_chat_history')) || [];
        // Store simplified version for history context
        const text = typeof data === 'string' ? data : 
                     data.type === 'text' ? data.content : 
                     JSON.stringify(data);
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
        history.forEach(msg => {
            // Load stored messages (may be old format)
            if (msg.class === 'bot-message') {
                try {
                    const data = typeof msg.text === 'string' && msg.text.startsWith('{') ? 
                                JSON.parse(msg.text) : msg.text;
                    createBotMessage(data);
                } catch (e) {
                    createBotMessage(msg.text);
                }
            } else {
                createUserMessage(msg.text);
            }
        });
        
        if (history.length === 0) {
             addMessageToChat("Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω;", "bot-message");
        }
    }
});