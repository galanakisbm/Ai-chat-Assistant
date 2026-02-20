<?php
/**
 * Admin panel with tabbed settings interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordAiChat_Admin {

    /** @var array Registered tabs */
    private $tabs = array();

    public function __construct() {
        $this->tabs = array(
            'general'    => __( 'Γενικές Ρυθμίσεις', 'word-ai-chat-assistant' ),
            'ai'         => __( 'AI Ρυθμίσεις', 'word-ai-chat-assistant' ),
            'products'   => __( 'Προϊόντα', 'word-ai-chat-assistant' ),
            'synonyms'   => __( 'Synonyms', 'word-ai-chat-assistant' ),
            'fbt'        => __( 'FBT', 'word-ai-chat-assistant' ),
            'buttons'    => __( 'Quick Buttons', 'word-ai-chat-assistant' ),
            'context'    => __( 'Page Context', 'word-ai-chat-assistant' ),
            'contact'    => __( 'Contact Links', 'word-ai-chat-assistant' ),
            'proactive'  => __( 'Proactive Message', 'word-ai-chat-assistant' ),
            'analytics'  => __( 'Analytics', 'word-ai-chat-assistant' ),
            'history'    => __( 'Chat History', 'word-ai-chat-assistant' ),
        );

        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_word_ai_chat_export_csv', array( $this, 'export_csv' ) );
        add_action( 'wp_ajax_word_ai_chat_test_products', array( $this, 'ajax_test_products' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'AI Chat Assistant', 'word-ai-chat-assistant' ),
            __( 'AI Chat', 'word-ai-chat-assistant' ),
            'manage_options',
            'word-ai-chat-assistant',
            array( $this, 'render_admin_page' ),
            'dashicons-format-chat',
            80
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_word-ai-chat-assistant' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
        wp_add_inline_style( 'wp-admin', $this->get_admin_css() );
    }

    private function get_admin_css() {
        return '
        .word-ai-chat-wrap { max-width: 1000px; }
        .word-ai-chat-tabs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 20px; border-bottom: 2px solid #268CCD; padding-bottom: 0; }
        .word-ai-chat-tabs a { padding: 8px 16px; text-decoration: none; border: 1px solid #ccc; border-bottom: none; border-radius: 4px 4px 0 0; background: #f1f1f1; color: #333; font-size: 13px; }
        .word-ai-chat-tabs a.active, .word-ai-chat-tabs a:hover { background: #268CCD; color: #fff; border-color: #268CCD; }
        .word-ai-chat-tab-content { background: #fff; border: 1px solid #ddd; border-top: none; padding: 24px; border-radius: 0 0 4px 4px; }
        .word-ai-chat-section { margin-bottom: 30px; }
        .word-ai-chat-section h3 { border-bottom: 1px solid #eee; padding-bottom: 8px; color: #268CCD; }
        .form-table th { width: 220px; }
        .word-ai-chat-notice { padding: 10px 14px; border-left: 4px solid #268CCD; background: #f0f8ff; margin: 10px 0; border-radius: 2px; }
        .word-ai-chat-notice.success { border-color: #46b450; background: #f0fff0; }
        .word-ai-chat-notice.error { border-color: #dc3232; background: #fff0f0; }
        .button-group { display: flex; gap: 8px; flex-wrap: wrap; }
        #quick-buttons-list .quick-btn-row { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; }
        #quick-buttons-list .quick-btn-row input, #quick-buttons-list .quick-btn-row select { flex: 1; min-width: 120px; }
        .analytics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .analytics-card { background: linear-gradient(135deg, #268CCD, #1a6ba3); color: #fff; padding: 20px; border-radius: 8px; text-align: center; }
        .analytics-card .number { font-size: 36px; font-weight: 700; display: block; }
        .analytics-card .label { font-size: 13px; opacity: 0.85; }
        .chat-history-item { border: 1px solid #eee; border-radius: 6px; margin-bottom: 12px; overflow: hidden; }
        .chat-history-header { padding: 10px 14px; background: #f9f9f9; cursor: pointer; display: flex; justify-content: space-between; font-weight: 500; }
        .chat-history-body { padding: 14px; display: none; }
        .chat-history-body.open { display: block; }
        .chat-msg { margin-bottom: 8px; padding: 8px 12px; border-radius: 6px; font-size: 13px; }
        .chat-msg.user { background: #e8f4fd; text-align: right; }
        .chat-msg.assistant { background: #f0f0f0; }
        ';
    }

    /**
     * Handle form submissions.
     */
    public function handle_settings_save() {
        if ( ! isset( $_POST['word_ai_chat_nonce'] ) ) {
            return;
        }

        $tab = isset( $_POST['word_ai_chat_tab'] ) ? sanitize_key( $_POST['word_ai_chat_tab'] ) : 'general';

        if ( ! check_admin_referer( 'word_ai_chat_settings_' . $tab, 'word_ai_chat_nonce' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $this->save_tab( $tab );
        wp_safe_redirect( add_query_arg( array( 'page' => 'word-ai-chat-assistant', 'tab' => $tab, 'saved' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Save settings for the given tab.
     *
     * @param string $tab
     */
    private function save_tab( $tab ) {
        $p = $_POST; // phpcs:ignore WordPress.Security.NonceVerification -- nonce already verified above

        switch ( $tab ) {
            case 'general':
                update_option( 'word_ai_chat_title', sanitize_text_field( $p['word_ai_chat_title'] ?? '' ) );
                update_option( 'word_ai_chat_welcome_message', sanitize_textarea_field( $p['word_ai_chat_welcome_message'] ?? '' ) );
                update_option( 'word_ai_chat_position', in_array( $p['word_ai_chat_position'] ?? '', array( 'left', 'right' ), true ) ? $p['word_ai_chat_position'] : 'right' );
                update_option( 'word_ai_chat_offset_bottom', (int) ( $p['word_ai_chat_offset_bottom'] ?? 24 ) );
                update_option( 'word_ai_chat_offset_side', (int) ( $p['word_ai_chat_offset_side'] ?? 24 ) );
                update_option( 'word_ai_chat_auto_open_delay', (int) ( $p['word_ai_chat_auto_open_delay'] ?? 0 ) );
                update_option( 'word_ai_chat_enabled', isset( $p['word_ai_chat_enabled'] ) ? 1 : 0 );
                update_option( 'word_ai_chat_auto_footer', isset( $p['word_ai_chat_auto_footer'] ) ? 1 : 0 );
                update_option( 'word_ai_chat_primary_color', sanitize_hex_color( $p['word_ai_chat_primary_color'] ?? '#268CCD' ) ?: '#268CCD' );
                update_option( 'word_ai_chat_secondary_color', sanitize_hex_color( $p['word_ai_chat_secondary_color'] ?? '#1a6ba3' ) ?: '#1a6ba3' );
                update_option( 'word_ai_chat_button_text_color', sanitize_hex_color( $p['word_ai_chat_button_text_color'] ?? '#ffffff' ) ?: '#ffffff' );
                update_option( 'word_ai_chat_custom_css', wp_strip_all_tags( $p['word_ai_chat_custom_css'] ?? '' ) );
                // Logo
                if ( ! empty( $p['word_ai_chat_chat_logo'] ) ) {
                    update_option( 'word_ai_chat_chat_logo', esc_url_raw( $p['word_ai_chat_chat_logo'] ) );
                }
                break;

            case 'ai':
                if ( ! empty( $p['word_ai_chat_api_key'] ) ) {
                    update_option( 'word_ai_chat_api_key', sanitize_text_field( $p['word_ai_chat_api_key'] ) );
                }
                $allowed_models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'gpt-4-turbo' );
                $model = sanitize_text_field( $p['word_ai_chat_model'] ?? 'gpt-4o-mini' );
                update_option( 'word_ai_chat_model', in_array( $model, $allowed_models, true ) ? $model : 'gpt-4o-mini' );
                $temp = (float) ( $p['word_ai_chat_temperature'] ?? 0.7 );
                update_option( 'word_ai_chat_temperature', min( 2.0, max( 0.0, $temp ) ) );
                update_option( 'word_ai_chat_max_tokens', (int) ( $p['word_ai_chat_max_tokens'] ?? 500 ) );
                update_option( 'word_ai_chat_system_prompt', sanitize_textarea_field( $p['word_ai_chat_system_prompt'] ?? '' ) );
                update_option( 'word_ai_chat_rate_limit_messages', (int) ( $p['word_ai_chat_rate_limit_messages'] ?? 20 ) );
                update_option( 'word_ai_chat_rate_limit_minutes', (int) ( $p['word_ai_chat_rate_limit_minutes'] ?? 10 ) );
                break;

            case 'products':
                $data_source = in_array( $p['word_ai_chat_data_source'] ?? 'xml', array( 'xml', 'woocommerce' ), true )
                    ? $p['word_ai_chat_data_source']
                    : 'xml';
                update_option( 'word_ai_chat_data_source', $data_source );

                // Handle XML upload
                if ( ! empty( $_FILES['word_ai_chat_xml_file']['tmp_name'] ) ) {
                    $this->handle_xml_upload( $_FILES['word_ai_chat_xml_file'] );
                }

                // Field mapping
                if ( isset( $p['word_ai_chat_xml_field_mapping'] ) && is_array( $p['word_ai_chat_xml_field_mapping'] ) ) {
                    $clean_mapping = array_map( 'sanitize_text_field', $p['word_ai_chat_xml_field_mapping'] );
                    update_option( 'word_ai_chat_xml_field_mapping', wp_json_encode( $clean_mapping ) );
                }
                break;

            case 'synonyms':
                update_option( 'word_ai_chat_synonyms_raw', sanitize_textarea_field( $p['word_ai_chat_synonyms_raw'] ?? '' ) );
                break;

            case 'fbt':
                update_option( 'word_ai_chat_fbt_rules', sanitize_textarea_field( $p['word_ai_chat_fbt_rules'] ?? '' ) );
                break;

            case 'buttons':
                $buttons = array();
                if ( isset( $p['btn_label'] ) && is_array( $p['btn_label'] ) ) {
                    foreach ( $p['btn_label'] as $idx => $label ) {
                        $label = sanitize_text_field( $label );
                        if ( empty( $label ) ) {
                            continue;
                        }
                        $type    = in_array( $p['btn_type'][ $idx ] ?? 'chat', array( 'chat', 'link' ), true ) ? $p['btn_type'][ $idx ] : 'chat';
                        $message = sanitize_text_field( $p['btn_message'][ $idx ] ?? '' );
                        $url     = esc_url_raw( $p['btn_url'][ $idx ] ?? '' );
                        $buttons[] = array( 'label' => $label, 'type' => $type, 'message' => $message, 'url' => $url );
                    }
                }
                update_option( 'word_ai_chat_quick_buttons', wp_json_encode( $buttons ) );
                break;

            case 'context':
                update_option( 'word_ai_chat_page_context_enabled', isset( $p['word_ai_chat_page_context_enabled'] ) ? 1 : 0 );
                update_option( 'word_ai_chat_page_context_rules', sanitize_textarea_field( $p['word_ai_chat_page_context_rules'] ?? '' ) );
                break;

            case 'contact':
                $contact_fields = array( 'phone', 'email', 'viber', 'whatsapp', 'messenger', 'instagram', 'facebook' );
                foreach ( $contact_fields as $field ) {
                    update_option( 'word_ai_chat_contact_' . $field, sanitize_text_field( $p[ 'word_ai_chat_contact_' . $field ] ?? '' ) );
                }
                update_option( 'word_ai_chat_hours_mon_fri', sanitize_text_field( $p['word_ai_chat_hours_mon_fri'] ?? '' ) );
                update_option( 'word_ai_chat_hours_sat', sanitize_text_field( $p['word_ai_chat_hours_sat'] ?? '' ) );
                update_option( 'word_ai_chat_hours_sun', sanitize_text_field( $p['word_ai_chat_hours_sun'] ?? '' ) );
                update_option( 'word_ai_chat_timezone', sanitize_text_field( $p['word_ai_chat_timezone'] ?? 'Europe/Athens' ) );
                break;

            case 'proactive':
                update_option( 'word_ai_chat_proactive_enabled', isset( $p['word_ai_chat_proactive_enabled'] ) ? 1 : 0 );
                update_option( 'word_ai_chat_proactive_message', sanitize_textarea_field( $p['word_ai_chat_proactive_message'] ?? '' ) );
                update_option( 'word_ai_chat_proactive_delay', (int) ( $p['word_ai_chat_proactive_delay'] ?? 10 ) );
                update_option( 'word_ai_chat_proactive_times', sanitize_text_field( $p['word_ai_chat_proactive_times'] ?? '1' ) );
                $allowed_pages = array( 'all', 'product_only' );
                $pages = sanitize_text_field( $p['word_ai_chat_proactive_pages'] ?? 'all' );
                update_option( 'word_ai_chat_proactive_pages', in_array( $pages, $allowed_pages, true ) ? $pages : 'all' );
                break;
        }
    }

    /**
     * Handle XML file upload.
     *
     * @param array $file
     */
    private function handle_xml_upload( $file ) {
        if ( UPLOAD_ERR_OK !== $file['error'] ) {
            return;
        }
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( 'xml' !== $ext ) {
            return;
        }
        $upload_dir = WORD_AI_CHAT_PLUGIN_DIR . 'uploads/';
        wp_mkdir_p( $upload_dir );
        $dest = $upload_dir . 'products.xml';
        if ( move_uploaded_file( $file['tmp_name'], $dest ) ) {
            update_option( 'word_ai_chat_xml_path', $dest );
        }
    }

    /**
     * Main admin page renderer.
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! array_key_exists( $current_tab, $this->tabs ) ) {
            $current_tab = 'general';
        }

        $saved = isset( $_GET['saved'] ) && '1' === $_GET['saved']; // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap word-ai-chat-wrap">
            <h1>🤖 <?php esc_html_e( 'Word AI Chat Assistant', 'word-ai-chat-assistant' ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="word-ai-chat-notice success">
                    ✅ <?php esc_html_e( 'Settings saved successfully.', 'word-ai-chat-assistant' ); ?>
                </div>
            <?php endif; ?>

            <div class="word-ai-chat-tabs">
                <?php foreach ( $this->tabs as $slug => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'word-ai-chat-assistant', 'tab' => $slug ), admin_url( 'admin.php' ) ) ); ?>"
                       class="<?php echo $slug === $current_tab ? 'active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="word-ai-chat-tab-content">
                <?php
                switch ( $current_tab ) {
                    case 'general':    $this->tab_general();   break;
                    case 'ai':         $this->tab_ai();         break;
                    case 'products':   $this->tab_products();   break;
                    case 'synonyms':   $this->tab_synonyms();   break;
                    case 'fbt':        $this->tab_fbt();        break;
                    case 'buttons':    $this->tab_buttons();    break;
                    case 'context':    $this->tab_context();    break;
                    case 'contact':    $this->tab_contact();    break;
                    case 'proactive':  $this->tab_proactive();  break;
                    case 'analytics':  $this->tab_analytics();  break;
                    case 'history':    $this->tab_history();    break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Tab renderers
    // -------------------------------------------------------------------------

    private function tab_general() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_general', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="general">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Chat', 'word-ai-chat-assistant' ); ?></th>
                    <td><label><input type="checkbox" name="word_ai_chat_enabled" value="1" <?php checked( get_option( 'word_ai_chat_enabled', 1 ), 1 ); ?>> <?php esc_html_e( 'Show chat widget on frontend', 'word-ai-chat-assistant' ); ?></label></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Auto-add to Footer', 'word-ai-chat-assistant' ); ?></th>
                    <td><label><input type="checkbox" name="word_ai_chat_auto_footer" value="1" <?php checked( get_option( 'word_ai_chat_auto_footer', 1 ), 1 ); ?>> <?php esc_html_e( 'Automatically inject widget in wp_footer', 'word-ai-chat-assistant' ); ?></label></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Chat Title', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_title" value="<?php echo esc_attr( get_option( 'word_ai_chat_title', 'AI Assistant' ) ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Welcome Message', 'word-ai-chat-assistant' ); ?></th>
                    <td><textarea name="word_ai_chat_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'word_ai_chat_welcome_message', '' ) ); ?></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Chat Position', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <select name="word_ai_chat_position">
                            <option value="right" <?php selected( get_option( 'word_ai_chat_position', 'right' ), 'right' ); ?>><?php esc_html_e( 'Right', 'word-ai-chat-assistant' ); ?></option>
                            <option value="left" <?php selected( get_option( 'word_ai_chat_position', 'right' ), 'left' ); ?>><?php esc_html_e( 'Left', 'word-ai-chat-assistant' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Offset Bottom (px)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_offset_bottom" value="<?php echo esc_attr( get_option( 'word_ai_chat_offset_bottom', 24 ) ); ?>" min="0" max="300" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Offset Side (px)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_offset_side" value="<?php echo esc_attr( get_option( 'word_ai_chat_offset_side', 24 ) ); ?>" min="0" max="300" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Auto-open Delay (seconds, 0=off)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_auto_open_delay" value="<?php echo esc_attr( get_option( 'word_ai_chat_auto_open_delay', 0 ) ); ?>" min="0" max="60" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Chat Logo URL', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <input type="text" name="word_ai_chat_chat_logo" id="word_ai_chat_chat_logo" value="<?php echo esc_attr( get_option( 'word_ai_chat_chat_logo', '' ) ); ?>" class="regular-text">
                        <button type="button" class="button" id="word_ai_chat_logo_btn"><?php esc_html_e( 'Choose Image', 'word-ai-chat-assistant' ); ?></button>
                        <script>
                        jQuery(function($){
                            $('#word_ai_chat_logo_btn').on('click', function(){
                                var frame = wp.media({ title: 'Select Logo', multiple: false });
                                frame.on('select', function(){ var att = frame.state().get('selection').first().toJSON(); $('#word_ai_chat_chat_logo').val(att.url); });
                                frame.open();
                            });
                        });
                        </script>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Primary Color', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_primary_color" value="<?php echo esc_attr( get_option( 'word_ai_chat_primary_color', '#268CCD' ) ); ?>" class="word-ai-chat-color-picker"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Secondary Color', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_secondary_color" value="<?php echo esc_attr( get_option( 'word_ai_chat_secondary_color', '#1a6ba3' ) ); ?>" class="word-ai-chat-color-picker"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Button Text Color', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_button_text_color" value="<?php echo esc_attr( get_option( 'word_ai_chat_button_text_color', '#ffffff' ) ); ?>" class="word-ai-chat-color-picker"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Custom CSS', 'word-ai-chat-assistant' ); ?></th>
                    <td><textarea name="word_ai_chat_custom_css" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'word_ai_chat_custom_css', '' ) ); ?></textarea></td>
                </tr>
            </table>
            <script>jQuery(function($){ $('.word-ai-chat-color-picker').wpColorPicker(); });</script>
            <?php submit_button( __( 'Save Settings', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_ai() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_ai', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="ai">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'OpenAI API Key', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <input type="password" name="word_ai_chat_api_key" value="<?php echo esc_attr( get_option( 'word_ai_chat_api_key', '' ) ); ?>" class="regular-text" autocomplete="new-password">
                        <p class="description"><?php esc_html_e( 'Leave blank to keep existing key.', 'word-ai-chat-assistant' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Model', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <select name="word_ai_chat_model">
                            <?php foreach ( array( 'gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'gpt-4-turbo' ) as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( get_option( 'word_ai_chat_model', 'gpt-4o-mini' ), $m ); ?>><?php echo esc_html( $m ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Temperature (0-2)', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <input type="range" name="word_ai_chat_temperature" min="0" max="2" step="0.1" value="<?php echo esc_attr( get_option( 'word_ai_chat_temperature', '0.7' ) ); ?>" oninput="this.nextElementSibling.textContent=this.value">
                        <span><?php echo esc_html( get_option( 'word_ai_chat_temperature', '0.7' ) ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Max Tokens', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_max_tokens" value="<?php echo esc_attr( get_option( 'word_ai_chat_max_tokens', 500 ) ); ?>" min="100" max="4096" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'System Prompt', 'word-ai-chat-assistant' ); ?></th>
                    <td><textarea name="word_ai_chat_system_prompt" rows="8" class="large-text"><?php echo esc_textarea( get_option( 'word_ai_chat_system_prompt', '' ) ); ?></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Rate Limit: Max Messages', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_rate_limit_messages" value="<?php echo esc_attr( get_option( 'word_ai_chat_rate_limit_messages', 20 ) ); ?>" min="1" max="1000" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Rate Limit: Per X Minutes', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_rate_limit_minutes" value="<?php echo esc_attr( get_option( 'word_ai_chat_rate_limit_minutes', 10 ) ); ?>" min="1" max="60" class="small-text"></td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_products() {
        $data_source  = get_option( 'word_ai_chat_data_source', 'xml' );
        $xml_path     = get_option( 'word_ai_chat_xml_path', '' );
        $mapping_json = get_option( 'word_ai_chat_xml_field_mapping', '' );
        $mapping      = $mapping_json ? json_decode( $mapping_json, true ) : array();

        $xml_fields = array();
        if ( $xml_path && file_exists( $xml_path ) ) {
            $xml_loader = new WordAiChat_XmlProducts();
            $xml_fields = $xml_loader->detect_fields( $xml_path );
        }

        $internal_fields = array( 'id', 'name', 'description', 'price', 'url', 'image', 'category', 'availability' );
        ?>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field( 'word_ai_chat_settings_products', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="products">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Data Source', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <select name="word_ai_chat_data_source" id="word_ai_chat_data_source" onchange="document.getElementById('xml-section').style.display=this.value==='xml'?'':'none'">
                            <option value="xml" <?php selected( $data_source, 'xml' ); ?>>XML File</option>
                            <option value="woocommerce" <?php selected( $data_source, 'woocommerce' ); ?>>WooCommerce</option>
                        </select>
                    </td>
                </tr>
            </table>

            <div id="xml-section" style="<?php echo 'xml' === $data_source ? '' : 'display:none'; ?>">
                <h3><?php esc_html_e( 'XML File', 'word-ai-chat-assistant' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Upload XML', 'word-ai-chat-assistant' ); ?></th>
                        <td>
                            <input type="file" name="word_ai_chat_xml_file" accept=".xml">
                            <?php if ( $xml_path ) : ?>
                                <p class="description">✅ <?php echo esc_html( basename( $xml_path ) ); ?> <?php esc_html_e( 'uploaded', 'word-ai-chat-assistant' ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ( ! empty( $xml_fields ) ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Field Mapping', 'word-ai-chat-assistant' ); ?></th>
                        <td>
                            <table class="widefat" style="max-width:500px">
                                <thead><tr><th><?php esc_html_e( 'Internal Field', 'word-ai-chat-assistant' ); ?></th><th><?php esc_html_e( 'XML Field', 'word-ai-chat-assistant' ); ?></th></tr></thead>
                                <tbody>
                                    <?php foreach ( $internal_fields as $field ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( $field ); ?></td>
                                        <td>
                                            <select name="word_ai_chat_xml_field_mapping[<?php echo esc_attr( $field ); ?>]">
                                                <option value=""><?php esc_html_e( '-- none --', 'word-ai-chat-assistant' ); ?></option>
                                                <?php foreach ( $xml_fields as $xf ) : ?>
                                                    <option value="<?php echo esc_attr( $xf ); ?>" <?php selected( $mapping[ $field ] ?? '', $xf ); ?>><?php echo esc_html( $xf ); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>

            <p>
                <button type="button" class="button" id="test-products-btn"><?php esc_html_e( 'Test Product Loading (first 3)', 'word-ai-chat-assistant' ); ?></button>
            </p>
            <div id="test-products-result" style="margin-top:10px;"></div>
            <script>
            jQuery(function($){
                $('#test-products-btn').on('click', function(){
                    $.post(ajaxurl, { action: 'word_ai_chat_test_products', nonce: '<?php echo esc_js( wp_create_nonce( 'word_ai_chat_test_products' ) ); ?>' }, function(r){
                        $('#test-products-result').html('<pre>' + JSON.stringify(r, null, 2) + '</pre>');
                    });
                });
            });
            </script>
            <?php submit_button( __( 'Save Settings', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_synonyms() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_synonyms', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="synonyms">
            <p class="description"><?php esc_html_e( 'One group per line. Words separated by comma or =. Example: τηλέφωνο=κινητό=smartphone', 'word-ai-chat-assistant' ); ?></p>
            <textarea name="word_ai_chat_synonyms_raw" rows="12" class="large-text code" style="font-family:monospace"><?php echo esc_textarea( get_option( 'word_ai_chat_synonyms_raw', '' ) ); ?></textarea>
            <?php submit_button( __( 'Save Synonyms', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_fbt() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_fbt', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="fbt">
            <p class="description"><?php esc_html_e( 'Format: product_id_1:product_id_2,product_id_3 — one rule per line.', 'word-ai-chat-assistant' ); ?></p>
            <textarea name="word_ai_chat_fbt_rules" rows="12" class="large-text code" style="font-family:monospace"><?php echo esc_textarea( get_option( 'word_ai_chat_fbt_rules', '' ) ); ?></textarea>
            <?php submit_button( __( 'Save FBT Rules', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_buttons() {
        $buttons = json_decode( get_option( 'word_ai_chat_quick_buttons', '[]' ), true );
        if ( ! is_array( $buttons ) ) {
            $buttons = array();
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_buttons', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="buttons">
            <div id="quick-buttons-list">
                <?php foreach ( $buttons as $btn ) : ?>
                <div class="quick-btn-row">
                    <input type="text" name="btn_label[]" value="<?php echo esc_attr( $btn['label'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Label', 'word-ai-chat-assistant' ); ?>">
                    <select name="btn_type[]">
                        <option value="chat" <?php selected( $btn['type'] ?? 'chat', 'chat' ); ?>><?php esc_html_e( 'Chat Message', 'word-ai-chat-assistant' ); ?></option>
                        <option value="link" <?php selected( $btn['type'] ?? 'chat', 'link' ); ?>><?php esc_html_e( 'External Link', 'word-ai-chat-assistant' ); ?></option>
                    </select>
                    <input type="text" name="btn_message[]" value="<?php echo esc_attr( $btn['message'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Message (chat type)', 'word-ai-chat-assistant' ); ?>">
                    <input type="url"  name="btn_url[]"     value="<?php echo esc_attr( $btn['url'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'URL (link type)', 'word-ai-chat-assistant' ); ?>">
                    <button type="button" class="button remove-btn-row">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <p>
                <button type="button" class="button" id="add-btn-row">+ <?php esc_html_e( 'Add Button', 'word-ai-chat-assistant' ); ?></button>
            </p>
            <script>
            jQuery(function($){
                $('#add-btn-row').on('click', function(){
                    var row = '<div class="quick-btn-row">'
                        + '<input type="text" name="btn_label[]" placeholder="<?php echo esc_js( __( 'Label', 'word-ai-chat-assistant' ) ); ?>">'
                        + '<select name="btn_type[]"><option value="chat"><?php echo esc_js( __( 'Chat Message', 'word-ai-chat-assistant' ) ); ?></option><option value="link"><?php echo esc_js( __( 'External Link', 'word-ai-chat-assistant' ) ); ?></option></select>'
                        + '<input type="text" name="btn_message[]" placeholder="<?php echo esc_js( __( 'Message', 'word-ai-chat-assistant' ) ); ?>">'
                        + '<input type="url" name="btn_url[]" placeholder="<?php echo esc_js( __( 'URL', 'word-ai-chat-assistant' ) ); ?>">'
                        + '<button type="button" class="button remove-btn-row">✕</button>'
                        + '</div>';
                    $('#quick-buttons-list').append(row);
                });
                $(document).on('click', '.remove-btn-row', function(){ $(this).closest('.quick-btn-row').remove(); });
            });
            </script>
            <?php submit_button( __( 'Save Buttons', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_context() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_context', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="context">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Page Context', 'word-ai-chat-assistant' ); ?></th>
                    <td><label><input type="checkbox" name="word_ai_chat_page_context_enabled" value="1" <?php checked( get_option( 'word_ai_chat_page_context_enabled', 1 ), 1 ); ?>> <?php esc_html_e( 'Send current page info to AI', 'word-ai-chat-assistant' ); ?></label></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Custom Context Rules', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <textarea name="word_ai_chat_page_context_rules" rows="8" class="large-text"><?php echo esc_textarea( get_option( 'word_ai_chat_page_context_rules', '' ) ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Optional: one rule per line in format: URL_pattern|context_text', 'word-ai-chat-assistant' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_contact() {
        $fields = array(
            'phone'     => array( 'label' => __( 'Phone', 'word-ai-chat-assistant' ), 'placeholder' => '+30 698 000 0000' ),
            'email'     => array( 'label' => __( 'Email', 'word-ai-chat-assistant' ), 'placeholder' => 'support@example.com' ),
            'whatsapp'  => array( 'label' => __( 'WhatsApp', 'word-ai-chat-assistant' ), 'placeholder' => '+30698...' ),
            'viber'     => array( 'label' => __( 'Viber', 'word-ai-chat-assistant' ), 'placeholder' => '+30698...' ),
            'messenger' => array( 'label' => __( 'Messenger', 'word-ai-chat-assistant' ), 'placeholder' => 'pagename' ),
            'instagram' => array( 'label' => __( 'Instagram', 'word-ai-chat-assistant' ), 'placeholder' => 'username' ),
            'facebook'  => array( 'label' => __( 'Facebook', 'word-ai-chat-assistant' ), 'placeholder' => 'pagename' ),
        );
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_contact', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="contact">
            <table class="form-table">
                <?php foreach ( $fields as $key => $info ) : ?>
                <tr>
                    <th><?php echo esc_html( $info['label'] ); ?></th>
                    <td><input type="text" name="word_ai_chat_contact_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( 'word_ai_chat_contact_' . $key, '' ) ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $info['placeholder'] ); ?>"></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <th><?php esc_html_e( 'Hours (Mon–Fri)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_hours_mon_fri" value="<?php echo esc_attr( get_option( 'word_ai_chat_hours_mon_fri', '' ) ); ?>" class="regular-text" placeholder="09:00-17:00"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Hours (Saturday)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_hours_sat" value="<?php echo esc_attr( get_option( 'word_ai_chat_hours_sat', '' ) ); ?>" class="regular-text" placeholder="09:00-14:00"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Hours (Sunday)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_hours_sun" value="<?php echo esc_attr( get_option( 'word_ai_chat_hours_sun', '' ) ); ?>" class="regular-text" placeholder="Closed"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Timezone', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="text" name="word_ai_chat_timezone" value="<?php echo esc_attr( get_option( 'word_ai_chat_timezone', 'Europe/Athens' ) ); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_proactive() {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'word_ai_chat_settings_proactive', 'word_ai_chat_nonce' ); ?>
            <input type="hidden" name="word_ai_chat_tab" value="proactive">
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Enable Proactive Message', 'word-ai-chat-assistant' ); ?></th>
                    <td><label><input type="checkbox" name="word_ai_chat_proactive_enabled" value="1" <?php checked( get_option( 'word_ai_chat_proactive_enabled', 0 ), 1 ); ?>></label></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Message', 'word-ai-chat-assistant' ); ?></th>
                    <td><textarea name="word_ai_chat_proactive_message" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'word_ai_chat_proactive_message', '' ) ); ?></textarea></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Delay (seconds)', 'word-ai-chat-assistant' ); ?></th>
                    <td><input type="number" name="word_ai_chat_proactive_delay" value="<?php echo esc_attr( get_option( 'word_ai_chat_proactive_delay', 10 ) ); ?>" min="1" max="120" class="small-text"></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Show Times', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <select name="word_ai_chat_proactive_times">
                            <option value="1" <?php selected( get_option( 'word_ai_chat_proactive_times', '1' ), '1' ); ?>><?php esc_html_e( 'Once per session', 'word-ai-chat-assistant' ); ?></option>
                            <option value="always" <?php selected( get_option( 'word_ai_chat_proactive_times', '1' ), 'always' ); ?>><?php esc_html_e( 'Always', 'word-ai-chat-assistant' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Show On Pages', 'word-ai-chat-assistant' ); ?></th>
                    <td>
                        <select name="word_ai_chat_proactive_pages">
                            <option value="all" <?php selected( get_option( 'word_ai_chat_proactive_pages', 'all' ), 'all' ); ?>><?php esc_html_e( 'All pages', 'word-ai-chat-assistant' ); ?></option>
                            <option value="product_only" <?php selected( get_option( 'word_ai_chat_proactive_pages', 'all' ), 'product_only' ); ?>><?php esc_html_e( 'Product pages only', 'word-ai-chat-assistant' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Settings', 'word-ai-chat-assistant' ) ); ?>
        </form>
        <?php
    }

    private function tab_analytics() {
        global $wpdb;

        $stats = $this->get_analytics_stats();
        ?>
        <div class="analytics-grid">
            <div class="analytics-card">
                <span class="number"><?php echo esc_html( $stats['total_conversations'] ); ?></span>
                <span class="label"><?php esc_html_e( 'Total Conversations', 'word-ai-chat-assistant' ); ?></span>
            </div>
            <div class="analytics-card">
                <span class="number"><?php echo esc_html( $stats['total_messages'] ); ?></span>
                <span class="label"><?php esc_html_e( 'Total Messages', 'word-ai-chat-assistant' ); ?></span>
            </div>
            <div class="analytics-card">
                <span class="number"><?php echo esc_html( $stats['avg_response_ms'] ); ?> ms</span>
                <span class="label"><?php esc_html_e( 'Avg Response Time', 'word-ai-chat-assistant' ); ?></span>
            </div>
        </div>

        <p>
            <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'word_ai_chat_export_csv', '_wpnonce' => wp_create_nonce( 'word_ai_chat_export_csv' ) ), admin_url( 'admin-ajax.php' ) ) ); ?>" class="button button-secondary">
                📥 <?php esc_html_e( 'Export CSV', 'word-ai-chat-assistant' ); ?>
            </a>
        </p>

        <h3><?php esc_html_e( 'Recent Conversations', 'word-ai-chat-assistant' ); ?></h3>
        <?php
        $conversations = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT * FROM {$wpdb->prefix}optic_chat_conversations ORDER BY started_at DESC LIMIT 20",
            ARRAY_A
        );
        if ( $conversations ) :
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th>#</th>
                    <th><?php esc_html_e( 'IP', 'word-ai-chat-assistant' ); ?></th>
                    <th><?php esc_html_e( 'Page', 'word-ai-chat-assistant' ); ?></th>
                    <th><?php esc_html_e( 'Messages', 'word-ai-chat-assistant' ); ?></th>
                    <th><?php esc_html_e( 'Started', 'word-ai-chat-assistant' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $conversations as $c ) : ?>
                <tr>
                    <td><?php echo esc_html( $c['id'] ); ?></td>
                    <td><?php echo esc_html( $c['ip_address'] ); ?></td>
                    <td><a href="<?php echo esc_url( $c['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( mb_substr( $c['page_url'], 0, 60 ) ); ?></a></td>
                    <td><?php echo esc_html( $c['message_count'] ); ?></td>
                    <td><?php echo esc_html( $c['started_at'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p><?php esc_html_e( 'No conversations yet.', 'word-ai-chat-assistant' ); ?></p>
        <?php endif; ?>
        <?php
    }

    private function tab_history() {
        global $wpdb;

        $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
        $conversations = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT * FROM {$wpdb->prefix}optic_chat_conversations ORDER BY started_at DESC LIMIT 50",
            ARRAY_A
        );
        ?>
        <p>
            <input type="text" id="history-search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'word-ai-chat-assistant' ); ?>" class="regular-text">
        </p>
        <?php
        if ( $conversations ) :
            foreach ( $conversations as $c ) :
                $messages = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                    $wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}optic_chat_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                        $c['id']
                    ),
                    ARRAY_A
                );
                ?>
                <div class="chat-history-item">
                    <div class="chat-history-header" onclick="var b=this.nextElementSibling;b.classList.toggle('open')">
                        <span>#<?php echo esc_html( $c['id'] ); ?> — <?php echo esc_html( $c['started_at'] ); ?> (<?php echo esc_html( $c['message_count'] ); ?> msgs)</span>
                        <span><?php echo esc_html( $c['ip_address'] ); ?></span>
                    </div>
                    <div class="chat-history-body">
                        <?php foreach ( $messages as $m ) : ?>
                            <div class="chat-msg <?php echo esc_attr( $m['role'] ); ?>">
                                <strong><?php echo esc_html( $m['role'] ); ?>:</strong> <?php echo esc_html( $m['content'] ); ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ( empty( $messages ) ) : ?>
                            <em><?php esc_html_e( 'No messages', 'word-ai-chat-assistant' ); ?></em>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            endforeach;
        else :
            echo '<p>' . esc_html__( 'No chat history found.', 'word-ai-chat-assistant' ) . '</p>';
        endif;
    }

    // -------------------------------------------------------------------------
    // Analytics helpers
    // -------------------------------------------------------------------------

    private function get_analytics_stats() {
        global $wpdb;

        $total_conversations = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}optic_chat_conversations" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $total_messages      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}optic_chat_messages" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $avg_response_ms     = (int) $wpdb->get_var( "SELECT AVG(response_time_ms) FROM {$wpdb->prefix}optic_chat_messages WHERE role='assistant' AND response_time_ms IS NOT NULL" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        return array(
            'total_conversations' => $total_conversations,
            'total_messages'      => $total_messages,
            'avg_response_ms'     => $avg_response_ms,
        );
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    public function export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Forbidden', 'word-ai-chat-assistant' ), 403 );
        }
        check_admin_referer( 'word_ai_chat_export_csv' );

        global $wpdb;

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            "SELECT c.id, c.session_id, c.ip_address, c.page_url, c.started_at, c.message_count, m.role, m.content, m.created_at, m.response_time_ms
             FROM {$wpdb->prefix}optic_chat_conversations c
             LEFT JOIN {$wpdb->prefix}optic_chat_messages m ON m.conversation_id = c.id
             ORDER BY c.started_at DESC, m.created_at ASC",
            ARRAY_A
        );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="chat-history-' . gmdate( 'Y-m-d' ) . '.csv"' );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM
        fputcsv( $out, array( 'conv_id', 'session', 'ip', 'page_url', 'started_at', 'msg_count', 'role', 'content', 'msg_time', 'response_ms' ) );
        foreach ( $rows as $row ) {
            fputcsv( $out, array(
                $row['id'], $row['session_id'], $row['ip_address'], $row['page_url'],
                $row['started_at'], $row['message_count'], $row['role'], $row['content'],
                $row['created_at'], $row['response_time_ms'],
            ) );
        }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        exit;
    }

    public function ajax_test_products() {
        check_ajax_referer( 'word_ai_chat_test_products', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Forbidden', 'word-ai-chat-assistant' ) );
        }
        $ds       = new WordAiChat_DataSource();
        $products = $ds->get_products( 3 );
        wp_send_json( $products );
    }
}
