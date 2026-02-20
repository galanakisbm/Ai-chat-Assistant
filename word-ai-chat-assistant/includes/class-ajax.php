<?php
/**
 * AJAX handler for the chat widget.
 * Handles wp_ajax_optic_chat_message and wp_ajax_nopriv_optic_chat_message.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WordAiChat_Ajax {

    public function __construct() {
        add_action( 'wp_ajax_optic_chat_message',        array( $this, 'handle_message' ) );
        add_action( 'wp_ajax_nopriv_optic_chat_message', array( $this, 'handle_message' ) );
    }

    /**
     * Main AJAX handler.
     */
    public function handle_message() {
        // Verify nonce
        if ( ! check_ajax_referer( 'optic_chat_nonce', 'nonce', false ) ) {
            wp_send_json( array( 'status' => 'error', 'reply' => 'Security check failed.' ), 403 );
        }

        $api_key = get_option( 'word_ai_chat_api_key', '' );
        if ( empty( $api_key ) ) {
            wp_send_json( array( 'status' => 'error', 'reply' => 'Configuration Error: API key missing.' ), 500 );
        }

        $user_message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
        if ( empty( $user_message ) ) {
            wp_send_json( array( 'status' => 'error', 'reply' => 'Empty message.' ), 400 );
        }

        // Rate limiting
        if ( ! $this->check_rate_limit() ) {
            wp_send_json( array( 'status' => 'error', 'reply' => 'Πολλά μηνύματα. Παρακαλώ περιμένετε λίγο.' ), 429 );
        }

        $history_json      = isset( $_POST['history'] ) ? wp_unslash( $_POST['history'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $page_context_json = isset( $_POST['page_context'] ) ? wp_unslash( $_POST['page_context'] ) : '{}'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

        $start_time = microtime( true );

        $result = $this->call_openai( $user_message, $history_json, $page_context_json, $api_key );

        $response_time_ms = (int) round( ( microtime( true ) - $start_time ) * 1000 );

        $response_text = is_array( $result ) ? ( isset( $result['text'] ) ? $result['text'] : '' ) : $result;
        $is_comparison = is_array( $result ) ? ( ! empty( $result['is_comparison'] ) ) : false;

        // Save to DB
        $this->save_conversation( $user_message, $response_text, $page_context_json, $response_time_ms );

        // Parse into structured response
        $parsed = $this->parse_ai_response( $response_text, $is_comparison );

        wp_send_json( array( 'status' => 'success', 'reply' => $parsed ) );
    }

    /**
     * Rate limiting: X messages per Y minutes per IP.
     *
     * @return bool True if allowed, false if rate-limited.
     */
    private function check_rate_limit() {
        $max_messages = (int) get_option( 'word_ai_chat_rate_limit_messages', 20 );
        $minutes      = (int) get_option( 'word_ai_chat_rate_limit_minutes', 10 );

        $ip        = $this->get_client_ip();
        $cache_key = 'word_ai_chat_rl_' . md5( $ip );
        $count     = (int) get_transient( $cache_key );

        if ( $count >= $max_messages ) {
            return false;
        }

        set_transient( $cache_key, $count + 1, $minutes * MINUTE_IN_SECONDS );
        return true;
    }

    /**
     * Get client IP address.
     *
     * @return string
     */
    private function get_client_ip() {
        $keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Take first IP if comma-separated
                $ip = explode( ',', $ip )[0];
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    /**
     * Build system prompt and call OpenAI API.
     *
     * @param string $user_message
     * @param string $history_json
     * @param string $page_context_json
     * @param string $api_key
     * @return string|array
     */
    private function call_openai( $user_message, $history_json, $page_context_json, $api_key ) {
        $data_source = new WordAiChat_DataSource();

        // Build products context
        $products_context = $data_source->build_products_context( 150 );
        $coupons_context  = $data_source->build_coupons_context();

        // Build page context
        $page_context_str = '';
        if ( get_option( 'word_ai_chat_page_context_enabled', 1 ) && ! empty( $page_context_json ) ) {
            $page_data = json_decode( $page_context_json, true );
            if ( is_array( $page_data ) ) {
                $page_context_str  = "\n\n=== CURRENT PAGE ===\n";
                $page_context_str .= 'Type: ' . ( isset( $page_data['type'] ) ? sanitize_text_field( $page_data['type'] ) : 'unknown' ) . "\n";
                $page_context_str .= 'URL: ' . ( isset( $page_data['url'] ) ? esc_url_raw( $page_data['url'] ) : '' ) . "\n";
                $page_context_str .= 'Title: ' . ( isset( $page_data['title'] ) ? sanitize_text_field( $page_data['title'] ) : '' ) . "\n";
                if ( isset( $page_data['productName'] ) ) {
                    $page_context_str .= 'Product: ' . sanitize_text_field( $page_data['productName'] ) . "\n";
                }
                $page_context_str .= "=== END PAGE ===\n";
            }
        }

        // Build synonyms context
        $synonyms_context = $this->build_synonyms_context();

        // Build FBT context
        $fbt_context = $this->build_fbt_context();

        // Build contact context
        $contact_context = $this->build_contact_context();

        // Compose system prompt
        $base_prompt = get_option( 'word_ai_chat_system_prompt', 'Είσαι ένας ευγενικός βοηθός για το κατάστημά μας. Απάντησε σύντομα.' );

        $product_format_instruction = "\n\nΌταν αναφέρεις προϊόντα, χρησιμοποίησε ΑΚΡΙΒΩΣ αυτή τη μορφή για κάθε προϊόν:\n[PRODUCT:ID|NAME|PRICE|IMAGE_URL|PRODUCT_URL]\n\nΠαράδειγμα:\n[PRODUCT:42|Μαύρη Μπλούζα|23.71|https://example.com/img.jpg|https://example.com/product/42]\n\nΑνάφερε μέχρι 4 προϊόντα ανά απάντηση.";

        $system_content = $base_prompt
            . $product_format_instruction
            . "\n\n" . $products_context
            . ( $coupons_context ? "\n\n" . $coupons_context : '' )
            . $page_context_str
            . $synonyms_context
            . $fbt_context
            . $contact_context;

        // Build messages array
        $messages = array(
            array( 'role' => 'system', 'content' => $system_content ),
        );

        // Add conversation history
        $history = json_decode( html_entity_decode( $history_json, ENT_QUOTES, 'UTF-8' ), true );
        if ( is_array( $history ) ) {
            foreach ( array_slice( $history, -10 ) as $entry ) {
                if ( isset( $entry['role'], $entry['content'] ) && in_array( $entry['role'], array( 'user', 'assistant' ), true ) ) {
                    $messages[] = array(
                        'role'    => sanitize_key( $entry['role'] ),
                        'content' => sanitize_textarea_field( $entry['content'] ),
                    );
                }
            }
        }

        // Add current user message
        $messages[] = array( 'role' => 'user', 'content' => $user_message );

        // Detect comparison query
        $is_comparison = $this->is_comparison_query( $user_message );

        $model       = get_option( 'word_ai_chat_model', 'gpt-4o-mini' );
        $temperature = (float) get_option( 'word_ai_chat_temperature', '0.7' );
        $max_tokens  = (int) get_option( 'word_ai_chat_max_tokens', 500 );

        $allowed_models = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo', 'gpt-4-turbo' );
        if ( ! in_array( $model, $allowed_models, true ) ) {
            $model = 'gpt-4o-mini';
        }

        $body = wp_json_encode( array(
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        ) );

        $response = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => $body,
            )
        );

        if ( is_wp_error( $response ) ) {
            return 'Σφάλμα σύνδεσης: ' . $response->get_error_message();
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body_raw, true );

        if ( 200 !== $status_code || empty( $data['choices'][0]['message']['content'] ) ) {
            $error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
            return 'API Error: ' . $error_msg;
        }

        $text = trim( $data['choices'][0]['message']['content'] );
        return array( 'text' => $text, 'is_comparison' => $is_comparison );
    }

    /**
     * Detect if the user is asking for a product comparison.
     *
     * @param string $message
     * @return bool
     */
    private function is_comparison_query( $message ) {
        $keywords = array( 'compare', 'σύγκριση', 'συγκρίνε', 'vs', 'διαφορά', 'difference', 'better', 'καλύτερο' );
        $lower    = mb_strtolower( $message );
        foreach ( $keywords as $kw ) {
            if ( false !== mb_strpos( $lower, $kw ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build synonyms context string from saved settings.
     *
     * @return string
     */
    private function build_synonyms_context() {
        $raw = get_option( 'word_ai_chat_synonyms_raw', '' );
        if ( empty( $raw ) ) {
            return '';
        }
        return "\n\n=== SYNONYMS ===\n" . $raw . "\n=== END SYNONYMS ===";
    }

    /**
     * Build Frequently Bought Together context.
     *
     * @return string
     */
    private function build_fbt_context() {
        $raw = get_option( 'word_ai_chat_fbt_rules', '' );
        if ( empty( $raw ) ) {
            return '';
        }
        return "\n\n=== FREQUENTLY BOUGHT TOGETHER ===\n" . $raw . "\n=== END FBT ===";
    }

    /**
     * Build contact info context.
     *
     * @return string
     */
    private function build_contact_context() {
        $parts = array();
        $fields = array( 'phone', 'email', 'viber', 'whatsapp', 'messenger', 'instagram', 'facebook' );
        foreach ( $fields as $field ) {
            $val = get_option( 'word_ai_chat_contact_' . $field, '' );
            if ( ! empty( $val ) ) {
                $parts[] = ucfirst( $field ) . ': ' . $val;
            }
        }
        if ( empty( $parts ) ) {
            return '';
        }
        return "\n\nCONTACT INFO:\n" . implode( "\n", $parts ) . "\n- When user asks how to contact us, reply with a friendly message followed by [CONTACT_CARD] on its own line.\n";
    }

    /**
     * Parse the AI response text into a structured array for the frontend.
     *
     * @param string $response
     * @param bool   $is_comparison
     * @return array
     */
    private function parse_ai_response( $response, $is_comparison = false ) {
        // Try [PRODUCT:id|name|price|image|url] format
        $pattern = '/\[PRODUCT:([^|]+)\|([^|]+)\|([^|]+)\|([^|]+)\|([^\]]+)\]/';
        preg_match_all( $pattern, $response, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );

        if ( ! empty( $matches ) ) {
            // Comparison mode with exactly 2 products
            if ( $is_comparison && count( $matches ) === 2 ) {
                $products   = array();
                $intro_text = trim( substr( $response, 0, $matches[0][0][1] ) );
                foreach ( $matches as $match ) {
                    $products[] = array(
                        'id'    => $match[1][0],
                        'name'  => $match[2][0],
                        'price' => $match[3][0],
                        'image' => $match[4][0],
                        'url'   => $match[5][0],
                    );
                }
                return array(
                    'type'     => 'comparison',
                    'text'     => $intro_text ?: null,
                    'products' => $products,
                );
            }

            $result   = array( 'type' => 'mixed', 'content' => array() );
            $last_pos = 0;

            foreach ( $matches as $match ) {
                $text_before = substr( $response, $last_pos, $match[0][1] - $last_pos );
                if ( trim( $text_before ) ) {
                    $result['content'][] = array( 'type' => 'text', 'text' => trim( $text_before ) );
                }
                $result['content'][] = array(
                    'type'  => 'product',
                    'id'    => $match[1][0],
                    'name'  => $match[2][0],
                    'price' => $match[3][0],
                    'image' => $match[4][0],
                    'url'   => $match[5][0],
                );
                $last_pos = $match[0][1] + strlen( $match[0][0] );
            }

            $text_after = substr( $response, $last_pos );
            if ( trim( $text_after ) ) {
                $result['content'][] = array( 'type' => 'text', 'text' => trim( $text_after ) );
            }

            return $result;
        }

        // Contact card marker
        if ( false !== strpos( $response, '[CONTACT_CARD]' ) ) {
            $text_content = trim( str_replace( '[CONTACT_CARD]', '', $response ) );
            return array(
                'type' => 'contact_card',
                'text' => $text_content ?: 'Μπορείτε να επικοινωνήσετε μαζί μας μέσω:',
            );
        }

        // Plain text
        return array( 'type' => 'text', 'content' => $response );
    }

    /**
     * Save conversation and messages to the database.
     *
     * @param string $user_message
     * @param string $response_text
     * @param string $page_context_json
     * @param int    $response_time_ms
     */
    private function save_conversation( $user_message, $response_text, $page_context_json, $response_time_ms ) {
        global $wpdb;

        $session_id = $this->get_session_id();
        $ip         = $this->get_client_ip();
        $page_url   = '';

        $page_data = json_decode( $page_context_json, true );
        if ( is_array( $page_data ) && isset( $page_data['url'] ) ) {
            $page_url = esc_url_raw( $page_data['url'] );
        }

        // Find or create conversation
        $conversation_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}optic_chat_conversations WHERE session_id = %s ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        if ( ! $conversation_id ) {
            $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prefix . 'optic_chat_conversations',
                array(
                    'session_id'   => $session_id,
                    'ip_address'   => $ip,
                    'page_url'     => mb_substr( $page_url, 0, 500 ),
                    'started_at'   => current_time( 'mysql' ),
                    'message_count' => 0,
                ),
                array( '%s', '%s', '%s', '%s', '%d' )
            );
            $conversation_id = $wpdb->insert_id;
        }

        if ( ! $conversation_id ) {
            return;
        }

        // Insert user message
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prefix . 'optic_chat_messages',
            array(
                'conversation_id'  => $conversation_id,
                'role'             => 'user',
                'content'          => $user_message,
                'created_at'       => current_time( 'mysql' ),
                'response_time_ms' => null,
            ),
            array( '%d', '%s', '%s', '%s', '%d' )
        );

        // Insert assistant message
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prefix . 'optic_chat_messages',
            array(
                'conversation_id'  => $conversation_id,
                'role'             => 'assistant',
                'content'          => $response_text,
                'created_at'       => current_time( 'mysql' ),
                'response_time_ms' => $response_time_ms,
            ),
            array( '%d', '%s', '%s', '%s', '%d' )
        );

        // Update conversation counts
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}optic_chat_conversations SET message_count = message_count + 2, last_message_at = %s WHERE id = %d",
                current_time( 'mysql' ),
                $conversation_id
            )
        );
    }

    /**
     * Get or create a session identifier.
     *
     * @return string
     */
    private function get_session_id() {
        $cookie_name = 'optic_chat_session';
        if ( isset( $_COOKIE[ $cookie_name ] ) ) {
            return sanitize_key( wp_unslash( $_COOKIE[ $cookie_name ] ) );
        }
        // Fallback: derive identifier from IP + day to group messages across requests
        return md5( $this->get_client_ip() . gmdate( 'Y-m-d' ) );
    }
}
