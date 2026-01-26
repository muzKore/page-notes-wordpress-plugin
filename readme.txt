=== Page Notes ===
Contributors: muzkore
Tags: notes, collaboration, annotations, team, workflow
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.0
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
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create a user with the "Page Notes Reviewer" role (or use existing Editor/Admin)
4. Send them login details - they're ready to leave notes!

**That's it!** No configuration needed for basic use. Visit any page, click **Page Notes** in the admin bar, and start adding feedback.

**Optional:** Go to **Settings > Page Notes** to configure email notifications, @mentions, activity digests, and other team features. These are all optional - the plugin works great without them for simple developer-client workflows.

== Frequently Asked Questions ==

= Do I need to configure settings to use the plugin? =

No! Install, activate, and you're ready to go. All settings are optional extras for larger teams. For a simple developer-client setup, just create a reviewer account and start leaving notes. The only "must do" is ensure users have the right role to access notes.

= Do I need to use @mentions or email notifications? =

No. These are optional features. Without @mentions, notes are general feedback anyone can see. Without email notifications, people just check notes manually when they log in. Perfect for small teams or solo developers working with clients.

= Who can see the notes? =

Only logged-in users with the `use_page_notes` or `edit_posts` capability and Page Notes enabled can see notes. Role access is configured in **Settings > Page Notes**. You can also assign users the "Page Notes Reviewer" role for client access without editing permissions.

= Can I disable email notifications? =

Yes. Notification types can be enabled/disabled in settings. Task reminders also require user opt-in (profile setting) when the feature is enabled globally.

= What happens to my data if I uninstall the plugin? =

By default, notes and settings are preserved in the database. You can opt in to full data removal in **Settings > Page Notes > Data Management**. Full removal is permanent.

**Important:** When the plugin is uninstalled, users with the "Page Notes Reviewer" role are automatically reassigned to the Subscriber role. This prevents users from being assigned to a role that no longer exists. If you want to delete reviewer accounts entirely, do so manually before uninstalling.

= Can I export my notes? =

Yes. Go to **Settings > Page Notes** and use the Export Notes section. You can export all notes to CSV (for spreadsheets) or JSON (for developers/backup). The export includes all note data, assignments, completion tracking, and timestamps.

= Does this plugin work with page builders? =

Yes. Page Notes works with any theme or page builder output (Elementor, Bricks, Breakdance, Oxygen, Gutenberg, etc.) because notes attach to rendered HTML elements on the page.

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

= 1.5.0 =
* Improved: Email templates now use inline CSS styles for better mobile email client compatibility
* Improved: Emails render consistently across Gmail app, Outlook mobile, Apple Mail, and other mobile clients
* Improved: Activity digest stats section now uses table layout for reliable cross-client rendering
* Improved: Added mobile-responsive media queries for email clients that support them
* Fixed: Prevent duplicate note submissions when save button is clicked multiple times
* Fixed: Notes now appear instantly after saving (optimistic UI) instead of reloading all notes

= 1.4.3 =
* Fixed: Critical bug where notes created with fallback selector strategy would not link to elements for other users
* Fixed: Archive pages (categories, tags, etc.) no longer confuse note associations with posts/pages sharing the same ID
* Fixed: Complete button now responds instantly (optimistic UI update instead of waiting for server)
* Added: Element fingerprinting system for robust note-to-element recovery
* Added: Multiple backup selector strategies stored with each note
* Added: Content-based element search when selectors fail
* Added: Broken link indicator shows when a note's target element cannot be found on the page
* Added: Notes Manager can now delete any note (not just their own)
* Added: Loading indicator when fetching notes
* Added: Pages list section now scrolls independently with max height limit
* Added: Local image assets stored in assets/images folder
* Improved: Note timestamps now display date and time (e.g., "21/01/2026 7:34PM") instead of date only

= 1.4.2 =
* Internal development build

= 1.4.1 =
* Added: Send Notifications button now shows pending count, e.g. "Send Notifications (3)"
* Added: Setting to allow any user to complete unassigned notes
* Fixed: Page Notes now hidden in page builder editors (Bricks, Elementor, Oxygen, Breakdance, Beaver Builder, Divi, WPBakery, Brizy, Thrive)
* Fixed: Notification recipient count was incorrectly inflated when sending multiple notifications to the same person
* Fixed: Hover highlight and scroll-to-element now works for all notes on the same element
* Fixed: Complete button not working on notes and replies
* Fixed: Assignees can now mark notes assigned to them as complete

= 1.4.0 =
* Rewritten: Complete element selector generation system for maximum stability
* Added: WordPress Block Editor (Gutenberg) support - notes survive block reordering
* Added: Page builder support (Elementor, Bricks, Breakdance, Oxygen, Beaver Builder, Divi, WPBakery)
* Improved: Intelligent selector fallback system with 7 strategies
* Improved: Parent ID + relative path strategy avoids fragile nth-child selectors
* Added: Permanent data attribute fallback for dynamic content
* Changed: Notes now remain attached to elements even when page structure changes

= 1.3.0 =
* Added: Custom "Page Notes Reviewer" role for client access
* Added: use_page_notes capability for granular permission control
* Changed: Plugin only loads on frontend (not in admin dashboard)
* Improved: Permission system supports both reviewers and editors
* Added: Reviewers can add notes without edit_posts capability

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
* Initial release - Complete collaborative annotation system for WordPress
* Added: Element-specific notes - attach notes to any HTML element on any page or post
* Added: Visual element highlighting - selected elements are highlighted for easy identification
* Added: Notes panel UI - slide-out panel for viewing and managing notes
* Added: Threaded conversations - reply to notes to keep discussions in context
* Added: Task status tracking - mark notes as complete or reopen them
* Added: Role-based access control - configure which WordPress roles can use Page Notes
* Added: Individual user overrides - enable or disable Page Notes for specific users
* Added: Admin bar integration - quick toggle to enable/disable note mode
* Added: Element selector generation - automatic CSS selector creation for note attachment
* Added: Notes Manager role - designated user for oversight and management
* Added: Character limit setting - admin-configurable maximum note length
* Added: Settings page - centralised configuration under Settings > Page Notes
* Added: Activity logging - track note creation, edits, and deletions
* Added: AJAX-powered interface - smooth, no-reload interactions
* Added: Secure capability checks - proper WordPress permission validation
* Added: Database schema - custom table for efficient note storage
* Added: Clean uninstall - option to remove all data when plugin is deleted

== Upgrade Notice ==

= 1.4.0 =
Major stability improvements! Element selectors now work with WordPress blocks and page builders. Notes survive content reordering.

= 1.3.0 =
Adds custom Reviewer role for clients. Plugin now only loads on frontend pages.

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

Emails (if enabled) are sent using WordPress `wp_mail()`, which respects any SMTP plugins or mail configuration on your site.

To support privacy requests and data deletion, administrators can:

* Delete notes (via the Page Notes panel on relevant pages)
* Remove plugin user meta/preferences as needed
* Enable full data removal on uninstall in **Settings > Page Notes > Data Management**

== Support ==

For issues, questions, or feature requests:

* Review the plugin documentation (if provided with your install)
* Check browser console/debug logs for technical issues
* Contact your site administrator or development team