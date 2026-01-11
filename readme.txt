=== Page Notes ===
Contributors: muzkore
Tags: notes, collaboration, annotations, team, workflow
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attach collaborative notes to specific elements on WordPress pages and posts for review, feedback, and task tracking.

== Description ==

Page Notes lets authorised users attach notes to specific elements on any page or post. Notes are context-aware, threaded, and support @mentions, task status, and optional email notifications.

This plugin is designed for internal collaboration: content review, QA, design feedback, and coordinating changes without long email threads.

Page Notes was created to address a common communication gap when working with clients and teams — particularly when feedback arrives as vague or fragmented requests that are hard to interpret out of context. Having previously used similar element-based annotation systems, the goal was to bring that clarity into WordPress as a standalone, builder-agnostic tool. By attaching feedback directly to the page elements being discussed, Page Notes helps reduce confusion, save time, and keep discussions grounded in the actual content being worked on. It’s shared publicly to support other developers, designers, and teams facing the same challenges.

= Core Features =

* **Element-Specific Notes** - Attach notes to any HTML element with visual highlighting
* **Threaded Conversations** - Reply to notes and keep discussions in context
* **@Mentions** - Mention users with @username to assign responsibility
* **Task Status** - Mark notes as completed or reopen them to track progress
* **Optional Email Notifications** - Instant or batched notifications (admin-controlled, opt-in)
* **Activity Logging** - Track note creation/updates/deletions, with an optional digest for a Notes Manager
* **Task Reminders** - Optional reminder emails for incomplete assigned tasks (admin enable + user opt-in)
* **Character Limits** - Admin-configurable per-note character limit
* **Role-Based Access** - Control which roles can access Page Notes
* **Individual User Overrides** - Enable/disable Page Notes for specific users

= Notes About Email Notifications (Opt-in) =

Email notifications are configurable and conservative by default:

* Instant assignment notifications are **disabled by default** and must be enabled by an administrator.
* Activity digests are **disabled by default** and must be enabled by an administrator.
* Task reminders are **disabled by default**; reminders also require user opt-in in their profile.
* Pending batches/digests can be sent on-demand by admins/Notes Manager (where available in the UI).

All emails use WordPress `wp_mail()`. No third-party email service is used unless your site is configured to route mail externally.

= Privacy & Data Handling =

* No external API calls
* No tracking or analytics
* No telemetry
* No third-party services
* localStorage is used only for UI preferences
* All plugin data is stored within your WordPress database

The documentation includes a clear list of stored data and practical steps for removing plugin data when needed.

== Installation ==

1. Upload the `page-notes` folder to `/wp-content/plugins/`
2. Activate the plugin through the ‘Plugins’ menu in WordPress
3. Go to **Settings > Page Notes** to configure access and notification options
4. Visit any page while logged in and click the **Page Notes** button in the admin bar

== Frequently Asked Questions ==

= Who can see the notes? =

Only logged-in users with the `edit_posts` capability and Page Notes enabled can see notes. Role access is configured in **Settings > Page Notes**, and individual user overrides may apply.

= Can I disable email notifications? =

Yes. Notification types can be enabled/disabled in settings. Task reminders also require user opt-in (profile setting) when the feature is enabled globally.

= What happens to my data if I uninstall the plugin? =

By default, notes and settings are preserved in the database. You can opt in to full data removal in **Settings > Page Notes > Data Management**. Full removal is permanent.

= Can I export my notes? =

Notes are stored in custom database tables created using your site’s `{$wpdb->prefix}` (for example: `{$wpdb->prefix}page_notes`, `{$wpdb->prefix}page_notes_activity`, `{$wpdb->prefix}page_notes_completions`). You can export them using standard database export tools.

= Does this plugin work with page builders? =

Yes. Page Notes works with any theme or page builder output (Elementor, Gutenberg, etc.) because notes attach to rendered HTML elements on the page.

= What happens if the page element is removed or changed? =

Notes remain visible in the notes panel even if the original element is no longer present. The stored selector remains available for reference.

== Screenshots ==

1. Notes panel showing threaded conversations and assignments
2. Settings page showing role access and notification options
3. Email notification example
4. Creating a note with element highlighting
5. Activity digest example (when enabled)
6. Task reminder email example (when enabled + user opted in)

== Changelog ==

= 1.2.1 =
* Added: Unified email template system
* Added: Data Management settings section
* Added: Opt-in data deletion on uninstall (default: preserve data)
* Improved: Capability checks on AJAX handlers
* Improved: Email templates reduced in size for maintainability
* Updated: Documentation for privacy/data handling and uninstall behaviour

= 1.2.0 =
* Added: Email notification system with instant and batched sending (admin-controlled)
* Added: Activity digest emails for Notes Manager (optional)
* Added: Pending notification management with manual send control
* Added: Auto-send interval configuration

= 1.1.0 =
* Added: @mention system and user autocomplete
* Improved: Searching by display name or username in autocomplete
* Improved: @mentions hidden from display and shown via badges

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.1 =
Adds data management controls, improves capability checks, and refines email template handling.

= 1.2.0 =
Introduces optional email notifications, activity digests, and configurable send schedules.

= 1.1.0 =
Adds @mentions for assignments and notifications.

== Privacy Policy ==

Page Notes stores data in your WordPress database, including:

* Note content and selectors
* Page IDs/URLs for context
* User IDs (authors and assignments)
* Timestamps
* Activity logs
* Plugin settings and user preferences

Page Notes does NOT:

* Send data to external servers
* Use tracking or analytics
* Make external API calls
* Share data with third parties
* Set tracking cookies

Emails (if enabled) are sent using WordPress `wp_mail()`.

To support privacy requests and data deletion, administrators can:

* Delete notes (via the Page Notes panel on relevant pages)
* Remove plugin user meta/preferences as needed
* Enable full data removal on uninstall in **Settings > Page Notes > Data Management**

== Support ==

For issues, questions, or feature requests:

* Review the plugin documentation (if provided with your install)
* Check browser console/debug logs for technical issues
* Contact your site administrator or development team