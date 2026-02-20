<?php
/**
 * Plugin Name: Word AI Chat Assistant
 * Plugin URI:  https://opticweb.gr
 * Description: AI-powered chat assistant for WordPress & WooCommerce. Supports product search, order help, coupons, and more. Powered by OpenAI.
 * Version:     1.0.0
 * Author:      OpticWeb
 * Author URI:  https://opticweb.gr
 * Text Domain: word-ai-chat-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WORD_AI_CHAT_VERSION', '1.0.0' );
define( 'WORD_AI_CHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORD_AI_CHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WORD_AI_CHAT_PLUGIN_FILE', __FILE__ );

// Include required files
require_once WORD_AI_CHAT_PLUGIN_DIR . 'includes/class-xml-products.php';
require_once WORD_AI_CHAT_PLUGIN_DIR . 'includes/class-woocommerce.php';
require_once WORD_AI_CHAT_PLUGIN_DIR . 'includes/class-data-source.php';
require_once WORD_AI_CHAT_PLUGIN_DIR . 'includes/class-ajax.php';
require_once WORD_AI_CHAT_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Plugin activation: create DB tables and set defaults.
 */
function word_ai_chat_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql_conversations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}optic_chat_conversations (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        ip_address varchar(45),
        page_url varchar(500),
        started_at datetime NOT NULL,
        last_message_at datetime,
        message_count int(11) DEFAULT 0,
        PRIMARY KEY (id),
        KEY session_id (session_id)
    ) $charset_collate;";

    $sql_messages = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}optic_chat_messages (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        conversation_id bigint(20) UNSIGNED NOT NULL,
        role enum('user','assistant') NOT NULL,
        content text NOT NULL,
        created_at datetime NOT NULL,
        response_time_ms int(11),
        PRIMARY KEY (id),
        KEY conversation_id (conversation_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_conversations );
    dbDelta( $sql_messages );

    // Create uploads directory
    $upload_dir = WORD_AI_CHAT_PLUGIN_DIR . 'uploads/';
    if ( ! file_exists( $upload_dir ) ) {
        wp_mkdir_p( $upload_dir );
    }

    // Set default options
    $defaults = array(
        'word_ai_chat_title'              => 'AI Assistant',
        'word_ai_chat_welcome_message'    => 'Γεια σας! Είμαι ο ψηφιακός βοηθός. Πώς μπορώ να βοηθήσω; 😊',
        'word_ai_chat_system_prompt'      => 'Είσαι ένας ευγενικός βοηθός για το κατάστημά μας. Απάντησε σύντομα και στα Ελληνικά.',
        'word_ai_chat_position'           => 'right',
        'word_ai_chat_offset_bottom'      => 24,
        'word_ai_chat_offset_side'        => 24,
        'word_ai_chat_auto_open_delay'    => 0,
        'word_ai_chat_enabled'            => 1,
        'word_ai_chat_auto_footer'        => 1,
        'word_ai_chat_model'              => 'gpt-4o-mini',
        'word_ai_chat_temperature'        => '0.7',
        'word_ai_chat_max_tokens'         => 500,
        'word_ai_chat_data_source'        => 'xml',
        'word_ai_chat_primary_color'      => '#268CCD',
        'word_ai_chat_secondary_color'    => '#1a6ba3',
        'word_ai_chat_button_text_color'  => '#ffffff',
        'word_ai_chat_synonyms_raw'       => '',
        'word_ai_chat_fbt_rules'          => '',
        'word_ai_chat_quick_buttons'      => '[]',
        'word_ai_chat_page_context_enabled' => 1,
        'word_ai_chat_proactive_enabled'  => 0,
        'word_ai_chat_proactive_delay'    => 10,
        'word_ai_chat_proactive_message'  => 'Γεια σας! Μπορώ να σας βοηθήσω; 😊',
        'word_ai_chat_proactive_times'    => '1',
        'word_ai_chat_proactive_pages'    => 'all',
        'word_ai_chat_rate_limit_messages' => 20,
        'word_ai_chat_rate_limit_minutes'  => 10,
        'word_ai_chat_xml_field_mapping'  => wp_json_encode( array(
            'id'          => 'id',
            'name'        => 'name',
            'description' => 'description',
            'price'       => 'price',
            'url'         => 'url',
            'image'       => 'image',
            'category'    => 'categories',
            'availability' => 'availability',
        ) ),
    );

    foreach ( $defaults as $key => $value ) {
        if ( false === get_option( $key ) ) {
            update_option( $key, $value );
        }
    }
}
register_activation_hook( __FILE__, 'word_ai_chat_activate' );

