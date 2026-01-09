<?php
/**
 * Plugin Name: Page Notes
 * Plugin URI: https://example.com
 * Description: Add collaborative notes to any element on your WordPress pages
 * Version: 1.0.0
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
define('PAGE_NOTES_VERSION', '1.0.6');
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
    }
    
    /**
     * Plugin activation - create database table
     */
    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            page_url varchar(500) NOT NULL,
            element_selector text NOT NULL,
            content text NOT NULL,
            user_id bigint(20) NOT NULL,
            parent_id bigint(20) DEFAULT 0,
            status varchar(20) DEFAULT 'open',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id (page_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Nothing to do on deactivation
        // Data is preserved until plugin is uninstalled
    }

    /**
     * Plugin uninstall - clean up database
     * This should be called from uninstall.php
     */
    public static function uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'page_notes';

        // Drop the custom table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Clean up any options if we add them in the future
        delete_option('page_notes_version');
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
            'currentUserName' => wp_get_current_user()->display_name
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

        // Query by both page_id AND page_url to catch notes that might have been stored with different IDs
        $notes = $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name
            FROM $table_name n
            LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
            WHERE (n.page_id = %d OR n.page_url = %s)
            ORDER BY n.created_at ASC",
            $page_id,
            $page_url
        ));

        // Sanitize output to prevent XSS
        foreach ($notes as $note) {
            $note->user_name = esc_html($note->user_name);
            $note->element_selector = esc_attr($note->element_selector);
            // content is already sanitized with wp_kses_post on input
        }

        wp_send_json_success($notes);
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

        $data = array(
            'page_id' => $page_id,
            'page_url' => $page_url,
            'element_selector' => $element_selector,
            'content' => $content,
            'user_id' => get_current_user_id(),
            'parent_id' => isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0
        );

        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            $note_id = $wpdb->insert_id;
            $note = $wpdb->get_row($wpdb->prepare(
                "SELECT n.*, u.display_name as user_name
                FROM $table_name n
                LEFT JOIN {$wpdb->users} u ON n.user_id = u.ID
                WHERE n.id = %d",
                $note_id
            ));

            // Sanitize output to prevent XSS
            if ($note) {
                $note->user_name = esc_html($note->user_name);
                $note->element_selector = esc_attr($note->element_selector);
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
     * Check if Page Notes is enabled for the current user
     * Default is enabled (true) if not set
     */
    private function is_page_notes_enabled_for_user() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }

        // Get user meta - default to '1' (enabled) if not set
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
        // Get current setting (default to enabled)
        $enabled = get_user_meta($user->ID, 'page_notes_enabled', true);
        if ($enabled === '') {
            $enabled = '1'; // Default to enabled
        }
        ?>
        <h2><?php esc_html_e('Page Notes', 'page-notes'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Enable Page Notes', 'page-notes'); ?></th>
                <td>
                    <fieldset>
                        <label for="page_notes_enabled">
                            <input
                                type="checkbox"
                                name="page_notes_enabled"
                                id="page_notes_enabled"
                                value="1"
                                <?php checked($enabled, '1'); ?>
                            />
                            <?php esc_html_e('Show Page Notes button in admin bar and load Page Notes functionality', 'page-notes'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Uncheck this to disable Page Notes for your account. You can re-enable it anytime.', 'page-notes'); ?>
                        </p>
                    </fieldset>
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

        // Save the checkbox value
        $enabled = isset($_POST['page_notes_enabled']) ? '1' : '0';
        update_user_meta($user_id, 'page_notes_enabled', $enabled);
    }
}

// Initialize the plugin
PageNotes::get_instance();