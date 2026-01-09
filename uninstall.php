<?php
/**
 * Uninstall Page Notes Plugin
 *
 * This file is called when the plugin is deleted via WordPress admin.
 * It removes all plugin data from the database.
 */

// Exit if accessed directly or not called during uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load the plugin class if not already loaded
if (!class_exists('PageNotes')) {
    require_once plugin_dir_path(__FILE__) . 'page_notes_plugin.php';
}

// Call the uninstall method
PageNotes::uninstall();
