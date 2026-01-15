<?php
/**
 * Uninstall Page Notes Plugin
 *
 * This file is called when the plugin is deleted via WordPress admin.
 * By default, it preserves all plugin data (notes, settings, etc.).
 * Data is only removed if the user has checked "Remove all plugin settings
 * and data when uninstalled" in Settings > Page Notes > Data Management.
 */

// Exit if accessed directly or not called during uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Always clear scheduled cron jobs (even when preserving data)
wp_clear_scheduled_hook('page_notes_auto_send_notifications');
wp_clear_scheduled_hook('page_notes_send_reminders');
wp_clear_scheduled_hook('page_notes_send_activity_digest');

// Check if user wants to delete data on uninstall (default: NO)
// Using strict comparison to avoid issues with empty strings or null
$page_notes_delete_on_uninstall = get_option('page_notes_delete_on_uninstall', '0');

// Only delete data if explicitly enabled by the user (must be string '1')
if ($page_notes_delete_on_uninstall !== '1') {
    // Data preservation mode - exit without deleting anything
    return;
}

// ============================================================================
// USER HAS OPTED IN TO FULL DATA DELETION - PROCEED WITH CLEANUP
// ============================================================================

global $wpdb;

// Drop the custom tables
$page_notes_table = $wpdb->prefix . 'page_notes';
$page_notes_activity_table = $wpdb->prefix . 'page_notes_activity';
$page_notes_completion_table = $wpdb->prefix . 'page_notes_completions';

$wpdb->query("DROP TABLE IF EXISTS $page_notes_table");
$wpdb->query("DROP TABLE IF EXISTS $page_notes_activity_table");
$wpdb->query("DROP TABLE IF EXISTS $page_notes_completion_table");

// Delete all plugin options
delete_option('page_notes_version');
delete_option('page_notes_db_version');
delete_option('page_notes_allowed_roles');
delete_option('page_notes_manager_user_id');
delete_option('page_notes_instant_email');
delete_option('page_notes_auto_send_interval');
delete_option('page_notes_reminders_enabled');
delete_option('page_notes_reminder_time');
delete_option('page_notes_character_limit');
delete_option('page_notes_activity_digest_enabled');
delete_option('page_notes_completion_notification');
delete_option('page_notes_cron_interval');
delete_option('page_notes_reminder_time_stored');
delete_option('page_notes_delete_on_uninstall');

// Delete all user meta for all users
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_enabled'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_individual_access'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_reminders_enabled'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_reminder_interval'");
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_last_reminder_sent'");
