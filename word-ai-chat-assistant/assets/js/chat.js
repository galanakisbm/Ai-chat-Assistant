document.addEventListener('DOMContentLoaded', function() {
    // WordPress localized vars (set via wp_localize_script)
    var ajaxUrl         = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.ajax_url        : '/wp-admin/admin-ajax.php';
    var chatNonce       = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.nonce            : '';
    var shopDomain      = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.shop_domain      : window.location.hostname.replace(/[^a-z0-9]/gi, '_');
    var welcomeMessage  = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.welcome_message  : 'Γεια σας! Πώς μπορώ να βοηθήσω; 😊';
    var quickButtons    = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.quick_buttons    : [];
    var contactLinks    = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.contact_links    : [];
    var proactive       = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.proactive        : null;
    var handoff         = (typeof optic_chat_vars !== 'undefined') ? optic_chat_vars.handoff          : null;

    var toggleBtn     = document.getElementById('optic-chat-toggle');
    var chatContainer = document.getElementById('optic-chat-container');
    var closeBtn      = document.getElementById('optic-chat-close');
    var clearBtn      = document.getElementById('optic-chat-clear');
    var sendBtn       = document.getElementById('optic-chat-send');
    var inputField    = document.getElementById('optic-chat-input');
    var messagesArea  = document.getElementById('optic-chat-messages');

    if (!toggleBtn || !chatContainer) return;

    // Per-shop unique storage keys
    var shopSuffix         = '_' + shopDomain;
    var STORAGE_HISTORY      = 'optic_chat_history'      + shopSuffix;
    var STORAGE_IS_OPEN      = 'optic_chat_is_open'      + shopSuffix;
    var STORAGE_MANUAL_CLOSE = 'optic_chat_manual_close' + shopSuffix;

    // In-memory conversation history for OpenAI context
    var conversationHistory = [];

    // 1. Load History on Start
    loadChatState();

    // 2. Events
    toggleBtn.addEventListener('click', openChat);
    closeBtn.addEventListener('click', closeChat);
    sendBtn.addEventListener('click', sendMessage);

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (confirm('Να διαγραφεί το ιστορικό της συνομιλίας;')) {
                localStorage.removeItem(STORAGE_HISTORY);
                localStorage.removeItem(STORAGE_IS_OPEN);
                conversationHistory = [];
                messagesArea.innerHTML = '';
                createBotMessage({ type: 'text', content: welcomeMessage });
            }
        });
    }

    inputField.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') sendMessage();
    });

    // Quick Replies
    document.querySelectorAll('.quick-reply-chat').forEach(function(btn) {
        btn.addEventListener('click', function() {
            inputField.value = this.getAttribute('data-msg');
            sendMessage();
        });
    });

    // Auto-Open Logic (if enabled and not manually closed)
    if (proactive && proactive.enabled) {
        initProactiveChat();
    }

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

    // Handoff keywords
    var handoffKeywords = ['μιλήσω', 'υπάλληλο', 'αντιπρόσωπο', 'human', 'agent',
                           'τηλέφωνο', 'επικοινωνία', 'επικοινωνήσω'];

    function isHandoffQuery(message) {
        var lc = message.toLowerCase();
        return handoffKeywords.some(function(kw) { return lc.indexOf(kw) !== -1; });
    }

    function sendMessage() {
        var message = inputField.value.trim();
        if (message === '') return;

        addMessageToChat(message, 'user-message');
        inputField.value = '';

        // Handoff detection
        if (handoff && isHandoffQuery(message)) {
            showHandoffOptions();
            return;
        }

        var loadingId = createMessageElement('Thinking...', 'bot-message typing-indicator');
        scrollToBottom();

        var history = JSON.parse(localStorage.getItem(STORAGE_HISTORY)) || [];

        var pageContext = getPageContext();

        // WordPress AJAX: use action + nonce
        var params = new URLSearchParams();
        params.append('action',       'optic_chat_message');
        params.append('nonce',        chatNonce);
        params.append('message',      message);
        params.append('history',      JSON.stringify(conversationHistory.slice(-10)));
        params.append('page_context', JSON.stringify(pageContext));

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var loadingMsg = document.getElementById(loadingId);
            if (loadingMsg) loadingMsg.remove();

            if (data.status === 'success') {
                var replyText = extractTextContent(data.reply);
                conversationHistory.push({ role: 'assistant', content: replyText });
                if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);
                addMessageToChat(data.reply, 'bot-message');
            } else {
                addMessageToChat(data.reply || 'Error.', 'bot-message');
            }
        })
        .catch(function() {
            var loadingMsg = document.getElementById(loadingId);
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
        var context = {
            url: window.location.href,
            title: document.title,
            type: 'unknown'
        };

        // WordPress / WooCommerce page-type detection
        if (document.body.classList.contains('single-product') || document.body.classList.contains('product')) {
            context.type = 'product';
            var productName  = document.querySelector('.product_title, h1.entry-title');
            var productPrice = document.querySelector('.price .woocommerce-Price-amount, .price');
            if (productName)  context.productName  = productName.textContent.trim();
            if (productPrice) context.productPrice = productPrice.textContent.trim();
        } else if (document.body.classList.contains('tax-product_cat') || document.body.classList.contains('term-product_cat')) {
            context.type = 'category';
            var catTitle = document.querySelector('.woocommerce-products-header__title, h1');
            if (catTitle) context.categoryName = catTitle.textContent.trim();
        } else if (document.body.classList.contains('woocommerce-cart')) {
            context.type = 'cart';
        } else if (document.body.classList.contains('woocommerce-checkout')) {
            context.type = 'checkout';
        } else if (document.body.classList.contains('home') || document.body.classList.contains('blog')) {
            context.type = 'home';
        } else if (document.body.classList.contains('page')) {
            context.type = 'page';
        }

        return context;
    }

    function addMessageToChat(data, className) {
        if (className === 'bot-message') {
            createBotMessage(data);
        } else {
            var userText = typeof data === 'string' ? data : (data.content || '');
            conversationHistory.push({ role: 'user', content: userText });
            if (conversationHistory.length > 20) conversationHistory = conversationHistory.slice(-20);
            createUserMessage(data);
        }
        saveMessageToStorage(data, className);
        scrollToBottom();
    }

    function createUserMessage(text) {
        var msgDiv = document.createElement('div');
        msgDiv.className = 'message user-message';

        if (typeof text === 'string') {
            msgDiv.textContent = text;
        } else if (text && typeof text === 'object') {
            if (typeof text.content === 'string') {
                msgDiv.textContent = text.content;
            } else if (typeof text.text === 'string') {
                msgDiv.textContent = text.text;
            } else {
                var firstString = Object.values(text).find(function(v) { return typeof v === 'string'; });
                msgDiv.textContent = firstString || '';
            }
        } else {
            msgDiv.textContent = String(text);
        }

        messagesArea.appendChild(msgDiv);
    }

    function createBotMessage(data) {
        var container = document.createElement('div');
        container.className = 'message bot-message';

        if (typeof data === 'string') {
            var textDiv = document.createElement('div');
            textDiv.className = 'bot-text';
            textDiv.innerHTML = escapeHtml(data).replace(/\n/g, '<br>');
            container.appendChild(textDiv);
        } else if (data.type === 'text') {
            var textDiv = document.createElement('div');
            textDiv.className = 'bot-text';
            textDiv.innerHTML = escapeHtml(data.content).replace(/\n/g, '<br>');
            container.appendChild(textDiv);
        } else if (data.type === 'comparison') {
            if (data.text) {
                var textDiv = document.createElement('div');
                textDiv.className = 'bot-text';
                textDiv.innerHTML = escapeHtml(data.text).replace(/\n/g, '<br>');
                container.appendChild(textDiv);
            }
            if (data.products && data.products.length >= 2) {
                container.appendChild(createComparisonCard(data.products));
            }
        } else if (data.type === 'mixed') {
            var productsWrapper = null;
            data.content.forEach(function(item) {
                if (item.type === 'text') {
                    productsWrapper = null;
                    var textDiv = document.createElement('div');
                    textDiv.className = 'bot-text';
                    textDiv.innerHTML = escapeHtml(item.text).replace(/\n/g, '<br>');
                    container.appendChild(textDiv);
                } else if (item.type === 'product') {
                    if (!productsWrapper) {
                        productsWrapper = document.createElement('div');
                        productsWrapper.className = 'products-grid';
                        container.appendChild(productsWrapper);
                    }
                    productsWrapper.appendChild(createProductCard(item));
                }
            });
        } else if (data.type === 'contact_card') {
            if (data.text) {
                var textDiv = document.createElement('div');
                textDiv.className = 'bot-text';
                textDiv.innerHTML = escapeHtml(data.text).replace(/\n/g, '<br>');
                container.appendChild(textDiv);
            }
            var contactCard = createContactCard();
            if (contactCard) container.appendChild(contactCard);
        }

        messagesArea.appendChild(container);
    }

    function createProductCard(product) {
        var card = document.createElement('div');
        card.className = 'product-card';

        var imageUrl = product.image || '';
        var fallbackImage = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200"%3E%3Crect fill="%23f0f0f0" width="200" height="200"/%3E%3Ctext x="50%25" y="50%25" text-anchor="middle" dy=".3em" fill="%23999" font-family="Arial" font-size="14"%3ENo Image%3C/text%3E%3C/svg%3E';

        card.innerHTML =
            '<div class="product-image">'
            + '<img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(product.name) + '" onerror="this.src=\'' + fallbackImage + '\'">'
            + '</div>'
            + '<div class="product-info">'
            + '<h4 class="product-name">' + escapeHtml(product.name) + '</h4>'
            + '<div class="product-price">' + escapeHtml(product.price) + '€</div>'
            + '<a href="' + escapeHtml(product.url) + '" target="_blank" rel="noopener noreferrer" class="product-link">Δείτε το προϊόν →</a>'
            + '</div>';

        // Add to cart button
        var buyBtn = document.createElement('a');
        buyBtn.href = product.url;
        buyBtn.className = 'optic-product-buy-btn';
        buyBtn.textContent = 'Αγορά →';
        buyBtn.target = '_blank';
        buyBtn.rel = 'noopener noreferrer';
        var infoDiv = card.querySelector('.product-info');
        if (infoDiv) infoDiv.appendChild(buyBtn);

        return card;
    }

    function createComparisonCard(products) {
        var wrapper = document.createElement('div');
        wrapper.className = 'comparison-card';

        var grid = document.createElement('div');
        grid.className = 'comparison-grid';

        products.slice(0, 2).forEach(function(product, idx) {
            grid.appendChild(createProductCard(product));
            if (idx === 0 && products.length > 1) {
                var vs = document.createElement('div');
                vs.className = 'comparison-vs';
                vs.textContent = 'VS';
                grid.appendChild(vs);
            }
        });

        wrapper.appendChild(grid);
        return wrapper;
    }

    function showHandoffOptions() {
        if (!handoff) return;

        var container = document.createElement('div');
        container.className = 'message bot-message';

        var textDiv = document.createElement('div');
        textDiv.className = 'bot-text';
        textDiv.textContent = 'Μπορείτε να επικοινωνήσετε μαζί μας μέσω:';
        container.appendChild(textDiv);

        var card = document.createElement('div');
        card.className = 'handoff-card';

        if (handoff.phone) {
            var a = document.createElement('a');
            a.href = 'tel:' + handoff.phone.replace(/[^0-9+]/g, '');
            a.className = 'handoff-btn handoff-btn--phone';
            a.target = '_blank'; a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>📞</span><span>' + escapeHtml(handoff.phone) + '</span>';
            card.appendChild(a);
        }
        if (handoff.whatsapp) {
            var a = document.createElement('a');
            a.href = 'https://wa.me/' + handoff.whatsapp.replace(/[^0-9+]/g, '');
            a.className = 'handoff-btn handoff-btn--whatsapp';
            a.target = '_blank'; a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>💬</span><span>WhatsApp</span>';
            card.appendChild(a);
        }
        if (handoff.viber) {
            var a = document.createElement('a');
            a.href = 'viber://chat?number=' + handoff.viber.replace(/[^0-9+]/g, '');
            a.className = 'handoff-btn handoff-btn--viber';
            a.target = '_blank'; a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>📱</span><span>Viber</span>';
            card.appendChild(a);
        }
        if (handoff.email) {
            var a = document.createElement('a');
            a.href = 'mailto:' + handoff.email;
            a.className = 'handoff-btn handoff-btn--email';
            a.target = '_blank'; a.rel = 'noopener noreferrer';
            a.innerHTML = '<span>✉️</span><span>' + escapeHtml(handoff.email) + '</span>';
            card.appendChild(a);
        }

        container.appendChild(card);
        messagesArea.appendChild(container);
        scrollToBottom();
    }

    function createContactCard() {
        if (!contactLinks || !contactLinks.length) return null;
        var card = document.createElement('div');
        card.className = 'contact-card';
        contactLinks.forEach(function(link) {
            var btn = document.createElement('a');
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
        var str = text != null ? String(text) : '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function createMessageElement(text, className) {
        var msgDiv = document.createElement('div');
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
        var history = JSON.parse(localStorage.getItem(STORAGE_HISTORY)) || [];
        history.push({ text: data, class: className });
        if (history.length > 50) history = history.slice(-50);
        localStorage.setItem(STORAGE_HISTORY, JSON.stringify(history));
    }

    function loadChatState() {
        if (localStorage.getItem(STORAGE_IS_OPEN) === 'true') {
            chatContainer.classList.remove('optic-chat-closed');
            chatContainer.classList.add('optic-chat-open');
            toggleBtn.style.display = 'none';
        }

        var history = JSON.parse(localStorage.getItem(STORAGE_HISTORY)) || [];

        history.forEach(function(msg) {
            try {
                var data;
                if (typeof msg.text === 'string') {
                    try { data = JSON.parse(msg.text); } catch(e) { data = msg.text; }
                } else {
                    data = msg.text;
                }

                if (msg.class && msg.class.includes('bot-message')) {
                    createBotMessage(data);
                    var txt = extractTextContent(data);
                    if (txt) conversationHistory.push({ role: 'assistant', content: txt });
                } else {
                    createUserMessage(data);
                    var txt = typeof data === 'string' ? data : (data && data.content ? data.content : '');
                    if (txt) conversationHistory.push({ role: 'user', content: txt });
                }
            } catch(e) {
                console.error('Error loading message:', e);
            }
        });

        if (history.length === 0) {
            createBotMessage({ type: 'text', content: welcomeMessage });
        }

        scrollToBottom();
    }

    function initProactiveChat() {
        if (!proactive || !proactive.enabled) return;
        if (proactive.pages === 'product_only' && !proactive.is_product_page) return;

        var STORAGE_PROACTIVE_COUNT = 'optic_chat_proactive_count' + shopSuffix;
        var shownTimes = parseInt(localStorage.getItem(STORAGE_PROACTIVE_COUNT) || '0', 10);
        var maxTimes   = proactive.times === 'always' ? Infinity : parseInt(proactive.times, 10);

        if (shownTimes >= maxTimes) return;
        if (localStorage.getItem(STORAGE_IS_OPEN) === 'true') return;

        setTimeout(function() {
            openChat();
            createBotMessage({ type: 'text', content: proactive.message });
            localStorage.setItem(STORAGE_PROACTIVE_COUNT, shownTimes + 1);
        }, proactive.delay * 1000);
    }
});
