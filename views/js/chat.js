document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('optic-chat-toggle');
    const chatContainer = document.getElementById('optic-chat-container');
    const closeBtn = document.getElementById('optic-chat-close');
    const clearBtn = document.getElementById('optic-chat-clear');
    const sendBtn = document.getElementById('optic-chat-send');
    const inputField = document.getElementById('optic-chat-input');
    const messagesArea = document.getElementById('optic-chat-messages');

    // Per-shop unique storage keys
    const shopSuffix = '_' + (typeof optic_chat_shop_domain !== 'undefined'
        ? optic_chat_shop_domain
        : window.location.hostname.replace(/[^a-z0-9]/gi, '_'));
    const STORAGE_HISTORY      = 'optic_chat_history'      + shopSuffix;
    const STORAGE_IS_OPEN      = 'optic_chat_is_open'      + shopSuffix;
    const STORAGE_MANUAL_CLOSE = 'optic_chat_manual_close' + shopSuffix;

    // Feature 5: In-memory conversation history for OpenAI context
    let conversationHistory = [];

    // 1. Load History on Start
    loadChatState();

    // 2. Events
    toggleBtn.addEventListener('click', openChat);
    closeBtn.addEventListener('click', closeChat);
    sendBtn.addEventListener('click', sendMessage);

    // Feature 5: Clear button
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (confirm('Να διαγραφεί το ιστορικό της συνομιλίας;')) {
                localStorage.removeItem(STORAGE_HISTORY);
                localStorage.removeItem(STORAGE_IS_OPEN);
                conversationHistory = [];
                messagesArea.innerHTML = '';
                createBotMessage({ type: 'text', content: typeof optic_chat_welcome_message !== 'undefined' ? optic_chat_welcome_message : 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊' });
            }
        });
    }
    
    inputField.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    // Quick Replies Click Event
    document.querySelectorAll('.quick-reply-chat').forEach(btn => {
        btn.addEventListener('click', function() {
            inputField.value = this.getAttribute('data-msg');
            sendMessage();
        });
    });

    // Auto-Open Logic (μετά από 8 δευτερόλεπτα αν δεν έχει κλείσει ρητά)
    setTimeout(function() {
        if (localStorage.getItem(STORAGE_MANUAL_CLOSE) !== 'true' && 
            localStorage.getItem(STORAGE_IS_OPEN) !== 'true') {
            openChat();
        }
    }, 8000);

    // --- Functions ---

    function openChat() {
        chatContainer.classList.remove('optic-chat-closed');
        chatContainer.classList.add('optic-chat-open');
        toggleBtn.style.display = 'none';
        localStorage.setItem(STORAGE_IS_OPEN, 'true');
        scrollToBottom();
    }

    function closeChat() {
        chatContainer.classList.remove('optic-chat-open');
        chatContainer.classList.add('optic-chat-closed');
        toggleBtn.style.display = 'block';
        localStorage.setItem(STORAGE_IS_OPEN, 'false');
        localStorage.setItem(STORAGE_MANUAL_CLOSE, 'true'); 
    }

    // Feature 7: Keywords that trigger handoff
    const handoffKeywords = ['μιλήσω', 'υπάλληλο', 'αντιπρόσωπο', 'human', 'agent',
                              'τηλέφωνο', 'επικοινωνία', 'επικοινωνήσω'];

    function isHandoffQuery(message) {
        const lc = message.toLowerCase();
        return handoffKeywords.some(function(kw) { return lc.indexOf(kw) !== -1; });
    }

    function sendMessage() {
        const message = inputField.value.trim();
        if (message === '') return;

        addMessageToChat(message, 'user-message');
        inputField.value = '';

        // Feature 7: Handoff detection – show contact buttons immediately
        if (typeof optic_chat_handoff !== 'undefined' && optic_chat_handoff &&
            isHandoffQuery(message)) {
            showHandoffOptions();
            return;
        }

        const loadingId = createMessageElement('Thinking...', 'bot-message typing-indicator');
        scrollToBottom();

        // --- ΛΗΨΗ ΙΣΤΟΡΙΚΟΥ ---
        let history = JSON.parse(localStorage.getItem(STORAGE_HISTORY)) || [];
        let recentHistory = history.slice(-6); // Στέλνουμε τα τελευταία 6

        // --- ΛΗΨΗ PAGE CONTEXT ---
        const pageContext = getPageContext();

        fetch(optic_chat_ajax_url, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'ajax=1&action=displayAjaxMessage&message=' + encodeURIComponent(message) + 
                  '&history=' + encodeURIComponent(JSON.stringify(conversationHistory.slice(-10))) +
                  '&page_context=' + encodeURIComponent(JSON.stringify(pageContext))
        })
        .then(response => response.json())
        .then(data => {
            const loadingMsg = document.getElementById(loadingId);
            if (loadingMsg) loadingMsg.remove();
            
            if (data.status === 'success') {
                // Feature 5: track assistant reply in conversationHistory
                const replyText = extractTextContent(data.reply);
                conversationHistory.push({ role: 'assistant', content: replyText });
                if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);
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

    function extractTextContent(data) {
        if (typeof data === 'string') return data;
        if (data && data.type === 'text') return data.content || '';
        if (data && data.type === 'mixed' && Array.isArray(data.content)) {
            return data.content.filter(function(i) { return i.type === 'text'; })
                               .map(function(i) { return i.text; }).join(' ');
        }
        return '';
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
            // Feature 5: track user message in conversationHistory
            const userText = typeof data === 'string' ? data : (data.content || '');
            conversationHistory.push({ role: 'user', content: userText });
            if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);
            createUserMessage(data);
        }
        saveMessageToStorage(data, className);
        scrollToBottom();
    }

    function createUserMessage(text) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message user-message';
        
        if (typeof text === 'string') {
            msgDiv.textContent = text;
        } else if (text && typeof text === 'object') {
            // Extract actual text content - never show raw JSON
            if (typeof text.content === 'string') {
                msgDiv.textContent = text.content;
            } else if (typeof text.text === 'string') {
                msgDiv.textContent = text.text;
            } else {
                // Last resort: try to find any string value
                const firstString = Object.values(text).find(v => typeof v === 'string');
                msgDiv.textContent = firstString || '';
            }
        } else {
            msgDiv.textContent = String(text);
        }
        
        messagesArea.appendChild(msgDiv);
    }

    function createBotMessage(data) {
        const container = document.createElement('div');
        container.className = 'message bot-message';
        
        // Handle different response types
        if (typeof data === 'string') {
            // Legacy plain text
            const textDiv = document.createElement('div');
            textDiv.className = 'bot-text';
            textDiv.innerHTML = escapeHtml(data).replace(/\n/g, '<br>');
            container.appendChild(textDiv);
        } else if (data.type === 'text') {
            // Simple text response
            const textDiv = document.createElement('div');
            textDiv.className = 'bot-text';
            textDiv.innerHTML = escapeHtml(data.content).replace(/\n/g, '<br>');
            container.appendChild(textDiv);
        } else if (data.type === 'comparison') {
            // Feature 6: Comparison card
            if (data.text) {
                const textDiv = document.createElement('div');
                textDiv.className = 'bot-text';
                textDiv.innerHTML = escapeHtml(data.text).replace(/\n/g, '<br>');
                container.appendChild(textDiv);
            }
            if (data.products && data.products.length >= 2) {
                const compCard = createComparisonCard(data.products);
                container.appendChild(compCard);
            }
        } else if (data.type === 'mixed') {
            // Mixed content (text + products) - group products into grid wrapper
            let productsWrapper = null;

            data.content.forEach(item => {
                if (item.type === 'text') {
                    // Reset products grid wrapper for next product group
                    productsWrapper = null;

                    const textDiv = document.createElement('div');
                    textDiv.className = 'bot-text';
                    textDiv.innerHTML = escapeHtml(item.text).replace(/\n/g, '<br>');
                    container.appendChild(textDiv);
                } else if (item.type === 'product') {
                    // Create a products-grid wrapper only once per consecutive product group
                    if (!productsWrapper) {
                        productsWrapper = document.createElement('div');
                        productsWrapper.className = 'products-grid';
                        container.appendChild(productsWrapper);
                    }
                    const productCard = createProductCard(item);
                    productsWrapper.appendChild(productCard);
                }
            });
        } else if (data.type === 'contact_card') {
            if (data.text) {
                const textDiv = document.createElement('div');
                textDiv.className = 'bot-text';
                textDiv.innerHTML = escapeHtml(data.text).replace(/\n/g, '<br>');
                container.appendChild(textDiv);
            }
            const contactCard = createContactCard();
            if (contactCard) {
                container.appendChild(contactCard);
            }
        }
        
        messagesArea.appendChild(container);
    }

    function createProductCard(product) {
        const card = document.createElement('div');
        card.className = 'product-card';
        
        const imageUrl = product.image || '';
        const fallbackImage = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23f0f0f0" width="200" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-family="Arial" font-size="14"%3EΧωρίς Εικόνα%3C/text%3E%3C/svg%3E';
        
        card.innerHTML = `
            <div class="product-image">
                <img src="${escapeHtml(imageUrl)}" 
                     alt="${escapeHtml(product.name)}" 
                     onerror="this.src='${fallbackImage}'">
            </div>
            <div class="product-info">
                <h4 class="product-name">${escapeHtml(product.name)}</h4>
                <div class="product-price">${escapeHtml(product.price)}€</div>
                <a href="${escapeHtml(product.url)}" target="_blank" class="product-link">
                    Δείτε το προϊόν →
                </a>
            </div>
        `;

        // Feature 1: Add to Cart button
        const buyBtn = document.createElement('a');
        buyBtn.href = product.url;
        buyBtn.className = 'optic-product-buy-btn';
        buyBtn.textContent = (typeof optic_chat_labels !== 'undefined' && optic_chat_labels && optic_chat_labels.add_to_cart)
            ? optic_chat_labels.add_to_cart
            : 'Αγορά →';
        buyBtn.target = '_blank';
        buyBtn.rel = 'noopener noreferrer';
        const infoDiv = card.querySelector('.product-info');
        if (infoDiv) infoDiv.appendChild(buyBtn);
        
        return card;
    }

    // Feature 6: Comparison card
    function createComparisonCard(products) {
        const wrapper = document.createElement('div');
        wrapper.className = 'comparison-card';

        const grid = document.createElement('div');
        grid.className = 'comparison-grid';

        products.slice(0, 2).forEach(function(product, idx) {
            const card = createProductCard(product);
            grid.appendChild(card);
            if (idx === 0 && products.length > 1) {
                const vs = document.createElement('div');
                vs.className = 'comparison-vs';
                vs.textContent = 'VS';
                grid.appendChild(vs);
            }
        });

        wrapper.appendChild(grid);
        return wrapper;
    }

    // Feature 7: Show handoff options
    function showHandoffOptions() {
        if (typeof optic_chat_handoff === 'undefined' || !optic_chat_handoff) return;

        const container = document.createElement('div');
        container.className = 'message bot-message';

        const textDiv = document.createElement('div');
        textDiv.className = 'bot-text';
        textDiv.textContent = 'Μπορείτε να επικοινωνήσετε μαζί μας μέσω:';
        container.appendChild(textDiv);

        const card = document.createElement('div');
        card.className = 'handoff-card';

        const h = optic_chat_handoff;

        if (h.phone) {
            const a = document.createElement('a');
            a.href = 'tel:' + h.phone.replace(/[^0-9+]/g, '');
            a.className = 'handoff-btn handoff-btn--phone';
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>📞</span><span>' + escapeHtml(h.phone) + '</span>';
            card.appendChild(a);
        }
        if (h.whatsapp) {
            const a = document.createElement('a');
            a.href = 'https://wa.me/' + h.whatsapp.replace(/[^0-9+]/g, '');
            a.className = 'handoff-btn handoff-btn--whatsapp';
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>💬</span><span>WhatsApp</span>';
            card.appendChild(a);
        }
        if (h.viber) {
            const a = document.createElement('a');
            a.href = 'viber://chat?number=' + h.viber.replace(/[^0-9+]/g, '');
            a.className = 'handoff-btn handoff-btn--viber';
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>📱</span><span>Viber</span>';
            card.appendChild(a);
        }
        if (h.email) {
            const a = document.createElement('a');
            a.href = 'mailto:' + h.email;
            a.className = 'handoff-btn handoff-btn--email';
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>✉️</span><span>' + escapeHtml(h.email) + '</span>';
            card.appendChild(a);
        }

        container.appendChild(card);
        messagesArea.appendChild(container);
        scrollToBottom();
    }

    function createContactCard() {
        if (typeof optic_chat_contact_links === 'undefined' || !optic_chat_contact_links || !optic_chat_contact_links.length) {
            return null;
        }
        const card = document.createElement('div');
        card.className = 'contact-card';

        optic_chat_contact_links.forEach(function(link) {
            const btn = document.createElement('a');
            btn.href = link.url;
            btn.target = '_blank';
            btn.rel = 'noopener noreferrer';
            btn.className = 'contact-btn contact-btn--' + escapeHtml(link.type);
            btn.innerHTML = '<span class="contact-btn-icon">' + link.icon + '</span>'
                          + '<span class="contact-btn-label">' + escapeHtml(link.label) + '</span>';
            card.appendChild(btn);
        });

        return card;
    }

    function escapeHtml(text) {
        // Convert to string if not already
        const str = text != null ? String(text) : '';
        const div = document.createElement('div');
        div.textContent = str;
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
        let history = JSON.parse(localStorage.getItem(STORAGE_HISTORY)) || [];
        history.push({ text: data, class: className }); // store as object directly
        if (history.length > 50) history = history.slice(-50);
        localStorage.setItem(STORAGE_HISTORY, JSON.stringify(history));
    }

    function loadChatState() {
        if (localStorage.getItem(STORAGE_IS_OPEN) === 'true') {
            chatContainer.classList.remove('optic-chat-closed');
            chatContainer.classList.add('optic-chat-open');
            toggleBtn.style.display = 'none';
        }
        
        const history = JSON.parse(localStorage.getItem(STORAGE_HISTORY)) || [];
        
        history.forEach(msg => {
            try {
                let data;
                
                // Parse the stored text
                if (typeof msg.text === 'string') {
                    try {
                        data = JSON.parse(msg.text);
                    } catch (e) {
                        // Not JSON, treat as plain text
                        data = msg.text;
                    }
                } else {
                    data = msg.text;
                }
                
                // Render based on message type
                if (msg.class && msg.class.includes('bot-message')) {
                    createBotMessage(data);
                    // Feature 5: rebuild conversationHistory
                    const txt = extractTextContent(data);
                    if (txt) conversationHistory.push({ role: 'assistant', content: txt });
                } else {
                    createUserMessage(data);
                    // Feature 5: rebuild conversationHistory
                    const txt = typeof data === 'string' ? data : (data && data.content ? data.content : '');
                    if (txt) conversationHistory.push({ role: 'user', content: txt });
                }
            } catch (e) {
                // Skip malformed messages to prevent breaking entire history
                console.error('Error loading message:', e);
            }
        });
        
        if (history.length === 0) {
            const welcomeMsg = typeof optic_chat_welcome_message !== 'undefined' 
                ? optic_chat_welcome_message 
                : "Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊";
            createBotMessage({
                type: 'text',
                content: welcomeMsg
            });
        }
        
        scrollToBottom();
    }

    // Feature 8: Proactive chat
    function initProactiveChat() {
        if (typeof optic_chat_proactive === 'undefined' || !optic_chat_proactive) return;
        if (!optic_chat_proactive.enabled) return;
        if (optic_chat_proactive.pages === 'product_only' && !optic_chat_proactive.is_product_page) return;

        const STORAGE_PROACTIVE_COUNT = 'optic_chat_proactive_count' + shopSuffix;
        const shownTimes = parseInt(localStorage.getItem(STORAGE_PROACTIVE_COUNT) || '0');
        const maxTimes = optic_chat_proactive.times === 'always' ? Infinity : parseInt(optic_chat_proactive.times);

        if (shownTimes >= maxTimes) return;
        if (localStorage.getItem(STORAGE_IS_OPEN) === 'true') return; // chat already open

        setTimeout(function() {
            openChat();
            createBotMessage({ type: 'text', content: optic_chat_proactive.message });
            localStorage.setItem(STORAGE_PROACTIVE_COUNT, shownTimes + 1);
        }, optic_chat_proactive.delay * 1000);
    }

    initProactiveChat();
});