/**
 * Plugin deactivation hook (no-op; uninstall.php handles cleanup).
 */
function word_ai_chat_deactivate() {
    // Nothing to do on deactivation
}
register_deactivation_hook( __FILE__, 'word_ai_chat_deactivate' );

/**
 * Bootstrap the plugin.
 */
function word_ai_chat_init() {
    // Load text domain
    load_plugin_textdomain( 'word-ai-chat-assistant', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Initialize admin
    if ( is_admin() ) {
        new WordAiChat_Admin();
    }

    // Initialize AJAX handler (always, for both logged-in and guest users)
    new WordAiChat_Ajax();

    // Enqueue frontend assets and register shortcode
    add_action( 'wp_enqueue_scripts', 'word_ai_chat_enqueue_assets' );
    add_shortcode( 'word_ai_chat', 'word_ai_chat_shortcode' );

    // Auto-add to footer if enabled
    if ( get_option( 'word_ai_chat_auto_footer', 1 ) && get_option( 'word_ai_chat_enabled', 1 ) ) {
        add_action( 'wp_footer', 'word_ai_chat_render_widget' );
    }
}
add_action( 'plugins_loaded', 'word_ai_chat_init' );

/**
 * Enqueue CSS and JS assets on the frontend.
 */
function word_ai_chat_enqueue_assets() {
    if ( ! get_option( 'word_ai_chat_enabled', 1 ) ) {
        return;
    }

    wp_enqueue_style(
        'word-ai-chat',
        WORD_AI_CHAT_PLUGIN_URL . 'assets/css/chat.css',
        array(),
        WORD_AI_CHAT_VERSION
    );

    wp_enqueue_script(
        'word-ai-chat',
        WORD_AI_CHAT_PLUGIN_URL . 'assets/js/chat.js',
        array(),
        WORD_AI_CHAT_VERSION,
        true
    );

    // Build contact links for JS
    $contact_links = array();
    $contact_mappings = array(
        'phone'     => array( 'label' => 'Τηλέφωνο', 'icon' => '📞', 'prefix' => 'tel:',                   'clean' => true ),
        'email'     => array( 'label' => 'Email',     'icon' => '✉️', 'prefix' => 'mailto:',                 'clean' => false ),
        'viber'     => array( 'label' => 'Viber',     'icon' => '💬', 'prefix' => 'viber://chat?number=',    'clean' => true ),
        'whatsapp'  => array( 'label' => 'WhatsApp',  'icon' => '💬', 'prefix' => 'https://wa.me/',          'clean' => true ),
        'messenger' => array( 'label' => 'Messenger', 'icon' => '💬', 'prefix' => 'https://m.me/',           'clean' => false ),
        'instagram' => array( 'label' => 'Instagram', 'icon' => '📸', 'prefix' => 'https://instagram.com/',  'clean' => false ),
        'facebook'  => array( 'label' => 'Facebook',  'icon' => '👍', 'prefix' => 'https://facebook.com/',   'clean' => false ),
    );
    foreach ( $contact_mappings as $type => $cfg ) {
        $val = get_option( 'word_ai_chat_contact_' . $type, '' );
        if ( ! empty( $val ) ) {
            if ( strpos( $val, 'http' ) === 0 ) {
                $url = $val;
            } else {
                $clean = $cfg['clean'] ? preg_replace( '/[^0-9+]/', '', $val ) : $val;
                $url   = $cfg['prefix'] . $clean;
            }
            $contact_links[] = array(
                'type'  => $type,
                'label' => $cfg['label'],
                'icon'  => $cfg['icon'],
                'url'   => $url,
            );
        }
    }

    $quick_buttons = json_decode( get_option( 'word_ai_chat_quick_buttons', '[]' ), true );
    if ( ! is_array( $quick_buttons ) ) {
        $quick_buttons = array();
    }

    wp_localize_script(
        'word-ai-chat',
        'optic_chat_vars',
        array(
            'ajax_url'        => admin_url( 'admin-ajax.php' ),
            'nonce'           => wp_create_nonce( 'optic_chat_nonce' ),
            'shop_domain'     => parse_url( home_url(), PHP_URL_HOST ) ?? '',
            'welcome_message' => get_option( 'word_ai_chat_welcome_message', 'Γεια σας! Πώς μπορώ να βοηθήσω; 😊' ),
            'quick_buttons'   => $quick_buttons,
            'contact_links'   => $contact_links,
            'proactive'       => array(
                'enabled' => (bool) get_option( 'word_ai_chat_proactive_enabled', 0 ),
                'delay'   => (int) get_option( 'word_ai_chat_proactive_delay', 10 ),
                'times'   => get_option( 'word_ai_chat_proactive_times', '1' ),
                'message' => get_option( 'word_ai_chat_proactive_message', '' ),
                'pages'   => get_option( 'word_ai_chat_proactive_pages', 'all' ),
                'is_product_page' => is_singular( 'product' ),
            ),
            'handoff'         => array(
                'phone'    => get_option( 'word_ai_chat_contact_phone', '' ),
                'whatsapp' => get_option( 'word_ai_chat_contact_whatsapp', '' ),
                'viber'    => get_option( 'word_ai_chat_contact_viber', '' ),
                'email'    => get_option( 'word_ai_chat_contact_email', '' ),
            ),
        )
    );

    // Inject CSS variables for colors
    $primary   = get_option( 'word_ai_chat_primary_color', '#268CCD' );
    $secondary = get_option( 'word_ai_chat_secondary_color', '#1a6ba3' );
    $btn_text  = get_option( 'word_ai_chat_button_text_color', '#ffffff' );
    $color_pattern = '/^#[0-9A-Fa-f]{6}$/';
    if ( ! preg_match( $color_pattern, $primary ) )   { $primary   = '#268CCD'; }
    if ( ! preg_match( $color_pattern, $secondary ) )  { $secondary = '#1a6ba3'; }
    if ( ! preg_match( $color_pattern, $btn_text ) )   { $btn_text  = '#ffffff'; }

    $inline_css = ':root{--optic-chat-primary:' . esc_attr( $primary ) . ';--optic-chat-secondary:' . esc_attr( $secondary ) . ';--optic-chat-button-text:' . esc_attr( $btn_text ) . ';}';
    wp_add_inline_style( 'word-ai-chat', $inline_css );

    // Custom CSS
    $custom_css = get_option( 'word_ai_chat_custom_css', '' );
    if ( ! empty( $custom_css ) ) {
        wp_add_inline_style( 'word-ai-chat', wp_strip_all_tags( $custom_css ) );
    }
}

/**
 * Render the chat widget HTML.
 */
function word_ai_chat_render_widget() {
    if ( ! get_option( 'word_ai_chat_enabled', 1 ) ) {
        return;
    }
    include WORD_AI_CHAT_PLUGIN_DIR . 'templates/chat-widget.php';
}

/**
 * Shortcode [word_ai_chat]
 */
function word_ai_chat_shortcode( $atts ) {
    if ( ! get_option( 'word_ai_chat_enabled', 1 ) ) {
        return '';
    }
    ob_start();
    include WORD_AI_CHAT_PLUGIN_DIR . 'templates/chat-widget.php';
    return ob_get_clean();
}
