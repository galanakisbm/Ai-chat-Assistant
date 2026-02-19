{if isset($optic_custom_css)}
    {$optic_custom_css nofilter}
{/if}

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<div id="optic-chat-container" class="optic-chat-closed">
    <div class="optic-chat-header">
        <div class="header-title">
            <img src="{$shop_logo}" alt="{$shop_name}" class="chat-header-logo" />
            <span>{$chat_title|escape:'html':'UTF-8'}</span>
        </div>
        <button id="optic-chat-close">✕</button>
    </div>

    <div id="optic-chat-messages"></div>

    <div id="optic-chat-suggestions">
        <button class="quick-reply-btn" data-msg="Τι προσφορές τρέχουν;">🏷️ Προσφορές</button>
        <button class="quick-reply-btn" data-msg="Πού είναι η παραγγελία μου;">📦 Η Παραγγελία μου</button>
        <button class="quick-reply-btn" data-msg="Πρότεινέ μου κάτι νέο">✨ Νέες Αφίξεις</button>
    </div>

    <div class="optic-chat-input-area">
        <input type="text" id="optic-chat-input" placeholder="Ρωτήστε με..." />
        <button id="optic-chat-send">➤</button>
    </div>
</div>

<button id="optic-chat-toggle">💬</button>