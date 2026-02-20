<?php
/**
 * Fired when the plugin is uninstalled.
 * Removes all plugin options and database tables.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}optic_chat_conversations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}optic_chat_messages" );

// Delete all plugin options
$option_keys = array(
    'word_ai_chat_title',
    'word_ai_chat_welcome_message',
    'word_ai_chat_system_prompt',
    'word_ai_chat_position',
    'word_ai_chat_offset_bottom',
    'word_ai_chat_offset_side',
    'word_ai_chat_auto_open_delay',
    'word_ai_chat_enabled',
    'word_ai_chat_auto_footer',
    'word_ai_chat_api_key',
    'word_ai_chat_model',
    'word_ai_chat_temperature',
    'word_ai_chat_max_tokens',
    'word_ai_chat_data_source',
    'word_ai_chat_xml_path',
    'word_ai_chat_xml_field_mapping',
    'word_ai_chat_primary_color',
    'word_ai_chat_secondary_color',
    'word_ai_chat_button_text_color',
    'word_ai_chat_custom_css',
    'word_ai_chat_chat_logo',
    'word_ai_chat_synonyms_raw',
    'word_ai_chat_fbt_rules',
    'word_ai_chat_quick_buttons',
    'word_ai_chat_page_context_enabled',
    'word_ai_chat_page_context_rules',
    'word_ai_chat_proactive_enabled',
    'word_ai_chat_proactive_delay',
    'word_ai_chat_proactive_message',
    'word_ai_chat_proactive_times',
    'word_ai_chat_proactive_pages',
    'word_ai_chat_rate_limit_messages',
    'word_ai_chat_rate_limit_minutes',
    'word_ai_chat_contact_phone',
    'word_ai_chat_contact_email',
    'word_ai_chat_contact_viber',
    'word_ai_chat_contact_whatsapp',
    'word_ai_chat_contact_messenger',
    'word_ai_chat_contact_instagram',
    'word_ai_chat_contact_facebook',
    'word_ai_chat_hours_mon_fri',
    'word_ai_chat_hours_sat',
    'word_ai_chat_hours_sun',
    'word_ai_chat_timezone',
);

foreach ( $option_keys as $key ) {
    delete_option( $key );
}
