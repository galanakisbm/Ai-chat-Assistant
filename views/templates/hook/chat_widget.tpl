{if isset($optic_custom_css)}
    {$optic_custom_css nofilter}
{/if}

<style>
#optic-chat-toggle {
    bottom: {$offset_bottom|intval}px !important;
    {if $chat_position == 'left'}
    left: {$offset_side|intval}px !important;
    right: auto !important;
    {else}
    right: {$offset_side|intval}px !important;
    left: auto !important;
    {/if}
}
#optic-chat-container {
    bottom: {$container_bottom|intval}px !important;
    {if $chat_position == 'left'}
    left: {$offset_side|intval}px !important;
    right: auto !important;
    {else}
    right: {$offset_side|intval}px !important;
    left: auto !important;
    {/if}
}
</style>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<div id="optic-chat-container" class="optic-chat-closed">
    <div class="optic-chat-header">
        <div class="header-title">
            {if $chat_logo}
                <img src="{$chat_logo|escape:'html':'UTF-8'}" alt="{$chat_title|escape:'html':'UTF-8'}" class="optic-chat-logo">
            {/if}
            <span>{$chat_title|escape:'html':'UTF-8'}</span>
        </div>
        <button id="optic-chat-close" aria-label="Close">✕</button>
    </div>

    <div id="optic-chat-messages"></div>

    {if isset($quick_buttons) && $quick_buttons|count > 0}
    <div id="optic-chat-suggestions">
        {foreach from=$quick_buttons item=btn}
            {if $btn.type == 'link'}
                <a href="{$btn.url|escape:'html':'UTF-8'}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="quick-reply-btn quick-reply-link">
                    {$btn.label|escape:'html':'UTF-8'}
                </a>
            {else}
                <button class="quick-reply-btn quick-reply-chat"
                        data-msg="{$btn.message|escape:'html':'UTF-8'}">
                    {$btn.label|escape:'html':'UTF-8'}
                </button>
            {/if}
        {/foreach}
    </div>
    {/if}

    <div class="optic-chat-input-area">
        <input type="text" id="optic-chat-input" placeholder="Ρωτήστε με..." />
        <button id="optic-chat-send">➤</button>
    </div>

    <div class="optic-chat-footer">
        <div class="optic-branding">
            <span class="optic-branding-powered">
                Powered by <a href="https://opticweb.gr" target="_blank" rel="noopener noreferrer" class="optic-branding-link">OpticWeb</a>
            </span>
            <span class="optic-branding-contact">
                📞 <a href="tel:00306983623929" class="optic-branding-contact-link">+30 698 362 3929</a> | 
                ✉️ <a href="mailto:support@opticweb.gr" class="optic-branding-contact-link">support@opticweb.gr</a>
            </span>
        </div>
    </div>
</div>

<button id="optic-chat-toggle">
    {if $chat_icon}
        <img src="{$chat_icon|escape:'html':'UTF-8'}" alt="Chat" style="width: 24px; height: 24px;">
    {else}
        💬
    {/if}
</button>