<?php
/**
 * Plugin Name: Page Notes
 * Plugin URI: https://example.com
 * Description: Add collaborative notes to any element on your WordPress pages
 * Version: 1.2.0
 * Author: Murray Chapman
 * Author URI: https://muzkore.com
 * License: GPL v2 or later
 * Text Domain: page-notes
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PAGE_NOTES_VERSION', '1.2.0');
define('PAGE_NOTES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PAGE_NOTES_PLUGIN_URL', plugin_dir_url(__FILE__));

class PageNotes {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Activation/Deactivation/Uninstall hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('init', array($this, 'init'));
        add_action('admin_bar_menu', array($this, 'add_admin_bar_button'), 100);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Admin settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // User profile hooks
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));

        // AJAX handlers
        add_action('wp_ajax_pn_get_notes', array($this, 'ajax_get_notes'));
        add_action('wp_ajax_pn_save_note', array($this, 'ajax_save_note'));
        add_action('wp_ajax_pn_update_note', array($this, 'ajax_update_note'));
        add_action('wp_ajax_pn_delete_note', array($this, 'ajax_delete_note'));
        add_action('wp_ajax_pn_get_pages_with_notes', array($this, 'ajax_get_pages_with_notes'));
        add_action('wp_ajax_pn_search_users', array($this, 'ajax_search_users'));
        add_action('wp_ajax_pn_send_notifications', array($this, 'ajax_send_notifications'));
        add_action('wp_ajax_pn_check_pending_notifications', array($this, 'ajax_check_pending_notifications'));

        // Cron job for auto-sending notifications
        add_filter('cron_schedules', array($this, 'add_cron_schedules'));
        add_action('page_notes_auto_send_notifications', array($this, 'cron_send_notifications'));
        add_action('page_notes_send_reminders', array($this, 'cron_send_reminders'));
        add_action('page_notes_send_activity_digest', array($this, 'cron_send_activity_digest'));
        add_action('admin_init', array($this, 'schedule_cron_if_needed'));
    }
    
    /**
     * Plugin activation - create/update database table
     */
    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            page_url varchar(500) NOT NULL,
            element_selector text NOT NULL,
            content text NOT NULL,
            user_id bigint(20) NOT NULL,
            assigned_to bigint(20) DEFAULT NULL,
            parent_id bigint(20) DEFAULT 0,
            status varchar(20) DEFAULT 'open',
            notification_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY user_id (user_id),
            KEY assigned_to (assigned_to),
            KEY parent_id (parent_id),
            KEY notification_sent (notification_sent)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Create activity log table
        $activity_table = $wpdb->prefix . 'page_notes_activity';
        $activity_sql = "CREATE TABLE $activity_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            note_id bigint(20) NOT NULL,
            action varchar(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            old_content text DEFAULT NULL,
            new_content text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY note_id (note_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($activity_sql);

        // Create completion notifications table
        $completion_table = $wpdb->prefix . 'page_notes_completions';
        $completion_sql = "CREATE TABLE $completion_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            note_id bigint(20) NOT NULL,
            completed_by bigint(20) NOT NULL,
            note_creator bigint(20) NOT NULL,
            notification_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY note_id (note_id),
            KEY completed_by (completed_by),
            KEY note_creator (note_creator),
            KEY notification_sent (notification_sent)
        ) $charset_collate;";

        dbDelta($completion_sql);

        // Update plugin version in database
        update_option('page_notes_db_version', PAGE_NOTES_VERSION);

        // Schedule daily reminder cron if not already scheduled
        if (!wp_next_scheduled('page_notes_send_reminders')) {
            $reminder_time = get_option('page_notes_reminder_time', '09:00');

            // Calculate next occurrence using WordPress timezone
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            $target = new DateTime($now->format('Y-m-d') . ' ' . $reminder_time, $timezone);

            // If target time has passed today, schedule for tomorrow
            if ($target->getTimestamp() <= $now->getTimestamp()) {
                $target->modify('+1 day');
            }

            $timestamp = $target->getTimestamp();
            wp_schedule_event($timestamp, 'daily', 'page_notes_send_reminders');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled cron jobs
        $timestamp = wp_next_scheduled('page_notes_auto_send_notifications');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'page_notes_auto_send_notifications');
        }

        $reminder_timestamp = wp_next_scheduled('page_notes_send_reminders');
        if ($reminder_timestamp) {
            wp_unschedule_event($reminder_timestamp, 'page_notes_send_reminders');
        }
    }

    /**
     * Plugin uninstall - clean up database
     * This should be called from uninstall.php
     */
    public static function uninstall() {
        global $wpdb;

        // Drop the custom tables
        $notes_table = $wpdb->prefix . 'page_notes';
        $activity_table = $wpdb->prefix . 'page_notes_activity';
        $completion_table = $wpdb->prefix . 'page_notes_completions';
        $wpdb->query("DROP TABLE IF EXISTS $notes_table");
        $wpdb->query("DROP TABLE IF EXISTS $activity_table");
        $wpdb->query("DROP TABLE IF EXISTS $completion_table");

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

        // Delete all user meta for all users
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_enabled'");
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_individual_access'");
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_reminders_enabled'");
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_reminder_interval'");
        $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key = 'page_notes_last_reminder_sent'");

        // Clear all scheduled cron events
        wp_clear_scheduled_hook('page_notes_auto_send_notifications');
        wp_clear_scheduled_hook('page_notes_send_reminders');
        wp_clear_scheduled_hook('page_notes_send_activity_digest');
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Future: Add any initialization code here
    }
    
    /**
     * Add button to admin bar
     */
    public function add_admin_bar_button($wp_admin_bar) {
        // Only show to users who can edit posts and have Page Notes enabled
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Check if user has Page Notes enabled (default is enabled)
        if (!$this->is_page_notes_enabled_for_user()) {
            return;
        }

        $args = array(
            'id'    => 'page-notes-toggle',
            'title' => '<span class="ab-icon dashicons dashicons-sticky"></span><span class="ab-label">Notes</span>',
            'href'  => '#',
            'meta'  => array(
                'class' => 'page-notes-toggle',
                'title' => 'Toggle Page Notes'
            )
        );
        $wp_admin_bar->add_node($args);
    }
    
    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueue_assets() {
        // Only load for users who can edit posts and have Page Notes enabled
        if (!current_user_can('edit_posts')) {
            return;
        }

        // Check if user has Page Notes enabled (default is enabled)
        if (!$this->is_page_notes_enabled_for_user()) {
            return;
        }

        // CSS
        wp_enqueue_style(
            'page-notes-style',
            PAGE_NOTES_PLUGIN_URL . 'assets/css/page_notes_css.css',
            array(),
            PAGE_NOTES_VERSION
        );

        // JavaScript (no dependencies - pure vanilla JS!)
        wp_enqueue_script(
            'page-notes-script',
            PAGE_NOTES_PLUGIN_URL . 'assets/js/page_notes_js.js',
            array(), // No dependencies needed
            PAGE_NOTES_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script('page-notes-script', 'pageNotesData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('page_notes_nonce'),
            'currentPageId' => get_queried_object_id(),
            'currentPageUrl' => get_permalink(),
            'currentUserId' => get_current_user_id(),
            'currentUserName' => wp_get_current_user()->display_name,
            'characterLimit' => intval(get_option('page_notes_character_limit', '150'))
        ));
    }
    
    /**
     * AJAX: Get all notes for current page
     */
    public function ajax_get_notes() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';
        $page_id = intval($_POST['page_id']);
        $page_url = isset($_POST['page_url']) ? sanitize_text_field($_POST['page_url']) : '';

        // Get current user ID for visibility filtering
        $current_user_id = get_current_user_id();

        // Check if current user is the Notes Manager
        $manager_user_id = get_option('page_notes_manager_user_id', '');
        $is_manager = !empty($manager_user_id) && $current_user_id == $manager_user_id;

        // Query by both page_id AND page_url to catch notes that might have been stored with different IDs
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*,
                u.display_name as user_name,
                a.display_name as assigned_to_display_name,
                a.user_login as assigned_to_username,
                a.ID as assigned_to_id
            FROM $table_name n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            LEFT JOIN {$wpdb->users} a ON n.assigned_to = a.ID
            WHERE (n.page_id = %d OR n.page_url = %s)
            ORDER BY n.created_at ASC",
            $page_id,
            $page_url
        ));

        // Filter notes based on visibility rules with thread context
        $visible_notes = array();
        $context_parent_ids = array(); // Track parent notes that need to be shown for context

        // First pass: identify notes user can directly see
        $directly_visible_ids = array();
        foreach ($notes as $note) {
            $visible = false;

            // Notes Manager sees everything
            if ($is_manager) {
                $visible = true;
            }
            // Note created by current user
            elseif ($note->user_id == $current_user_id) {
                $visible = true;
            }
            // Note assigned to current user
            elseif (!empty($note->assigned_to) && $note->assigned_to == $current_user_id) {
                $visible = true;
            }
            // Note not assigned to anyone (general note)
            elseif (empty($note->assigned_to) || $note->assigned_to == 0) {
                $visible = true;
            }

            if ($visible) {
                $directly_visible_ids[] = $note->id;

                // If this is a reply, mark its parent for context inclusion
                if (!empty($note->parent_id) && $note->parent_id > 0) {
                    $context_parent_ids[] = $note->parent_id;
                }
            }
        }

        // Second pass: build final visible notes list with context markers
        foreach ($notes as $note) {
            $is_directly_visible = in_array($note->id, $directly_visible_ids);
            $is_context_only = !$is_directly_visible && in_array($note->id, $context_parent_ids);

            if ($is_directly_visible || $is_context_only) {
                // Build better display names using first/last names with fallback
                $note->user_name = esc_html($note->user_name);
                $note->element_selector = esc_attr($note->element_selector);
                $note->assigned_to_username = esc_html($note->assigned_to_username);

                // Build assigned_to_name from first/last name or display name or username
                if ($note->assigned_to_id) {
                    $note->assigned_to_name = $this->get_user_display_name($note->assigned_to_id);
                } else {
                    $note->assigned_to_name = '';
                }

                // Mark context-only notes so frontend can style them differently
                $note->is_context_only = $is_context_only;

                $visible_notes[] = $note;
            }
        }

        wp_send_json_success($visible_notes);
    }
    
    /**
     * AJAX: Save new note
     */
    public function ajax_save_note() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Validate required fields exist in POST
        if (!isset($_POST['page_id']) || !isset($_POST['content']) || !isset($_POST['element_selector'])) {
            wp_send_json_error('Missing required fields');
            return;
        }

        $page_id = intval($_POST['page_id']);
        $content = trim(wp_kses_post($_POST['content']));
        $element_selector = sanitize_text_field($_POST['element_selector']);
        $page_url = isset($_POST['page_url']) ? sanitize_text_field($_POST['page_url']) : '';

        // Validate page_id is not negative (0 is valid for some pages like blog index)
        if ($page_id < 0) {
            wp_send_json_error('Invalid page ID');
            return;
        }

        // Validate content is not empty after sanitization
        if (empty($content)) {
            wp_send_json_error('Note content cannot be empty');
            return;
        }

        // Validate character limit
        $character_limit = intval(get_option('page_notes_character_limit', '150'));
        if ($character_limit > 0 && mb_strlen($content) > $character_limit) {
            wp_send_json_error(sprintf('Note content exceeds the maximum limit of %d characters', $character_limit));
            return;
        }

        // Validate element_selector is not empty
        if (empty($element_selector)) {
            wp_send_json_error('Element selector cannot be empty');
            return;
        }

        // Validate URL format (if provided)
        // Note: We use a relaxed validation since WordPress URLs can vary
        if (!empty($page_url)) {
            $parsed_url = parse_url($page_url);
            if ($parsed_url === false || !isset($parsed_url['scheme'])) {
                // If parse_url fails or no scheme, it might be a relative URL - that's OK
                // We'll just store it as-is
            }
        }

        // Extract @mention and get assigned user ID
        $assigned_to = $this->extract_mention_user_id($content);

        $data = array(
            'page_id' => $page_id,
            'page_url' => $page_url,
            'element_selector' => $element_selector,
            'content' => $content,
            'user_id' => get_current_user_id(),
            'assigned_to' => $assigned_to,
            'parent_id' => isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0
        );

        $result = $wpdb->insert($table_name, $data);

        if ($result) {
            $note_id = $wpdb->insert_id;

            // Log activity
            $this->log_activity($note_id, 'created', null, $content);

            // Check if instant email is enabled
            $instant_email = get_option('page_notes_instant_email', '0');

            // Handle reply notifications (if this is a reply)
            $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
            if ($instant_email === '1' && $parent_id > 0) {
                // This is a reply - notify the parent note author
                $parent_note = $wpdb->get_row($wpdb->prepare(
                    "SELECT n.*, creator.user_email, creator.ID as creator_id
                    FROM $table_name n
                    LEFT JOIN {$wpdb->users} creator ON n.user_id = creator.ID
                    WHERE n.id = %d",
                    $parent_id
                ));

                if ($parent_note && !empty($parent_note->user_email)) {
                    $parent_author_name = $this->get_user_display_name($parent_note->creator_id);

                    // Get the reply note details
                    $reply_note = $wpdb->get_row($wpdb->prepare(
                        "SELECT n.*, creator.ID as creator_id
                        FROM $table_name n
                        LEFT JOIN {$wpdb->users} creator ON n.user_id = creator.ID
                        WHERE n.id = %d",
                        $note_id
                    ));

                    if ($reply_note) {
                        $this->send_reply_notification_email(
                            $parent_note->user_email,
                            $parent_author_name,
                            $parent_note,
                            $reply_note
                        );

                        // Mark reply as sent
                        $wpdb->update(
                            $table_name,
                            array('notification_sent' => 1),
                            array('id' => $note_id)
                        );
                    }
                }
            }

            // Handle assignment notifications (if note is assigned)
            if ($instant_email === '1' && !empty($assigned_to)) {
                // Send notification immediately
                $assignee = get_user_by('id', $assigned_to);
                if ($assignee && !empty($assignee->user_email)) {
                    $assignee_name = $this->get_user_display_name($assigned_to);

                    // Create a note object for the email
                    $note_for_email = $wpdb->get_row($wpdb->prepare(
                        "SELECT n.*,
                            creator.ID as creator_id
                        FROM $table_name n
                        LEFT JOIN {$wpdb->users} creator ON n.user_id = creator.ID
                        WHERE n.id = %d",
                        $note_id
                    ));

                    if ($note_for_email) {
                        $this->send_notification_email(
                            $assignee->user_email,
                            $assignee_name,
                            array($note_for_email)
                        );

                        // Mark as sent
                        $wpdb->update(
                            $table_name,
                            array('notification_sent' => 1),
                            array('id' => $note_id)
                        );
                    }
                }
            }
            $note = $wpdb->get_row($wpdb->prepare(
                "SELECT n.*,
                    u.display_name as user_name,
                    a.display_name as assigned_to_display_name,
                    a.user_login as assigned_to_username,
                    a.ID as assigned_to_id
                FROM $table_name n
                LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
                LEFT JOIN {$wpdb->users} a ON n.assigned_to = a.ID
                WHERE n.id = %d",
                $note_id
            ));

            // Sanitize output to prevent XSS and build display name
            if ($note) {
                $note->user_name = esc_html($note->user_name);
                $note->element_selector = esc_attr($note->element_selector);
                $note->assigned_to_username = esc_html($note->assigned_to_username);

                // Build assigned_to_name from first/last name or display name or username
                if ($note->assigned_to_id) {
                    $note->assigned_to_name = $this->get_user_display_name($note->assigned_to_id);
                } else {
                    $note->assigned_to_name = '';
                }
            }

            wp_send_json_success($note);
        } else {
            wp_send_json_error('Failed to save note');
        }
    }
    
    /**
     * AJAX: Update existing note
     */
    public function ajax_update_note() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Validate note_id
        if (empty($_POST['note_id'])) {
            wp_send_json_error('Missing note ID');
            return;
        }

        $note_id = intval($_POST['note_id']);

        if ($note_id <= 0) {
            wp_send_json_error('Invalid note ID');
            return;
        }

        // Check if user owns this note
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $note_id
        ));

        if (!$note) {
            wp_send_json_error('Note not found');
            return;
        }

        if ($note->user_id != get_current_user_id()) {
            wp_send_json_error('Permission denied');
            return;
        }

        $data = array();

        // Validate and add content if provided
        if (isset($_POST['content'])) {
            $content = trim(wp_kses_post($_POST['content']));
            if (empty($content)) {
                wp_send_json_error('Note content cannot be empty');
                return;
            }

            // Validate character limit
            $character_limit = intval(get_option('page_notes_character_limit', '150'));
            if ($character_limit > 0 && mb_strlen($content) > $character_limit) {
                wp_send_json_error(sprintf('Note content exceeds the maximum limit of %d characters', $character_limit));
                return;
            }

            $data['content'] = $content;
        }

        // Validate and add status if provided
        if (isset($_POST['status'])) {
            $status = sanitize_text_field($_POST['status']);
            $allowed_statuses = array('open', 'completed');
            if (!in_array($status, $allowed_statuses, true)) {
                wp_send_json_error('Invalid status value');
                return;
            }
            $data['status'] = $status;
        }

        // Ensure we have something to update
        if (empty($data)) {
            wp_send_json_error('No data to update');
            return;
        }

        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $note_id)
        );

        if ($result !== false) {
            // Log activity if content was changed
            if (isset($data['content'])) {
                $this->log_activity($note_id, 'edited', $note->content, $data['content']);
            }

            // Handle completion notification
            if (isset($data['status']) && $data['status'] === 'completed' && $note->status !== 'completed') {
                // Status changed from not completed to completed
                $this->handle_completion_notification($note_id, $note->user_id, get_current_user_id());
            }

            wp_send_json_success('Note updated');
        } else {
            wp_send_json_error('Failed to update note');
        }
    }
    
    /**
     * AJAX: Delete note
     */
    public function ajax_delete_note() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Validate note_id
        if (empty($_POST['note_id'])) {
            wp_send_json_error('Missing note ID');
            return;
        }

        $note_id = intval($_POST['note_id']);

        if ($note_id <= 0) {
            wp_send_json_error('Invalid note ID');
            return;
        }

        // Check if user owns this note
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $note_id
        ));

        if (!$note) {
            wp_send_json_error('Note not found');
            return;
        }

        if ($note->user_id != get_current_user_id()) {
            wp_send_json_error('Permission denied');
            return;
        }

        // Log activity before deleting
        $this->log_activity($note_id, 'deleted', $note->content, null);

        $result = $wpdb->delete($table_name, array('id' => $note_id));

        if ($result) {
            wp_send_json_success('Note deleted');
        } else {
            wp_send_json_error('Failed to delete note');
        }
    }
    
    /**
     * AJAX: Get all pages that have notes
     */
    public function ajax_get_pages_with_notes() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Get pages with both total count and open count
        $pages = $wpdb->get_results($wpdb->prepare(
            "SELECT
                page_id,
                page_url,
                COUNT(*) as note_count,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open_count
            FROM {$wpdb->prefix}page_notes
            WHERE parent_id = %d
            GROUP BY page_id, page_url
            ORDER BY MAX(updated_at) DESC",
            0
        ));

        // Track pages to keep and pages to delete
        $valid_pages = array();
        $pages_to_delete = array();

        // Get page titles and sanitize output
        foreach ($pages as $page) {
            $title = '';
            $page_exists = false;

            // Strategy 1: Try to get post by URL first (most reliable)
            if (!empty($page->page_url)) {
                $post_id = url_to_postid($page->page_url);
                if ($post_id > 0) {
                    $title = get_the_title($post_id);
                    $page_exists = true;
                }
            }

            // Strategy 2: If URL didn't work, try using the stored page_id
            if (empty($title) && $page->page_id > 0) {
                $post = get_post($page->page_id);
                if ($post && $post->post_status !== 'trash') {
                    $title = get_the_title($page->page_id);
                    $page_exists = true;
                }
            }

            // Strategy 3: If still no title, extract from URL path (for non-post pages)
            if (empty($title) && !empty($page->page_url)) {
                $parsed = parse_url($page->page_url);
                if (isset($parsed['path'])) {
                    $path = trim($parsed['path'], '/');
                    $title = $path ? ucwords(str_replace(['-', '_', '/'], ' ', $path)) : 'Home';
                    // Assume custom URLs exist unless proven otherwise
                    $page_exists = true;
                }
            }

            // If page doesn't exist, mark for deletion
            if (!$page_exists) {
                $pages_to_delete[] = array(
                    'page_id' => $page->page_id,
                    'page_url' => $page->page_url
                );
                continue; // Don't include in results
            }

            // Page exists, add to valid pages
            $page->page_title = esc_html($title);
            $page->page_url = esc_url($page->page_url);
            $valid_pages[] = $page;
        }

        // Delete notes for non-existent pages
        if (!empty($pages_to_delete)) {
            foreach ($pages_to_delete as $deleted_page) {
                $wpdb->delete(
                    $table_name,
                    array(
                        'page_id' => $deleted_page['page_id'],
                        'page_url' => $deleted_page['page_url']
                    )
                );
            }
        }

        wp_send_json_success($valid_pages);
    }

    /**
     * Check if user's role is allowed to use Page Notes
     */
    private function user_role_is_allowed($user = null) {
        if ($user === null) {
            $user = wp_get_current_user();
        }

        if (!$user || !$user->exists()) {
            return false;
        }

        // Check for individual user override first (allows specific users regardless of role)
        $individual_access = get_user_meta($user->ID, 'page_notes_individual_access', true);
        if ($individual_access === '1') {
            return true;
        }

        // Get allowed roles from settings (default to administrator only)
        $allowed_roles = get_option('page_notes_allowed_roles', array('administrator'));
        if (!is_array($allowed_roles)) {
            $allowed_roles = array('administrator');
        }

        // Check if user has any of the allowed roles
        $user_roles = $user->roles;
        foreach ($user_roles as $role) {
            if (in_array($role, $allowed_roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if Page Notes is enabled for the current user
     * Checks both role permission and user preference
     */
    private function is_page_notes_enabled_for_user() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        // First check if user's role is allowed
        if (!$this->user_role_is_allowed()) {
            return false;
        }

        // Then check user's personal preference (default to enabled)
        $enabled = get_user_meta($user_id, 'page_notes_enabled', true);

        // If the meta doesn't exist yet, default to enabled
        if ($enabled === '') {
            return true;
        }

        return $enabled === '1' || $enabled === 1 || $enabled === true;
    }

    /**
     * Add Page Notes section to user profile page
     */
    public function add_user_profile_fields($user) {
        $has_role_access = $this->user_role_is_allowed($user);
        $individual_access = get_user_meta($user->ID, 'page_notes_individual_access', true);

        // Check if current user can manage this - only admins can grant individual access
        $can_manage_access = current_user_can('manage_options') && get_current_user_id() != $user->ID;

        // Show individual access field for admins (when editing other users)
        if ($can_manage_access) {
            ?>
            <h3>Page Notes Access</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Grant Individual Access</th>
                    <td>
                        <label for="page_notes_individual_access">
                            <input type="checkbox" name="page_notes_individual_access" id="page_notes_individual_access" value="1" <?php checked($individual_access, '1'); ?> />
                            Grant this user access to Page Notes regardless of their role
                        </label>
                        <p class="description">
                            Useful for giving specific users (e.g., Subscribers) access to Page Notes without changing their role or global role settings.
                            <?php if (!$has_role_access && $individual_access !== '1'): ?>
                                <strong>Currently, this user does not have access to Page Notes.</strong>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php
        }

        // Show regular Page Notes settings only if user has access (either by role or individual grant)
        if (!$has_role_access && $individual_access !== '1') {
            return;
        }

        // Get current settings (default to enabled)
        $enabled = get_user_meta($user->ID, 'page_notes_enabled', true);
        if ($enabled === '') {
            $enabled = '1'; // Default to enabled
        }

        $reminders_enabled = get_user_meta($user->ID, 'page_notes_reminders_enabled', true);
        if ($reminders_enabled === '') {
            $reminders_enabled = '1'; // Default to enabled
        }

        $reminder_interval = get_user_meta($user->ID, 'page_notes_reminder_interval', true);
        if (empty($reminder_interval)) {
            $reminder_interval = '1'; // Default to daily
        }
        ?>
        <h3>Page Notes Settings</h3>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Enable Page Notes</th>
                <td>
                    <label for="page_notes_enabled">
                        <input type="checkbox" name="page_notes_enabled" id="page_notes_enabled" value="1" <?php checked($enabled, '1'); ?> />
                        Show Page Notes button in admin bar and load Page Notes functionality
                    </label>
                    <p class="description">Uncheck this to disable Page Notes for your account. You can re-enable it anytime.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Task Reminders</th>
                <td>
                    <label for="page_notes_reminders_enabled">
                        <input type="checkbox" name="page_notes_reminders_enabled" id="page_notes_reminders_enabled" value="1" <?php checked($reminders_enabled, '1'); ?> />
                        Send me email reminders about incomplete tasks assigned to me
                    </label>
                    <p class="description">Get periodic reminders about tasks that are still waiting for you.</p>
                </td>
            </tr>
            <tr>
                <th scope="row">Reminder Frequency</th>
                <td>
                    <label for="page_notes_reminder_interval">
                        Send reminders every:
                        <select name="page_notes_reminder_interval" id="page_notes_reminder_interval">
                            <option value="1" <?php selected($reminder_interval, '1'); ?>>1 day (daily)</option>
                            <option value="2" <?php selected($reminder_interval, '2'); ?>>2 days</option>
                            <option value="3" <?php selected($reminder_interval, '3'); ?>>3 days</option>
                            <option value="5" <?php selected($reminder_interval, '5'); ?>>5 days</option>
                            <option value="7" <?php selected($reminder_interval, '7'); ?>>7 days (weekly)</option>
                        </select>
                    </label>
                    <p class="description">Only applies if task reminders are enabled above.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save Page Notes user profile fields
     */
    public function save_user_profile_fields($user_id) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        // Only admins can grant individual access (and not to themselves)
        if (current_user_can('manage_options') && get_current_user_id() != $user_id) {
            $individual_access = isset($_POST['page_notes_individual_access']) ? '1' : '0';
            update_user_meta($user_id, 'page_notes_individual_access', $individual_access);
        }

        // Save the checkbox values
        $enabled = isset($_POST['page_notes_enabled']) ? '1' : '0';
        update_user_meta($user_id, 'page_notes_enabled', $enabled);

        $reminders_enabled = isset($_POST['page_notes_reminders_enabled']) ? '1' : '0';
        update_user_meta($user_id, 'page_notes_reminders_enabled', $reminders_enabled);

        // Save reminder interval
        if (isset($_POST['page_notes_reminder_interval'])) {
            $interval = intval($_POST['page_notes_reminder_interval']);
            if (in_array($interval, array(1, 2, 3, 5, 7))) {
                update_user_meta($user_id, 'page_notes_reminder_interval', $interval);
            }
        }
    }

    /**
     * Add settings page to WordPress admin menu
     */
    public function add_settings_page() {
        // Only allow administrators to access settings
        if (!current_user_can('manage_options')) {
            return;
        }

        add_options_page(
            'Page Notes Settings',           // Page title
            'Page Notes',                    // Menu title
            'manage_options',                // Capability required
            'page-notes-settings',           // Menu slug
            array($this, 'render_settings_page') // Callback function
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('page_notes_settings', 'page_notes_allowed_roles');
        register_setting('page_notes_settings', 'page_notes_manager_user_id');
        register_setting('page_notes_settings', 'page_notes_instant_email');
        register_setting('page_notes_settings', 'page_notes_auto_send_interval');
        register_setting('page_notes_settings', 'page_notes_reminders_enabled');
        register_setting('page_notes_settings', 'page_notes_reminder_time');
        register_setting('page_notes_settings', 'page_notes_character_limit');
        register_setting('page_notes_settings', 'page_notes_activity_digest_enabled');
        register_setting('page_notes_settings', 'page_notes_completion_notification');
    }

    /**
     * Render the settings page content
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get all WordPress roles
        $wp_roles = wp_roles();
        $all_roles = $wp_roles->get_names();

        // Get current allowed roles setting (default to just administrator)
        $allowed_roles = get_option('page_notes_allowed_roles', array('administrator'));
        if (!is_array($allowed_roles)) {
            $allowed_roles = array('administrator');
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('page_notes_settings'); ?>

                <div class="card">
                    <h2>User Role Access</h2>
                    <p>Select which user roles should have access to Page Notes. Users with allowed roles can then enable/disable Page Notes in their profile.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Allowed User Roles</th>
                            <td>
                                <fieldset>
                                    <?php foreach ($all_roles as $role_key => $role_name) : ?>
                                        <?php
                                        $is_admin = ($role_key === 'administrator');
                                        $is_checked = in_array($role_key, $allowed_roles) || $is_admin;
                                        ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input
                                                type="checkbox"
                                                name="page_notes_allowed_roles[]"
                                                value="<?php echo esc_attr($role_key); ?>"
                                                <?php checked($is_checked, true); ?>
                                                <?php echo $is_admin ? 'disabled' : ''; ?>
                                            />
                                            <?php echo esc_html($role_name); ?>
                                            <?php if ($is_admin) : ?>
                                                <em>(always enabled)</em>
                                                <input type="hidden" name="page_notes_allowed_roles[]" value="administrator" />
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <p class="description">
                                        Administrators always have access to Page Notes and cannot be disabled.
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Notes Manager</h2>
                    <p>Select a user who can see ALL notes (like a project manager). This user will see every note regardless of assignment. All other users only see notes they created, notes assigned to them, or unassigned notes.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Notes Manager</th>
                            <td>
                                <?php
                                $manager_user_id = get_option('page_notes_manager_user_id', '');
                                $allowed_roles = get_option('page_notes_allowed_roles', array('administrator'));

                                // Get all users with allowed roles
                                $args = array(
                                    'role__in' => $allowed_roles,
                                    'orderby' => 'display_name',
                                    'order' => 'ASC'
                                );
                                $user_query = new WP_User_Query($args);
                                $users = $user_query->get_results();
                                ?>
                                <select name="page_notes_manager_user_id" id="page_notes_manager_user_id">
                                    <option value="">None (no user sees all notes)</option>
                                    <?php foreach ($users as $user) : ?>
                                        <?php
                                        $display_name = $this->get_user_display_name($user->ID);
                                        $username = $user->user_login;
                                        ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($manager_user_id, $user->ID); ?>>
                                            <?php echo esc_html($display_name . ' (@' . $username . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    The selected user will have access to all notes on all pages, regardless of who created or is assigned to them. Useful for project managers or team leads.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Email Notifications</h2>
                    <p>Configure how and when email notifications are sent when users are mentioned in notes.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Send Emails Immediately</th>
                            <td>
                                <?php
                                $instant_email = get_option('page_notes_instant_email', '0');
                                ?>
                                <label for="page_notes_instant_email">
                                    <input type="checkbox" name="page_notes_instant_email" id="page_notes_instant_email" value="1" <?php checked($instant_email, '1'); ?> />
                                    Send email immediately when a user is mentioned with @username
                                </label>
                                <p class="description">
                                    When enabled, emails are sent instantly (like Elementor). When disabled, notifications are batched and sent manually or automatically based on the schedule below.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Auto-send Pending Notifications</th>
                            <td>
                                <?php
                                $auto_send_interval = get_option('page_notes_auto_send_interval', '4hours');
                                ?>
                                <select name="page_notes_auto_send_interval" id="page_notes_auto_send_interval">
                                    <option value="never" <?php selected($auto_send_interval, 'never'); ?>>Never (manual send only)</option>
                                    <option value="1hour" <?php selected($auto_send_interval, '1hour'); ?>>Every 1 hour</option>
                                    <option value="4hours" <?php selected($auto_send_interval, '4hours'); ?>>Every 4 hours (recommended)</option>
                                    <option value="8hours" <?php selected($auto_send_interval, '8hours'); ?>>Every 8 hours</option>
                                    <option value="daily" <?php selected($auto_send_interval, 'daily'); ?>>Once daily</option>
                                </select>
                                <p class="description">
                                    Automatically sends any pending notifications on this schedule. This ensures notifications don't get forgotten. Users can also manually send notifications anytime using the "Send Notifications" button in the Page Notes panel.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Task Reminders</th>
                            <td>
                                <?php
                                $reminders_enabled = get_option('page_notes_reminders_enabled', '1');
                                ?>
                                <label for="page_notes_reminders_enabled">
                                    <input type="checkbox" name="page_notes_reminders_enabled" id="page_notes_reminders_enabled" value="1" <?php checked($reminders_enabled, '1'); ?> />
                                    Enable task reminder emails
                                </label>
                                <p class="description">
                                    When enabled, users can opt-in to receive periodic email reminders about incomplete tasks assigned to them. Users configure their reminder preferences in their profile settings.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Reminder Send Time</th>
                            <td>
                                <?php
                                $reminder_time = get_option('page_notes_reminder_time', '09:00');
                                ?>
                                <select name="page_notes_reminder_time" id="page_notes_reminder_time">
                                    <?php
                                    // Generate time options in 30-minute blocks
                                    for ($hour = 0; $hour < 24; $hour++) {
                                        foreach (array('00', '30') as $minute) {
                                            $time_value = sprintf('%02d:%s', $hour, $minute);
                                            $time_label = date('g:i A', strtotime($time_value));
                                            ?>
                                            <option value="<?php echo esc_attr($time_value); ?>" <?php selected($reminder_time, $time_value); ?>>
                                                <?php echo esc_html($time_label); ?>
                                            </option>
                                            <?php
                                        }
                                    }
                                    ?>
                                </select>
                                <p class="description">
                                    Daily time when reminder emails should be sent. Reminders are only sent to users who have opted in and have incomplete tasks waiting.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Daily Activity Digest</th>
                            <td>
                                <?php
                                $activity_digest_enabled = get_option('page_notes_activity_digest_enabled', '0');
                                ?>
                                <label for="page_notes_activity_digest_enabled">
                                    <input type="checkbox" name="page_notes_activity_digest_enabled" id="page_notes_activity_digest_enabled" value="1" <?php checked($activity_digest_enabled, '1'); ?> />
                                    Send daily activity digest to Notes Manager
                                </label>
                                <p class="description">
                                    When enabled, the Notes Manager will receive a daily email summary of all notes created, edited, and deleted in the last 24 hours. Sent at the same time as task reminders.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Task Completion Notifications</th>
                            <td>
                                <?php
                                $completion_notification = get_option('page_notes_completion_notification', 'instant');
                                ?>
                                <select name="page_notes_completion_notification" id="page_notes_completion_notification">
                                    <option value="instant" <?php selected($completion_notification, 'instant'); ?>>Send instant email to note creator</option>
                                    <option value="pending" <?php selected($completion_notification, 'pending'); ?>>Add to pending notifications</option>
                                    <option value="disabled" <?php selected($completion_notification, 'disabled'); ?>>Disabled</option>
                                </select>
                                <p class="description">
                                    How to notify the original note creator when someone completes their task. Instant sends immediately, pending adds to the batch queue, disabled sends no notification.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="margin-top: 20px;">
                    <h2>Content Settings</h2>
                    <p>Configure content restrictions and limits for notes.</p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Note Character Limit</th>
                            <td>
                                <?php
                                $character_limit = get_option('page_notes_character_limit', '150');
                                ?>
                                <input type="number" name="page_notes_character_limit" id="page_notes_character_limit" value="<?php echo esc_attr($character_limit); ?>" min="50" max="10000" step="50" style="width: 100px;" />
                                characters
                                <p class="description">
                                    Maximum number of characters allowed per note. Set to 0 for no limit. Default: 150 characters. Recommended range: 150-500 characters.
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('Save Settings'); ?>
            </form>

            <div class="card" style="margin-top: 20px;">
                <h2>Plugin Information</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Version</th>
                        <td><?php echo esc_html(PAGE_NOTES_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Plugin Directory</th>
                        <td><code style="word-break: break-all;"><?php echo esc_html(PAGE_NOTES_PLUGIN_DIR); ?></code></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Log note activity for digest emails
     */
    private function log_activity($note_id, $action, $old_content = null, $new_content = null) {
        global $wpdb;
        $activity_table = $wpdb->prefix . 'page_notes_activity';

        $wpdb->insert($activity_table, array(
            'note_id' => $note_id,
            'action' => $action,
            'user_id' => get_current_user_id(),
            'old_content' => $old_content,
            'new_content' => $new_content
        ));
    }

    /**
     * Handle completion notification when a note is marked as completed
     */
    private function handle_completion_notification($note_id, $note_creator_id, $completed_by_id) {
        global $wpdb;

        // Don't notify if the creator completed their own note
        if ($note_creator_id == $completed_by_id) {
            return;
        }

        // Get the notification setting
        $notification_type = get_option('page_notes_completion_notification', 'instant');

        // If disabled, do nothing
        if ($notification_type === 'disabled') {
            return;
        }

        // Record the completion
        $completion_table = $wpdb->prefix . 'page_notes_completions';
        $wpdb->insert($completion_table, array(
            'note_id' => $note_id,
            'completed_by' => $completed_by_id,
            'note_creator' => $note_creator_id,
            'notification_sent' => ($notification_type === 'instant') ? 0 : 0
        ));

        // Send instant email if configured
        if ($notification_type === 'instant') {
            $this->send_completion_email($note_id, $note_creator_id, $completed_by_id);

            // Mark as sent
            $wpdb->update(
                $completion_table,
                array('notification_sent' => 1),
                array(
                    'note_id' => $note_id,
                    'completed_by' => $completed_by_id,
                    'note_creator' => $note_creator_id
                )
            );
        }
        // If 'pending', it will be picked up by the pending notifications batch
    }

    /**
     * Send email notification when a task is completed
     */
    private function send_completion_email($note_id, $note_creator_id, $completed_by_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Get the note details
        $note = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $note_id
        ));

        if (!$note) {
            return;
        }

        // Get user info
        $creator = get_userdata($note_creator_id);
        $completer = get_userdata($completed_by_id);

        if (!$creator || !$completer) {
            return;
        }

        $completer_name = $this->get_user_display_name($completed_by_id);
        $page_title = get_the_title($note->page_id) ?: 'Unknown Page';
        $page_url = $note->page_url;

        // Build email
        $subject = sprintf('[Page Notes] Task completed on "%s"', $page_title);

        $message = sprintf(
            "Hi %s,\n\n" .
            "%s has marked your note as completed.\n\n" .
            "Note content:\n%s\n\n" .
            "Page: %s\n" .
            "View the page: %s\n\n" .
            "---\n" .
            "This is an automated notification from Page Notes.",
            $creator->display_name,
            $completer_name,
            $note->content,
            $page_title,
            $page_url
        );

        wp_mail($creator->user_email, $subject, $message);
    }

    /**
     * Get user display name with first/last name preference
     * Priority: First Last > Display Name > Username
     */
    private function get_user_display_name($user_id) {
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);

        // If both first and last name exist, use them
        if (!empty($first_name) && !empty($last_name)) {
            return esc_html(trim($first_name . ' ' . $last_name));
        }

        // If only first name exists
        if (!empty($first_name)) {
            return esc_html($first_name);
        }

        // If only last name exists
        if (!empty($last_name)) {
            return esc_html($last_name);
        }

        // Fall back to display name
        $user = get_user_by('id', $user_id);
        if ($user && !empty($user->display_name)) {
            return esc_html($user->display_name);
        }

        // Final fallback to username
        if ($user && !empty($user->user_login)) {
            return esc_html($user->user_login);
        }

        return '';
    }

    /**
     * Extract @mention from note content and return user ID
     * Returns the first mentioned user's ID or NULL if no mention found
     */
    private function extract_mention_user_id($content) {
        // Match @username pattern (letters, numbers, underscore, hyphen)
        if (preg_match('/@([\w-]+)/', $content, $matches)) {
            $username = $matches[1];

            // Look up user by username
            $user = get_user_by('login', $username);

            if ($user && $user->ID) {
                return $user->ID;
            }
        }

        return null;
    }

    /**
     * AJAX: Search users for @mention autocomplete
     */
    public function ajax_search_users() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        if (strlen($search) < 1) {
            wp_send_json_success(array());
            return;
        }

        // Get allowed roles
        $allowed_roles = get_option('page_notes_allowed_roles', array('administrator'));
        if (!is_array($allowed_roles)) {
            $allowed_roles = array('administrator');
        }

        // Search for users with allowed roles
        // We'll do a broader search and filter on the PHP side to include first/last names
        $args = array(
            'role__in' => $allowed_roles,
            'number' => 50, // Get more results for filtering
            'orderby' => 'display_name',
            'order' => 'ASC'
        );

        $user_query = new WP_User_Query($args);
        $all_users = $user_query->get_results();

        // Filter results based on search term matching username, display name, first name, or last name
        $search_lower = strtolower($search);
        $results = array();

        foreach ($all_users as $user) {
            $username_lower = strtolower($user->user_login);
            $display_name_lower = strtolower($user->display_name);
            $first_name_lower = strtolower(get_user_meta($user->ID, 'first_name', true));
            $last_name_lower = strtolower(get_user_meta($user->ID, 'last_name', true));

            // Check if search matches any of these fields
            if (
                strpos($username_lower, $search_lower) !== false ||
                strpos($display_name_lower, $search_lower) !== false ||
                strpos($first_name_lower, $search_lower) !== false ||
                strpos($last_name_lower, $search_lower) !== false
            ) {
                // Build display name using the same priority as assignment badges
                $real_name = $this->get_user_display_name($user->ID);

                $results[] = array(
                    'id' => $user->ID,
                    'username' => $user->user_login,
                    'display_name' => $real_name // Use real name instead of display_name
                );
            }

            // Limit to 10 results
            if (count($results) >= 10) {
                break;
            }
        }

        wp_send_json_success($results);
    }

    /**
     * AJAX: Manually send pending notifications
     */
    public function ajax_send_notifications() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        $result = $this->send_pending_notifications();

        if ($result['recipients'] > 0) {
            wp_send_json_success(array(
                'message' => sprintf(
                    'Sent %d notification%s to %d recipient%s',
                    $result['sent'],
                    $result['sent'] === 1 ? '' : 's',
                    $result['recipients'],
                    $result['recipients'] === 1 ? '' : 's'
                ),
                'sent' => $result['sent'],
                'recipients' => $result['recipients']
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'No pending notifications to send',
                'sent' => 0,
                'recipients' => 0
            ));
        }
    }

    /**
     * AJAX: Check if there are pending notifications
     */
    public function ajax_check_pending_notifications() {
        check_ajax_referer('page_notes_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';
        $completion_table = $wpdb->prefix . 'page_notes_completions';

        // Count pending assignment notifications
        $assignment_count = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $table_name
            WHERE assigned_to IS NOT NULL
            AND assigned_to > 0
            AND notification_sent = 0"
        );

        // Count pending reply notifications
        $reply_count = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $table_name
            WHERE parent_id > 0
            AND notification_sent = 0"
        );

        // Count pending completion notifications
        $completion_count = $wpdb->get_var(
            "SELECT COUNT(*)
            FROM $completion_table
            WHERE notification_sent = 0"
        );

        $total_count = intval($assignment_count) + intval($reply_count) + intval($completion_count);

        wp_send_json_success(array(
            'has_pending' => $total_count > 0,
            'count' => $total_count
        ));
    }

    /**
     * Send pending email notifications
     * Groups notes by recipient and page for a clean email experience
     * Includes both assignment notifications and reply notifications
     */
    public function send_pending_notifications() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Get all notes with pending notifications (assigned to someone but notification not sent)
        $pending_notes = $wpdb->get_results(
            "SELECT n.*,
                creator.display_name as creator_display_name,
                creator.user_login as creator_username,
                creator.ID as creator_id,
                assignee.user_email as assignee_email,
                assignee.ID as assignee_id
            FROM $table_name n
            LEFT JOIN {$wpdb->users} creator ON n.user_id = creator.ID
            LEFT JOIN {$wpdb->users} assignee ON n.assigned_to = assignee.ID
            WHERE n.assigned_to IS NOT NULL
            AND n.assigned_to > 0
            AND n.notification_sent = 0
            ORDER BY assignee.ID, n.page_url, n.created_at ASC"
        );

        // Get all replies with pending notifications
        $pending_replies = $wpdb->get_results(
            "SELECT n.*,
                creator.display_name as reply_author_name,
                creator.ID as reply_author_id,
                parent.user_id as parent_author_id,
                parent_user.user_email as parent_author_email
            FROM $table_name n
            LEFT JOIN {$wpdb->users} creator ON n.user_id = creator.ID
            LEFT JOIN $table_name parent ON n.parent_id = parent.id
            LEFT JOIN {$wpdb->users} parent_user ON parent.user_id = parent_user.ID
            WHERE n.parent_id > 0
            AND n.notification_sent = 0
            ORDER BY parent_author_id, n.created_at ASC"
        );

        // Get all pending completion notifications
        $completion_table = $wpdb->prefix . 'page_notes_completions';
        $pending_completions = $wpdb->get_results(
            "SELECT c.*,
                n.content as note_content,
                n.page_id,
                n.page_url,
                completer.display_name as completer_name,
                creator.user_email as creator_email
            FROM $completion_table c
            LEFT JOIN $table_name n ON c.note_id = n.id
            LEFT JOIN {$wpdb->users} completer ON c.completed_by = completer.ID
            LEFT JOIN {$wpdb->users} creator ON c.note_creator = creator.ID
            WHERE c.notification_sent = 0
            ORDER BY c.note_creator, c.created_at ASC"
        );

        if (empty($pending_notes) && empty($pending_replies) && empty($pending_completions)) {
            return array('sent' => 0, 'recipients' => 0);
        }

        // Group assignment notes by recipient
        $notes_by_recipient = array();
        foreach ($pending_notes as $note) {
            if (empty($note->assignee_email)) {
                continue; // Skip if assignee doesn't exist
            }

            if (!isset($notes_by_recipient[$note->assignee_id])) {
                $notes_by_recipient[$note->assignee_id] = array(
                    'email' => $note->assignee_email,
                    'name' => $this->get_user_display_name($note->assignee_id),
                    'notes' => array()
                );
            }

            $notes_by_recipient[$note->assignee_id]['notes'][] = $note;
        }

        // Group replies by parent note author
        $replies_by_recipient = array();
        foreach ($pending_replies as $reply) {
            if (empty($reply->parent_author_email) || empty($reply->parent_author_id)) {
                continue; // Skip if parent author doesn't exist
            }

            if (!isset($replies_by_recipient[$reply->parent_author_id])) {
                $replies_by_recipient[$reply->parent_author_id] = array(
                    'email' => $reply->parent_author_email,
                    'name' => $this->get_user_display_name($reply->parent_author_id),
                    'replies' => array()
                );
            }

            $replies_by_recipient[$reply->parent_author_id]['replies'][] = $reply;
        }

        // Send one email per recipient for assignments
        $sent_count = 0;
        $note_ids_to_mark = array();

        foreach ($notes_by_recipient as $recipient_id => $recipient_data) {
            $email_sent = $this->send_notification_email(
                $recipient_data['email'],
                $recipient_data['name'],
                $recipient_data['notes']
            );

            if ($email_sent) {
                $sent_count++;
                // Collect note IDs to mark as sent
                foreach ($recipient_data['notes'] as $note) {
                    $note_ids_to_mark[] = intval($note->id);
                }
            }
        }

        // Send reply notifications
        foreach ($replies_by_recipient as $recipient_id => $recipient_data) {
            foreach ($recipient_data['replies'] as $reply) {
                // Get parent note details
                $parent_note = $wpdb->get_row($wpdb->prepare(
                    "SELECT n.*
                    FROM $table_name n
                    WHERE n.id = %d",
                    $reply->parent_id
                ));

                if ($parent_note) {
                    $email_sent = $this->send_reply_notification_email(
                        $recipient_data['email'],
                        $recipient_data['name'],
                        $parent_note,
                        $reply
                    );

                    if ($email_sent) {
                        $sent_count++;
                        $note_ids_to_mark[] = intval($reply->id);
                    }
                }
            }
        }

        // Send completion notifications
        $completion_ids_to_mark = array();
        foreach ($pending_completions as $completion) {
            if (empty($completion->creator_email)) {
                continue; // Skip if creator doesn't exist
            }

            $email_sent = $this->send_completion_email(
                $completion->note_id,
                $completion->note_creator,
                $completion->completed_by
            );

            if ($email_sent) {
                $sent_count++;
                $completion_ids_to_mark[] = intval($completion->id);
            }
        }

        // Mark all sent notifications
        if (!empty($note_ids_to_mark)) {
            $ids_string = implode(',', $note_ids_to_mark);
            $wpdb->query("UPDATE $table_name SET notification_sent = 1 WHERE id IN ($ids_string)");
        }

        // Mark completion notifications as sent
        if (!empty($completion_ids_to_mark)) {
            $ids_string = implode(',', $completion_ids_to_mark);
            $wpdb->query("UPDATE $completion_table SET notification_sent = 1 WHERE id IN ($ids_string)");
        }

        return array(
            'sent' => count($note_ids_to_mark),
            'recipients' => $sent_count
        );
    }

    /**
     * Send reply notification email
     * Notifies the parent note author that someone replied to their note
     */
    private function send_reply_notification_email($to_email, $to_name, $parent_note, $reply_note) {
        // Get page title
        $page_title = '';
        if (!empty($parent_note->page_url)) {
            $post_id = url_to_postid($parent_note->page_url);
            if ($post_id > 0) {
                $page_title = get_the_title($post_id);
            }
        }

        if (empty($page_title) && $parent_note->page_id > 0) {
            $post = get_post($parent_note->page_id);
            if ($post && $post->post_status !== 'trash') {
                $page_title = get_the_title($parent_note->page_id);
            }
        }

        if (empty($page_title) && !empty($parent_note->page_url)) {
            $parsed = parse_url($parent_note->page_url);
            if (isset($parsed['path'])) {
                $path = trim($parsed['path'], '/');
                $page_title = $path ? ucwords(str_replace(['-', '_', '/'], ' ', $path)) : 'Home';
            }
        }

        $page_title = $page_title ?: 'Unknown Page';

        // Get reply author name
        $reply_author_name = $this->get_user_display_name($reply_note->creator_id);

        // Site info
        $site_name = get_bloginfo('name');

        // Build email subject
        $subject = sprintf('Reply to your note on %s', $site_name);

        // Build email body
        $message = $this->build_reply_email_template(
            $to_name,
            $reply_author_name,
            $parent_note,
            $reply_note,
            $page_title,
            $parent_note->page_url
        );

        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        return wp_mail($to_email, $subject, $message, $headers);
    }

    /**
     * Build HTML email template for reply notifications
     */
    private function build_reply_email_template($to_name, $reply_author_name, $parent_note, $reply_note, $page_title, $page_url) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        // Strip @mentions from displayed content
        $parent_content = preg_replace('/@[\w-]+/', '', $parent_note->content);
        $parent_content = trim($parent_content);
        $reply_content = preg_replace('/@[\w-]+/', '', $reply_note->content);
        $reply_content = trim($reply_content);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background: #6c757d;
                    color: white;
                    padding: 20px;
                    border-radius: 5px 5px 0 0;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .content {
                    background: #f8f9fa;
                    padding: 20px;
                    border: 1px solid #dee2e6;
                    border-top: none;
                }
                .greeting {
                    font-size: 16px;
                    margin-bottom: 20px;
                }
                .divider {
                    border-top: 2px solid #6c757d;
                    margin: 20px 0;
                }
                .page-section {
                    margin-bottom: 25px;
                }
                .page-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #495057;
                    margin-bottom: 10px;
                }
                .page-link {
                    display: inline-block;
                    color: #0073aa;
                    text-decoration: none;
                    font-size: 14px;
                    margin-bottom: 15px;
                }
                .page-link:hover {
                    text-decoration: underline;
                }
                .note-box {
                    background: white;
                    padding: 15px;
                    margin-bottom: 15px;
                    border-left: 3px solid #ffc107;
                    border-radius: 3px;
                }
                .note-label {
                    font-size: 12px;
                    font-weight: 600;
                    color: #6c757d;
                    text-transform: uppercase;
                    margin-bottom: 8px;
                }
                .note-content {
                    margin: 0 0 8px 0;
                    font-size: 14px;
                }
                .reply-box {
                    background: white;
                    padding: 15px;
                    margin-left: 20px;
                    border-left: 3px solid #6c757d;
                    border-radius: 3px;
                }
                .reply-author {
                    font-size: 12px;
                    color: #666;
                    font-weight: 600;
                    margin-bottom: 8px;
                }
                .footer {
                    background: #f8f9fa;
                    padding: 15px 20px;
                    border: 1px solid #dee2e6;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .footer a {
                    color: #0073aa;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>💬 Reply to Your Note</h1>
            </div>

            <div class="content">
                <div class="greeting">
                    Hi <?php echo esc_html($to_name); ?>,
                </div>

                <p><strong><?php echo esc_html($reply_author_name); ?></strong> replied to your note:</p>

                <div class="divider"></div>

                <div class="page-section">
                    <div class="page-title"><?php echo esc_html($page_title); ?></div>
                    <a href="<?php echo esc_url($page_url); ?>" class="page-link">View Page &rarr;</a>

                    <div class="note-box">
                        <div class="note-label">Your Original Note:</div>
                        <p class="note-content"><?php echo nl2br(esc_html($parent_content)); ?></p>
                    </div>

                    <div class="reply-box">
                        <div class="reply-author"><?php echo esc_html($reply_author_name); ?> replied:</div>
                        <p class="note-content"><?php echo nl2br(esc_html($reply_content)); ?></p>
                    </div>
                </div>
            </div>

            <div class="footer">
                <p>This is an automated notification from <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
                <p>View the conversation in the Page Notes panel when you visit the page.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send notification email to a single recipient
     * Groups their notes by page for better readability
     */
    private function send_notification_email($to_email, $to_name, $notes) {
        // Group notes by page
        $notes_by_page = array();
        foreach ($notes as $note) {
            $page_key = $note->page_url;
            if (!isset($notes_by_page[$page_key])) {
                $notes_by_page[$page_key] = array(
                    'page_id' => $note->page_id,
                    'page_url' => $note->page_url,
                    'page_title' => '',
                    'notes' => array()
                );
            }
            $notes_by_page[$page_key]['notes'][] = $note;
        }

        // Get page titles
        foreach ($notes_by_page as $page_key => &$page_data) {
            $title = '';

            // Try to get post by URL first
            if (!empty($page_data['page_url'])) {
                $post_id = url_to_postid($page_data['page_url']);
                if ($post_id > 0) {
                    $title = get_the_title($post_id);
                }
            }

            // Try using stored page_id
            if (empty($title) && $page_data['page_id'] > 0) {
                $post = get_post($page_data['page_id']);
                if ($post && $post->post_status !== 'trash') {
                    $title = get_the_title($page_data['page_id']);
                }
            }

            // Extract from URL path
            if (empty($title) && !empty($page_data['page_url'])) {
                $parsed = parse_url($page_data['page_url']);
                if (isset($parsed['path'])) {
                    $path = trim($parsed['path'], '/');
                    $title = $path ? ucwords(str_replace(['-', '_', '/'], ' ', $path)) : 'Home';
                }
            }

            $page_data['page_title'] = $title ?: 'Unknown Page';
        }
        unset($page_data); // Break reference

        // Count total notes
        $total_notes = count($notes);
        $page_count = count($notes_by_page);

        // Get site name for subject line
        $site_name = get_bloginfo('name');

        // Build email subject
        $subject = sprintf('You have %d new Page Note%s on %s',
            $total_notes,
            $total_notes === 1 ? '' : 's',
            $site_name
        );

        // Build email body
        $message = $this->build_email_template($to_name, $notes_by_page, $total_notes, $page_count);

        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        return wp_mail($to_email, $subject, $message, $headers);
    }

    /**
     * Build HTML email template
     */
    private function build_email_template($to_name, $notes_by_page, $total_notes, $page_count) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background: #0073aa;
                    color: white;
                    padding: 20px;
                    border-radius: 5px 5px 0 0;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .content {
                    background: #f9f9f9;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-top: none;
                }
                .greeting {
                    font-size: 16px;
                    margin-bottom: 20px;
                }
                .divider {
                    border-top: 2px solid #0073aa;
                    margin: 20px 0;
                }
                .page-section {
                    margin-bottom: 25px;
                }
                .page-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #0073aa;
                    margin-bottom: 10px;
                }
                .page-link {
                    display: inline-block;
                    color: #0073aa;
                    text-decoration: none;
                    font-size: 14px;
                    margin-bottom: 10px;
                }
                .page-link:hover {
                    text-decoration: underline;
                }
                .note-item {
                    background: white;
                    padding: 12px;
                    margin-bottom: 10px;
                    border-left: 3px solid #0073aa;
                    border-radius: 3px;
                }
                .note-content {
                    margin: 0 0 8px 0;
                    font-size: 14px;
                }
                .note-author {
                    font-size: 12px;
                    color: #666;
                    font-style: italic;
                }
                .footer {
                    background: #f9f9f9;
                    padding: 15px 20px;
                    border: 1px solid #ddd;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .footer a {
                    color: #0073aa;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Page Notes</h1>
            </div>

            <div class="content">
                <div class="greeting">
                    Hi <?php echo esc_html($to_name); ?>,
                </div>

                <p>You've been mentioned in <?php echo esc_html($total_notes); ?> note<?php echo $total_notes === 1 ? '' : 's'; ?> across <?php echo esc_html($page_count); ?> page<?php echo $page_count === 1 ? '' : 's'; ?>:</p>

                <div class="divider"></div>

                <?php foreach ($notes_by_page as $page_data) : ?>
                    <div class="page-section">
                        <div class="page-title"><?php echo esc_html($page_data['page_title']); ?></div>
                        <a href="<?php echo esc_url($page_data['page_url']); ?>" class="page-link">View Page &rarr;</a>

                        <?php foreach ($page_data['notes'] as $note) : ?>
                            <?php
                            $creator_name = $this->get_user_display_name($note->creator_id);
                            // Strip @mentions from displayed content
                            $display_content = preg_replace('/@[\w-]+/', '', $note->content);
                            $display_content = trim($display_content);
                            ?>
                            <div class="note-item">
                                <p class="note-content"><?php echo nl2br(esc_html($display_content)); ?></p>
                                <div class="note-author">— <?php echo esc_html($creator_name); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="footer">
                <p>This is an automated notification from <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
                <p>View all your notes in the Page Notes panel when you visit the site.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Schedule cron job based on settings
     * Called on admin_init to ensure cron is properly scheduled
     */
    public function schedule_cron_if_needed() {
        // Handle notification cron scheduling
        $auto_send_interval = get_option('page_notes_auto_send_interval', '4hours');

        // If set to 'never', clear any existing schedule
        if ($auto_send_interval === 'never') {
            $timestamp = wp_next_scheduled('page_notes_auto_send_notifications');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'page_notes_auto_send_notifications');
            }
        } else {
            // Map interval to seconds
            $intervals = array(
                '1hour' => HOUR_IN_SECONDS,
                '4hours' => 4 * HOUR_IN_SECONDS,
                '8hours' => 8 * HOUR_IN_SECONDS,
                'daily' => DAY_IN_SECONDS
            );

            $interval_seconds = isset($intervals[$auto_send_interval]) ? $intervals[$auto_send_interval] : 4 * HOUR_IN_SECONDS;

            // Check if already scheduled
            $timestamp = wp_next_scheduled('page_notes_auto_send_notifications');

            // Get stored interval to detect changes
            $stored_interval = get_option('page_notes_cron_interval', '');

            // If interval changed or not scheduled, reschedule
            if (!$timestamp || $stored_interval !== $auto_send_interval) {
                // Clear existing schedule
                if ($timestamp) {
                    wp_unschedule_event($timestamp, 'page_notes_auto_send_notifications');
                }

                // Schedule new event
                wp_schedule_event(time() + $interval_seconds, $this->get_cron_recurrence($auto_send_interval), 'page_notes_auto_send_notifications');

                // Store the current interval
                update_option('page_notes_cron_interval', $auto_send_interval);
            }
        }

        // Handle reminder cron scheduling
        $reminders_enabled = get_option('page_notes_reminders_enabled', '1');
        $reminder_time = get_option('page_notes_reminder_time', '09:00');

        // Check if already scheduled
        $reminder_timestamp = wp_next_scheduled('page_notes_send_reminders');

        // Get stored time to detect changes
        $stored_reminder_time = get_option('page_notes_reminder_time_stored', '');

        // If reminders are disabled, clear the schedule
        if ($reminders_enabled !== '1') {
            if ($reminder_timestamp) {
                wp_unschedule_event($reminder_timestamp, 'page_notes_send_reminders');
            }
        } else {
            // If time changed or not scheduled, reschedule
            if (!$reminder_timestamp || $stored_reminder_time !== $reminder_time) {
                // Clear existing schedule
                if ($reminder_timestamp) {
                    wp_unschedule_event($reminder_timestamp, 'page_notes_send_reminders');
                }

                // Calculate next occurrence using WordPress timezone
                $timezone = wp_timezone();
                $now = new DateTime('now', $timezone);
                $target = new DateTime($now->format('Y-m-d') . ' ' . $reminder_time, $timezone);

                // If target time has passed today, schedule for tomorrow
                if ($target->getTimestamp() <= $now->getTimestamp()) {
                    $target->modify('+1 day');
                }

                $target_time = $target->getTimestamp();

                // Schedule new event
                wp_schedule_event($target_time, 'daily', 'page_notes_send_reminders');

                // Store the current time setting
                update_option('page_notes_reminder_time_stored', $reminder_time);
            }
        }

        // Handle activity digest cron scheduling (runs at same time as reminders)
        $activity_digest_enabled = get_option('page_notes_activity_digest_enabled', '0');
        $digest_timestamp = wp_next_scheduled('page_notes_send_activity_digest');

        if ($activity_digest_enabled !== '1') {
            // Digest disabled, clear any scheduled event
            if ($digest_timestamp) {
                wp_unschedule_event($digest_timestamp, 'page_notes_send_activity_digest');
            }
        } else {
            // Digest enabled, ensure it's scheduled at same time as reminders
            if (!$digest_timestamp || $stored_reminder_time !== $reminder_time) {
                // Clear existing schedule
                if ($digest_timestamp) {
                    wp_unschedule_event($digest_timestamp, 'page_notes_send_activity_digest');
                }

                // Schedule at same time as reminders
                if ($reminders_enabled === '1' && isset($target_time)) {
                    wp_schedule_event($target_time, 'daily', 'page_notes_send_activity_digest');
                } else {
                    // Reminders disabled, calculate time independently
                    $timezone = wp_timezone();
                    $now = new DateTime('now', $timezone);
                    $target = new DateTime($now->format('Y-m-d') . ' ' . $reminder_time, $timezone);

                    if ($target->getTimestamp() <= $now->getTimestamp()) {
                        $target->modify('+1 day');
                    }

                    wp_schedule_event($target->getTimestamp(), 'daily', 'page_notes_send_activity_digest');
                }
            }
        }
    }

    /**
     * Get WordPress cron recurrence name for our intervals
     */
    private function get_cron_recurrence($interval) {
        $recurrence_map = array(
            '1hour' => 'hourly',
            '4hours' => 'page_notes_4hours',
            '8hours' => 'page_notes_8hours',
            'daily' => 'daily'
        );

        return isset($recurrence_map[$interval]) ? $recurrence_map[$interval] : 'page_notes_4hours';
    }

    /**
     * Add custom cron schedules
     * WordPress only has hourly and daily by default
     */
    public function add_cron_schedules($schedules) {
        $schedules['page_notes_4hours'] = array(
            'interval' => 4 * HOUR_IN_SECONDS,
            'display' => __('Every 4 hours')
        );
        $schedules['page_notes_8hours'] = array(
            'interval' => 8 * HOUR_IN_SECONDS,
            'display' => __('Every 8 hours')
        );
        return $schedules;
    }

    /**
     * Cron job callback - send pending notifications
     */
    public function cron_send_notifications() {
        // Only run if auto-send is enabled (not 'never')
        $auto_send_interval = get_option('page_notes_auto_send_interval', '4hours');
        if ($auto_send_interval === 'never') {
            return;
        }

        // Send pending notifications
        $this->send_pending_notifications();
    }

    /**
     * Cron job callback - send task reminders
     * Runs daily at configured time
     */
    public function cron_send_reminders() {
        global $wpdb;

        // Check if reminders are enabled globally
        $reminders_enabled = get_option('page_notes_reminders_enabled', '1');
        if ($reminders_enabled !== '1') {
            return;
        }

        // Get all users with reminders enabled
        $users = get_users(array(
            'meta_key' => 'page_notes_reminders_enabled',
            'meta_value' => '1'
        ));

        if (empty($users)) {
            return;
        }

        $table_name = $wpdb->prefix . 'page_notes';
        $sent_count = 0;

        foreach ($users as $user) {
            // Get user's reminder interval (in days)
            $interval = intval(get_user_meta($user->ID, 'page_notes_reminder_interval', true));
            if (empty($interval)) {
                $interval = 1; // Default to daily
            }

            // Get last reminder sent date
            $last_sent = get_user_meta($user->ID, 'page_notes_last_reminder_sent', true);

            // Check if enough days have passed
            if (!empty($last_sent)) {
                $days_since = floor((time() - intval($last_sent)) / DAY_IN_SECONDS);
                if ($days_since < $interval) {
                    continue; // Not time yet
                }
            }

            // Get all open notes assigned to this user
            $open_notes = $wpdb->get_results($wpdb->prepare(
                "SELECT n.*,
                    creator.display_name as creator_display_name,
                    creator.ID as creator_id
                FROM $table_name n
                LEFT JOIN {$wpdb->users} creator ON n.user_id = creator.ID
                WHERE n.assigned_to = %d
                AND n.status = 'open'
                ORDER BY n.page_url, n.created_at ASC",
                $user->ID
            ));

            // Only send if there are open tasks
            if (!empty($open_notes)) {
                $email_sent = $this->send_reminder_email($user->user_email, $this->get_user_display_name($user->ID), $open_notes);

                if ($email_sent) {
                    // Update last sent timestamp
                    update_user_meta($user->ID, 'page_notes_last_reminder_sent', time());
                    $sent_count++;
                }
            }
        }

        return $sent_count;
    }

    /**
     * Cron job callback - send daily activity digest
     * Runs daily at same time as reminders
     */
    public function cron_send_activity_digest() {
        // Check if activity digest is enabled
        $digest_enabled = get_option('page_notes_activity_digest_enabled', '0');
        if ($digest_enabled !== '1') {
            return;
        }

        // Get the Notes Manager
        $manager_id = intval(get_option('page_notes_manager_user_id', 0));
        if ($manager_id <= 0) {
            return; // No manager configured
        }

        $manager = get_user_by('id', $manager_id);
        if (!$manager || empty($manager->user_email)) {
            return; // Manager not found or has no email
        }

        global $wpdb;
        $activity_table = $wpdb->prefix . 'page_notes_activity';
        $notes_table = $wpdb->prefix . 'page_notes';

        // Get activity from last 24 hours
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, n.page_url, n.page_id, u.ID as user_id
            FROM $activity_table a
            LEFT JOIN $notes_table n ON a.note_id = n.id
            LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
            WHERE a.created_at >= %s
            ORDER BY a.created_at DESC",
            $yesterday
        ));

        if (empty($activities)) {
            return; // No activity in last 24 hours
        }

        // Send the digest email
        $this->send_activity_digest_email($manager->user_email, $this->get_user_display_name($manager_id), $activities);
    }

    /**
     * Send task reminder email to a user
     */
    private function send_reminder_email($to_email, $to_name, $notes) {
        // Group notes by page
        $notes_by_page = array();
        foreach ($notes as $note) {
            $page_key = $note->page_url;
            if (!isset($notes_by_page[$page_key])) {
                $notes_by_page[$page_key] = array(
                    'page_id' => $note->page_id,
                    'page_url' => $note->page_url,
                    'page_title' => '',
                    'notes' => array()
                );
            }
            $notes_by_page[$page_key]['notes'][] = $note;
        }

        // Get page titles
        foreach ($notes_by_page as $page_key => &$page_data) {
            $title = '';

            // Try to get post by URL first
            if (!empty($page_data['page_url'])) {
                $post_id = url_to_postid($page_data['page_url']);
                if ($post_id > 0) {
                    $title = get_the_title($post_id);
                }
            }

            // Try using stored page_id
            if (empty($title) && $page_data['page_id'] > 0) {
                $post = get_post($page_data['page_id']);
                if ($post && $post->post_status !== 'trash') {
                    $title = get_the_title($page_data['page_id']);
                }
            }

            // Extract from URL path
            if (empty($title) && !empty($page_data['page_url'])) {
                $parsed = parse_url($page_data['page_url']);
                if (isset($parsed['path'])) {
                    $path = trim($parsed['path'], '/');
                    $title = $path ? ucwords(str_replace(['-', '_', '/'], ' ', $path)) : 'Home';
                }
            }

            $page_data['page_title'] = $title ?: 'Unknown Page';
        }
        unset($page_data);

        // Count total notes
        $total_notes = count($notes);
        $page_count = count($notes_by_page);

        // Get site name for subject line
        $site_name = get_bloginfo('name');

        // Build email subject
        $subject = sprintf('Reminder: You have %d incomplete task%s on %s',
            $total_notes,
            $total_notes === 1 ? '' : 's',
            $site_name
        );

        // Build email body
        $message = $this->build_reminder_email_template($to_name, $notes_by_page, $total_notes, $page_count);

        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        // Send email
        return wp_mail($to_email, $subject, $message, $headers);
    }

    /**
     * Build HTML email template for reminders
     */
    private function build_reminder_email_template($to_name, $notes_by_page, $total_notes, $page_count) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background: #ff9800;
                    color: white;
                    padding: 20px;
                    border-radius: 5px 5px 0 0;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .content {
                    background: #fff8e1;
                    padding: 20px;
                    border: 1px solid #ffcc80;
                    border-top: none;
                }
                .greeting {
                    font-size: 16px;
                    margin-bottom: 20px;
                }
                .divider {
                    border-top: 2px solid #ff9800;
                    margin: 20px 0;
                }
                .page-section {
                    margin-bottom: 25px;
                }
                .page-title {
                    font-size: 18px;
                    font-weight: 600;
                    color: #e65100;
                    margin-bottom: 10px;
                }
                .page-link {
                    display: inline-block;
                    color: #e65100;
                    text-decoration: none;
                    font-size: 14px;
                    margin-bottom: 10px;
                }
                .page-link:hover {
                    text-decoration: underline;
                }
                .note-item {
                    background: white;
                    padding: 12px;
                    margin-bottom: 10px;
                    border-left: 3px solid #ff9800;
                    border-radius: 3px;
                }
                .note-content {
                    margin: 0 0 8px 0;
                    font-size: 14px;
                }
                .note-meta {
                    font-size: 12px;
                    color: #666;
                }
                .note-author {
                    font-style: italic;
                }
                .note-age {
                    color: #999;
                    margin-left: 8px;
                }
                .footer {
                    background: #fff8e1;
                    padding: 15px 20px;
                    border: 1px solid #ffcc80;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .footer a {
                    color: #e65100;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📋 Task Reminder</h1>
            </div>

            <div class="content">
                <div class="greeting">
                    Hi <?php echo esc_html($to_name); ?>,
                </div>

                <p>This is your reminder that you have <?php echo esc_html($total_notes); ?> incomplete task<?php echo $total_notes === 1 ? '' : 's'; ?> waiting for you across <?php echo esc_html($page_count); ?> page<?php echo $page_count === 1 ? '' : 's'; ?>:</p>

                <div class="divider"></div>

                <?php foreach ($notes_by_page as $page_data) : ?>
                    <div class="page-section">
                        <div class="page-title"><?php echo esc_html($page_data['page_title']); ?></div>
                        <a href="<?php echo esc_url($page_data['page_url']); ?>" class="page-link">View Page &rarr;</a>

                        <?php foreach ($page_data['notes'] as $note) : ?>
                            <?php
                            $creator_name = $this->get_user_display_name($note->creator_id);
                            // Strip @mentions from displayed content
                            $display_content = preg_replace('/@[\w-]+/', '', $note->content);
                            $display_content = trim($display_content);

                            // Calculate how long ago the note was created
                            $created_timestamp = strtotime($note->created_at);
                            $days_ago = floor((time() - $created_timestamp) / DAY_IN_SECONDS);

                            if ($days_ago == 0) {
                                $age_text = 'today';
                            } elseif ($days_ago == 1) {
                                $age_text = '1 day ago';
                            } else {
                                $age_text = $days_ago . ' days ago';
                            }
                            ?>
                            <div class="note-item">
                                <p class="note-content"><?php echo nl2br(esc_html($display_content)); ?></p>
                                <div class="note-meta">
                                    <span class="note-author">Assigned by <?php echo esc_html($creator_name); ?></span>
                                    <span class="note-age">(<?php echo esc_html($age_text); ?>)</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="footer">
                <p>This is an automated reminder from <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
                <p>You can change your reminder preferences in your <a href="<?php echo esc_url(admin_url('profile.php')); ?>">profile settings</a>.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Send activity digest email to Notes Manager
     */
    private function send_activity_digest_email($to_email, $to_name, $activities) {
        $site_name = get_bloginfo('name');

        // Count activities by type
        $created_count = 0;
        $edited_count = 0;
        $deleted_count = 0;

        foreach ($activities as $activity) {
            switch ($activity->action) {
                case 'created':
                    $created_count++;
                    break;
                case 'edited':
                    $edited_count++;
                    break;
                case 'deleted':
                    $deleted_count++;
                    break;
            }
        }

        $subject = sprintf('Daily Activity Digest for %s - %d actions', $site_name, count($activities));

        $message = $this->build_activity_digest_template($to_name, $activities, $created_count, $edited_count, $deleted_count);

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        );

        return wp_mail($to_email, $subject, $message, $headers);
    }

    /**
     * Build HTML email template for activity digest
     */
    private function build_activity_digest_template($to_name, $activities, $created_count, $edited_count, $deleted_count) {
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        $total_count = count($activities);

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .header {
                    background: #007cba;
                    color: white;
                    padding: 20px;
                    border-radius: 5px 5px 0 0;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .content {
                    background: #f0f6fc;
                    padding: 20px;
                    border: 1px solid #c3dafe;
                    border-top: none;
                }
                .summary {
                    background: white;
                    padding: 15px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    display: flex;
                    justify-content: space-around;
                    text-align: center;
                }
                .summary-item {
                    flex: 1;
                }
                .summary-number {
                    font-size: 32px;
                    font-weight: bold;
                    color: #007cba;
                }
                .summary-label {
                    font-size: 12px;
                    color: #666;
                    text-transform: uppercase;
                }
                .activity-list {
                    background: white;
                    border-radius: 5px;
                    overflow: hidden;
                }
                .activity-item {
                    padding: 15px;
                    border-bottom: 1px solid #e0e0e0;
                }
                .activity-item:last-child {
                    border-bottom: none;
                }
                .activity-header {
                    display: flex;
                    align-items: center;
                    margin-bottom: 8px;
                }
                .activity-icon {
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin-right: 10px;
                    font-size: 16px;
                }
                .activity-icon.created {
                    background: #d4edda;
                    color: #155724;
                }
                .activity-icon.edited {
                    background: #fff3cd;
                    color: #856404;
                }
                .activity-icon.deleted {
                    background: #f8d7da;
                    color: #721c24;
                }
                .activity-user {
                    font-weight: 600;
                    color: #333;
                }
                .activity-action {
                    color: #666;
                    margin-left: 5px;
                }
                .activity-time {
                    font-size: 11px;
                    color: #999;
                    margin-left: auto;
                }
                .activity-content {
                    font-size: 13px;
                    color: #555;
                    margin-left: 40px;
                    padding: 8px;
                    background: #f9f9f9;
                    border-left: 2px solid #ddd;
                    border-radius: 3px;
                }
                .footer {
                    background: #f0f6fc;
                    padding: 15px 20px;
                    border: 1px solid #c3dafe;
                    border-top: none;
                    border-radius: 0 0 5px 5px;
                    text-align: center;
                    font-size: 12px;
                    color: #666;
                }
                .footer a {
                    color: #007cba;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📊 Daily Activity Digest</h1>
            </div>

            <div class="content">
                <p>Hi <?php echo esc_html($to_name); ?>,</p>
                <p>Here's your daily summary of Page Notes activity for the last 24 hours:</p>

                <div class="summary">
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $created_count; ?></div>
                        <div class="summary-label">Created</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $edited_count; ?></div>
                        <div class="summary-label">Edited</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-number"><?php echo $deleted_count; ?></div>
                        <div class="summary-label">Deleted</div>
                    </div>
                </div>

                <div class="activity-list">
                    <?php foreach ($activities as $activity) : ?>
                        <?php
                        $user_name = $this->get_user_display_name($activity->user_id);
                        $time_ago = human_time_diff(strtotime($activity->created_at), current_time('timestamp')) . ' ago';

                        $icon = '•';
                        $action_text = $activity->action;
                        if ($activity->action === 'created') {
                            $icon = '✓';
                            $action_text = 'created a note';
                        } elseif ($activity->action === 'edited') {
                            $icon = '✎';
                            $action_text = 'edited a note';
                        } elseif ($activity->action === 'deleted') {
                            $icon = '✗';
                            $action_text = 'deleted a note';
                        }

                        $content_to_show = $activity->new_content ?: $activity->old_content;
                        $content_to_show = mb_substr(strip_tags($content_to_show), 0, 100);
                        if (mb_strlen($content_to_show) > 100) {
                            $content_to_show .= '...';
                        }
                        ?>
                        <div class="activity-item">
                            <div class="activity-header">
                                <div class="activity-icon <?php echo esc_attr($activity->action); ?>">
                                    <?php echo $icon; ?>
                                </div>
                                <span class="activity-user"><?php echo esc_html($user_name); ?></span>
                                <span class="activity-action"><?php echo esc_html($action_text); ?></span>
                                <span class="activity-time"><?php echo esc_html($time_ago); ?></span>
                            </div>
                            <?php if ($content_to_show) : ?>
                                <div class="activity-content">
                                    <?php echo esc_html($content_to_show); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="footer">
                <p>This is an automated daily digest from <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a></p>
                <p>You're receiving this because you're the Notes Manager. Disable this in <a href="<?php echo esc_url(admin_url('options-general.php?page=page-notes-settings')); ?>">Page Notes Settings</a>.</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Initialize the plugin
PageNotes::get_instance();