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

// ============================================================================
// ALWAYS HANDLE REVIEWER ROLE CLEANUP (even when preserving other data)
// Users with page_notes_reviewer role are reassigned to Subscriber to prevent
// them being assigned to a non-existent role after uninstall.
// ============================================================================
$page_notes_reviewer_users = get_users(array('role' => 'page_notes_reviewer'));
foreach ($page_notes_reviewer_users as $page_notes_user) {
    $page_notes_user->set_role('subscriber');
}

// Remove the custom role
remove_role('page_notes_reviewer');

// Check if user wants to delete data on uninstall (default: NO)
// Using strict comparison to avoid issues with empty strings or null
$page_notes_delete_on_uninstall = get_option('page_notes_delete_on_uninstall', '0');

// Only delete data if explicitly enabled by the user (must be string '1')
if ($page_notes_delete_on_uninstall !== '1') {
    // Data preservation mode - exit without deleting anything else
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

// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names use $wpdb->prefix (safe)
$wpdb->query("DROP TABLE IF EXISTS $page_notes_table");
// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names use $wpdb->prefix (safe)
$wpdb->query("DROP TABLE IF EXISTS $page_notes_activity_table");
// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names use $wpdb->prefix (safe)
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
