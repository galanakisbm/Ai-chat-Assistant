<?php
/**
 * Chat widget template.
 * Rendered via the [word_ai_chat] shortcode or the wp_footer hook.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$chat_title     = esc_html( get_option( 'word_ai_chat_title', 'AI Assistant' ) );
$chat_logo      = esc_url( get_option( 'word_ai_chat_chat_logo', '' ) );
$chat_position  = get_option( 'word_ai_chat_position', 'right' );
$offset_bottom  = max( 0, (int) get_option( 'word_ai_chat_offset_bottom', 24 ) );
$offset_side    = max( 0, (int) get_option( 'word_ai_chat_offset_side', 24 ) );
$container_bottom = $offset_bottom + 80;
$quick_buttons  = json_decode( get_option( 'word_ai_chat_quick_buttons', '[]' ), true );
if ( ! is_array( $quick_buttons ) ) {
    $quick_buttons = array();
}

// Build inline position style
$toggle_style = 'bottom:' . $offset_bottom . 'px;';
$container_style = 'bottom:' . $container_bottom . 'px;';
if ( 'left' === $chat_position ) {
    $toggle_style    .= 'left:' . $offset_side . 'px;right:auto;';
    $container_style .= 'left:' . $offset_side . 'px;right:auto;';
} else {
    $toggle_style    .= 'right:' . $offset_side . 'px;left:auto;';
    $container_style .= 'right:' . $offset_side . 'px;left:auto;';
}
?>
<div id="optic-chat-container" class="optic-chat-closed" style="<?php echo esc_attr( $container_style ); ?>">
    <div class="optic-chat-header">
        <div class="header-title">
            <?php if ( $chat_logo ) : ?>
                <img src="<?php echo $chat_logo; // already escaped above ?>" alt="<?php echo $chat_title; ?>" class="optic-chat-logo">
            <?php endif; ?>
            <span><?php echo $chat_title; ?></span>
        </div>
        <button id="optic-chat-clear" aria-label="Clear chat" title="<?php esc_attr_e( 'Clear History', 'word-ai-chat-assistant' ); ?>">🗑️</button>
        <button id="optic-chat-close" aria-label="<?php esc_attr_e( 'Close', 'word-ai-chat-assistant' ); ?>">✕</button>
    </div>

    <div id="optic-chat-messages"></div>

    <?php if ( ! empty( $quick_buttons ) ) : ?>
    <div id="optic-chat-suggestions">
        <?php foreach ( $quick_buttons as $btn ) : ?>
            <?php if ( 'link' === ( $btn['type'] ?? 'chat' ) ) : ?>
                <a href="<?php echo esc_url( $btn['url'] ?? '#' ); ?>"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="quick-reply-btn quick-reply-link">
                    <?php echo esc_html( $btn['label'] ?? '' ); ?>
                </a>
            <?php else : ?>
                <button class="quick-reply-btn quick-reply-chat"
                        data-msg="<?php echo esc_attr( $btn['message'] ?? $btn['label'] ?? '' ); ?>">
                    <?php echo esc_html( $btn['label'] ?? '' ); ?>
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="optic-chat-input-area">
        <input type="text" id="optic-chat-input" placeholder="<?php esc_attr_e( 'Ask me…', 'word-ai-chat-assistant' ); ?>" />
        <button id="optic-chat-send">➤</button>
    </div>

    <div class="optic-chat-footer">
        <div class="optic-branding">
            <span class="optic-branding-powered">
                <?php esc_html_e( 'Powered by', 'word-ai-chat-assistant' ); ?>
                <a href="https://opticweb.gr" target="_blank" rel="noopener noreferrer" class="optic-branding-link">OpticWeb</a>
            </span>
            <span class="optic-branding-contact">
                📞 <a href="tel:00306983623929" class="optic-branding-contact-link">+30 698 362 3929</a> |
                ✉️ <a href="mailto:support@opticweb.gr" class="optic-branding-contact-link">support@opticweb.gr</a>
            </span>
        </div>
    </div>
</div>

<button id="optic-chat-toggle" style="<?php echo esc_attr( $toggle_style ); ?>">💬</button>
