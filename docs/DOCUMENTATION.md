# Page Notes Plugin Documentation

Version 1.4.0

## Overview

Page Notes is a WordPress plugin that enables team collaboration by allowing users to attach notes to specific elements on any page or post. Notes are context-aware, threaded, and include features like @mentions, task management, and optional notifications.

---

## Table of Contents

1. [Core Features](#core-features)
2. [Quick Start: Up and Running in 2 Minutes](#quick-start-up-and-running-in-2-minutes)
3. [Getting Started (Detailed)](#getting-started-detailed)
4. [Creating and Managing Notes](#creating-and-managing-notes)
5. [Advanced Features](#advanced-features)
6. [Settings Configuration](#settings-configuration)
7. [Email Notifications](#email-notifications)
8. [User Permissions](#user-permissions)
9. [Best Practices](#best-practices)
10. [Privacy and Data Handling](#privacy-and-data-handling)
11. [Data Retention and Removal](#data-retention-and-removal)
12. [WordPress.org Readiness Checklist](#wordpressorg-readiness-checklist)

---

## Core Features

### Element-Specific Notes

- Attach notes to any HTML element on a page
- Notes are linked using intelligent CSS selectors optimized for WordPress blocks and page builders
- Visual highlighting shows which element a note refers to
- Notes persist across page visits and are visible to authorised users
- **NEW in v1.4.0:** Selectors survive content reordering and page structure changes
- **NEW in v1.4.0:** Full support for Gutenberg blocks, Elementor, Beaver Builder, Divi, and WPBakery

### Threaded Conversations

- Reply to notes to create discussion threads
- Nested replies maintain conversation context
- Quote previous messages when replying
- Replies are collapsed by default to keep the interface clean
- Linear thread display for easy following of conversations

### Assignment System (@mentions)

- Mention users with @username syntax
- Assigned users receive email notifications
- Assignment badges show who is responsible for each note
- Autocomplete dropdown helps find users quickly
- Search by display name or username

### Task Management

- Mark notes as "Completed" or "Reopen" them
- Visual indicators distinguish completed notes
- Track progress on page-specific tasks
- Prevent deletion of notes with active replies

### Activity Logging

- Track who created, edited, or deleted notes
- Optional daily activity digest email
- Historical record of all note activity
- Sent to designated Notes Manager

### Character Limits

- Admin-configurable character limit per note
- Default limit: 150 characters
- Visual counter shows remaining characters
- Colour-coded warnings (orange at 90%, red at limit)
- Server-side validation prevents abuse

---

## Quick Start: Up and Running in 2 Minutes

**The Simple Setup (Perfect for Developer + Client):**

1. **Install and activate** the plugin
2. **Create a user** with the "Page Notes Reviewer" role (or use an existing Editor/Admin)
3. **Send them the login details**
4. **They're ready!** They can now leave notes on any page

**That's it.** No configuration needed. No settings to adjust. Just start leaving notes.

### How to Use (The Basics)

**For the person leaving notes (client/reviewer):**
1. Log in to WordPress
2. Visit any page on the site
3. Click the **Page Notes** button in the top admin bar
4. Click **Add Note**, then click any element on the page
5. Type your feedback and click **Save Note**
6. Optional: Click **Send Notifications** when done to notify the developer

**For the developer:**
- Just log in and you'll see all the notes on each page
- No notification needed - you can see them whenever you visit the page
- Mark notes as "Completed" when you've addressed them

### Optional Features (Use Only If You Need Them)

**@Mentions** - Type `@username` to assign a note to someone specific
- Without @mentions: Notes are general feedback anyone can see
- With @mentions: Notes are assigned to that person

**Email Notifications** - Can be enabled in Settings > Page Notes
- Default: OFF (people check notes manually)
- If enabled: People get emailed when mentioned or when someone replies

**All the other features** (reminders, digests, activity logs, etc.) are optional extras for larger teams. Ignore them if you don't need them.

---

## Getting Started (Detailed)

### Enabling Page Notes

**For Administrators:**

1. Go to Settings > Page Notes
2. Configure which user roles can use Page Notes
3. Select a Notes Manager (receives digest emails) - Optional
4. Save settings

**For Individual Users:**

1. Visit any page on the site
2. Look for the "Page Notes" button in the admin toolbar
3. Click to open the notes panel
4. If you don't see notes, check with your administrator

### First Note

1. Click "Add Note" in the notes panel
2. Click on any element on the page (text, image, button, etc.)
3. The element will be highlighted
4. Enter your note in the popup form
5. Optional: Type @ to mention someone
6. Click "Save Note"

---

## Creating and Managing Notes

### Adding a Note

1. Open the Page Notes panel
2. Click the "Add Note" button
3. Click on the page element you want to annotate
4. The form will appear with a text area
5. Type your note (watch the character counter)
6. Click "Save Note"

**Note:** All notes must be attached to a specific page element. You cannot create a note without selecting an element.

### Editing Notes

1. Locate the note you want to edit
2. Click the "Edit" button
3. Modify the content in the form
4. Click "Save Note"

**Restrictions:**

- You can only edit your own notes
- Character limit still applies

### Deleting Notes

1. Find the note you want to delete
2. Click the "Delete" button
3. Confirm deletion

**Restrictions:**

- You can only delete your own notes
- Notes with replies cannot be deleted (delete replies first)
- Delete button is disabled for notes with active threads

### Replying to Notes

1. Click the "Reply" button on any note
2. The original note content is automatically quoted
3. Type your response below the quote
4. Optional: Add @mentions to assign tasks
5. Click "Save Note"

**Reply Features:**

- Quoted text appears in styled blockquotes
- "replying to [Name]" labels show conversation flow
- Nested replies maintain context
- Expand/collapse threads with the toggle button

### Completing Tasks

1. Click "Complete" on any note or reply
2. The note is marked with a green checkmark
3. Completed notes remain visible but are visually distinct
4. Click "Reopen" to restore active status

---

## Advanced Features

### Element Selector Stability (v1.4.0)

Page Notes uses an intelligent selector generation system to ensure notes remain attached to the correct elements even when your page structure changes.

**Selector Strategy (in priority order):**

1. **Element ID** - If an element has a unique ID, that's used (most stable)
2. **WordPress Blocks** - Detects Gutenberg block classes and data attributes
3. **Page Builder Attributes** - Recognizes Elementor, Beaver Builder, Divi, WPBakery attributes
4. **Parent ID + Path** - Finds nearest parent with ID and builds relative path
5. **Data Attributes** - Uses existing stable data attributes
6. **Stable CSS Classes** - Filters out dynamic/temporary classes
7. **Permanent Marker** - Adds a unique data attribute as ultimate fallback

**Benefits:**

- Notes survive block reordering and content restructuring
- Works with WordPress Block Editor (Gutenberg)
- Compatible with all major page builders
- Avoids fragile position-based selectors (nth-child, nth-of-type)
- Adapts to different content management systems

**What This Means:**

- You can reorder blocks without breaking note attachments
- Adding new content above/below doesn't affect existing notes
- Page structure changes don't orphan notes
- Notes remain accurate across theme changes (in most cases)

### @Mention System

**How to Use:**

1. Type @ in any note or reply
2. An autocomplete dropdown appears
3. Start typing a name or username
4. Select from the filtered list
5. The mention is added as @username

**What Happens:**

- Mentioned user receives an email notification
- An assignment badge shows who is responsible
- Multiple mentions are supported
- Mentions are stripped from display (shown in badge instead)

**Autocomplete Features:**

- Search by display name or username
- Real-name display with username in parentheses
- Arrow keys to navigate suggestions
- Enter to select, Escape to close
- Click to select from list

### Quoted Replies

When you reply to a note, the original content is automatically quoted:

```
> John Doe wrote:
> Original message text here

Your reply goes here
```

**Quote Formatting:**

- Visual blockquote styling with decorative quote mark
- Author attribution line
- Distinguishable from new content
- Preserves original formatting

### Notifications System

**Instant Notifications:**

- Sent immediately when a note includes @mentions
- Email contains note content and link to page
- Sent to all mentioned users

**Pending Notifications:**

- Notes without @mentions queue for batch sending
- "Send Pending Notifications" button in panel
- Admin or Notes Manager can trigger manually
- Includes automatic send at configured intervals

**Activity Digest:**

- Daily summary of all note activity
- Sent to designated Notes Manager
- Includes created, edited, and deleted notes
- Only sent if activity occurred in last 24 hours

### Character Counter

**Visual Feedback:**

- Live counter shows: "45 / 150 characters"
- Updates as you type
- Colour changes to orange at 90% capacity
- Turns red when at limit

**Enforcement:**

- Browser prevents typing beyond limit
- Server validates all submissions
- Configurable by administrator
- Set to 0 for unlimited characters

### Context Notes

Some notes are shown "for context only" when viewing threaded conversations:

**What are Context Notes:**

- Notes from other pages shown to maintain thread continuity
- Displayed when a reply chain spans multiple pages
- Marked with "Context" badge
- Dimmed appearance to distinguish from current page notes

**Behaviour:**

- No action buttons (read-only)
- Cannot edit or delete
- Provide conversation history
- Help understand discussion flow

---

## Settings Configuration

Navigate to: **Settings > Page Notes**

### Role Access Settings

**Allowed Roles:**

- Select which user roles can create and view notes
- Options: Administrator, Editor, Author, Contributor
- Default: Administrator and Editor only
- Multiple selections allowed
- Note: All users must have the `edit_posts` capability (Subscribers do not have this by default)

**Individual User Access:**

- Enable/disable notes for specific users
- Overrides role settings
- Useful for contractors or limited access
- Managed per-user in profile settings

### Notification Settings

**Notes Manager:**

- User who receives activity digests
- Typically project manager or team lead
- Dropdown of all users
- Required for digest emails

**Instant Email:**

- Enable/disable instant notifications for @mentions
- **Disabled by default** - administrators can enable this if they want assignment notifications sent immediately
- When enabled, assigned users receive email notifications as soon as they are mentioned

**Auto-Send Interval:**

- Hours between automatic pending notification batches
- Default: 24 hours
- Range: 1-168 hours (1 week)
- Set to 0 to disable auto-send

**Activity Digest:**

- Enable/disable daily activity summary email
- **Disabled by default** - administrators must enable
- Sent to Notes Manager
- Sent at same time as task reminders
- Only sent if activity occurred
- **Ad-hoc send:** Administrators can click "Send Activity Digest Now" button in settings to immediately send a digest of the last 24 hours

### Content Settings

**Note Character Limit:**

- Maximum characters allowed per note
- Default: 150 characters
- Recommended range: 150-500
- Set to 0 for no limit
- Prevents overly long notes

### Reminder Settings

**Enable Task Reminders:**

- **Disabled by default** - administrators must enable
- Send periodic reminders about incomplete tasks
- Reminders sent to assigned users (via @mentions)
- Users must opt-in via their profile settings
- Based on configured schedule

**Reminder Time:**

- Time of day to send reminders (24-hour format)
- Example: 09:00 for 9 AM
- Uses WordPress timezone setting
- Same time used for activity digest

**Reminder Interval (per user):**

- How often each user receives reminders
- Options: Daily, Every 3 days, Weekly
- User-configurable in profile
- Default: Weekly (when user opts in)

### Data Management Settings

**Uninstall Behaviour:**

- Controls whether data is deleted when plugin is uninstalled
- Default: Unchecked (data is preserved)
- When unchecked: All notes, settings, and user preferences are kept in the database if you uninstall the plugin
- When checked: Uninstalling will permanently delete all plugin data including:
  - Custom database tables (notes, activity logs, completions)
  - All plugin settings and options
  - User preferences and metadata
- Scheduled cron jobs are always cleared on uninstall regardless of this setting
- Recommendation: Leave unchecked unless you are certain you want to permanently remove all data

---

## Email Notifications

### Instant Assignment Notifications

**Triggered by:** @mentions in notes or replies

**Note:** This notification type is disabled by default and must be enabled by an administrator.

**Content includes:**

- Who mentioned you
- The complete note content
- Page title and URL
- Direct link to view the note
- Timestamp

**Example:**

```
Subject: You've been mentioned in a note on "Homepage"

John Doe mentioned you in a note:

"Can you review the header image? @jane It seems
pixelated on mobile."

Page: Homepage
View note: [link]
```

### Pending Notification Batches

**Triggered by:** Manual send or auto-send interval

**Content includes:**

- List of all pending notes
- Page context for each note
- Author information
- Links to view each note

**Use cases:**

- Daily team updates
- End-of-week summaries
- Project milestone reviews

### Activity Digest Email

**Triggered by:**
- Automatic: Daily at configured reminder time (when enabled)
- Manual: Click "Send Activity Digest Now" button in Settings > Page Notes

**Sent to:** Notes Manager only

**Content includes:**

- Notes created in last 24 hours
- Notes edited/updated
- Notes deleted
- Full content and context
- Grouped by activity type

**Example sections:**

- "Notes Created" (with author, page, content)
- "Notes Updated" (with editor, old/new content)
- "Notes Deleted" (with content preserved)

**Manual Send Feature:**

- Available in Settings > Page Notes under "Daily Activity Digest"
- Administrators can send an immediate digest
- Covers activity from the last 24 hours
- Useful for project reviews or milestone reports
- Does not require automatic digest to be enabled

### Task Reminder Emails

**Triggered by:** User's configured reminder interval

**Content includes:**

- Your incomplete notes across all pages
- Page context for each note
- How long each note has been open
- Links to view and complete

---

## User Permissions

### By Role

**Administrator:**

- Full access to all features
- Configure plugin settings
- View all users' notes
- Edit/delete any note
- Send notifications
- Access activity logs

**Editor:**

- Create, edit, delete own notes
- View notes from others
- Reply to any note
- Complete/reopen tasks
- Cannot access settings

**Author/Contributor:**

- Same as Editor (if role is enabled in settings)
- Limited by role access configuration

**Page Notes Reviewer (NEW in v1.3.0):**

- Custom role designed for client access
- Can view the site and add notes
- **Cannot** access WordPress admin dashboard
- **Cannot** edit posts, pages, or any content
- **Cannot** install plugins or modify site settings
- Has `use_page_notes` capability but not `edit_posts`
- Perfect for clients providing feedback without full site access

**Note on Subscribers:**

- Subscribers do not have the `edit_posts` or `use_page_notes` capability by default
- Subscribers cannot use Page Notes unless you assign them the Page Notes Reviewer role or grant them appropriate capabilities
- If you need client/stakeholder commenting, consider creating a custom role with limited capabilities rather than granting `edit_posts` to Subscribers

### Individual Access Control

Managed in user profile settings:

**Page Notes Enabled:**

- Override for specific users
- Can disable notes for individual users
- Can enable for users whose role is disabled

**Reminder Preferences:**

- Each user controls their reminder frequency
- Options: Daily, Every 3 days, Weekly
- Personal preference, doesn't affect others

### Note Ownership Rules

**You can only edit/delete:**

- Notes you created
- Not notes assigned to you
- Not notes you're mentioned in

**Everyone can:**

- View all notes (if they have plugin access)
- Reply to any note
- Complete/reopen any note
- Mention any user

---

## Best Practices

### Writing Effective Notes

**Be Specific:**

- Attach notes to the exact element you're referencing
- Use clear, concise language
- Include actionable items

**Use @Mentions:**

- Assign responsibility clearly
- Mention relevant team members
- Ensures notifications are sent

**Keep Notes Short:**

- Character limits encourage brevity
- Use replies for detailed discussions
- One topic per note

### Managing Conversations

**Use Replies:**

- Keep related discussions together
- Don't create duplicate notes
- Maintains conversation history

**Complete When Done:**

- Mark resolved notes as complete
- Don't delete unless truly irrelevant
- Completed notes provide project history

**Quote Context:**

- Use quoted replies for clarity
- Reference specific points
- Makes threads easier to follow

### Team Workflow

**Daily Routine:**

1. Check notes panel for new activity
2. Review @mentions and assignments
3. Reply to questions and updates
4. Complete resolved items
5. Create notes for new issues

**Project Manager:**

1. Review activity digest email daily
2. Send pending notifications at milestones
3. Monitor completion rates
4. Archive completed pages

**Design Review Process:**

1. Designer creates notes on mockups
2. @mentions stakeholders for feedback
3. Stakeholders reply with comments
4. Designer marks items complete when fixed
5. Final review confirms all notes resolved

### Organizing Notes by Page

**Homepage:**

- Header/navigation issues
- Hero section feedback
- Call-to-action optimisation

**Product Pages:**

- Image quality notes
- Description clarity
- Pricing display issues

**Checkout Flow:**

- Form validation bugs
- Button placement feedback
- Mobile responsive issues

### Troubleshooting

**Can't see notes:**

- Check if your role is enabled in settings
- Verify individual access isn't disabled
- Ensure you're logged in
- Clear browser cache

**Not receiving emails:**

- Check spam folder
- Verify WordPress email configuration
- Confirm instant email is enabled in settings
- Test with site email functionality

**Notes not saving:**

- Check character limit
- Verify you have permission
- Check browser console for errors
- Ensure JavaScript is enabled

**Element highlighting not working:**

- Element may have been removed/changed
- CSS selector may be too specific
- Try creating a new note on updated element
- Old notes retain original selector

---

## Technical Notes

### Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript required
- localStorage used for UI preferences only

### Performance

- Notes loaded asynchronously
- Minimal impact on page load
- Activity logging runs in background
- Cron jobs scheduled efficiently

### Data Storage

- Custom database tables for notes and activity
- Standard WordPress options for settings
- User meta for individual preferences
- Data is preserved on uninstall by default
- Administrators can opt in to full data removal in Settings → Page Notes → Data Management

### Security

- Nonce verification on all AJAX requests
- Capability checks for all operations
- XSS prevention via escaping
- SQL injection protection via prepared statements
- Character limit prevents content abuse

---

## Privacy and Data Handling

### What Data Is Stored

Page Notes stores the following data in your WordPress database:

**Notes Data:**
- Note content (text you write)
- CSS selectors (to identify page elements)
- Page URLs and IDs
- User IDs (who created/edited notes)
- Timestamps (creation and modification dates)
- Assignment data (@mentions)
- Status (open/completed)

**Activity Logs:**
- User actions (created, edited, deleted)
- Previous and new content (for edit history)
- Timestamps of all actions

**Plugin Settings:**
- Role permissions
- Notes Manager assignment
- Email notification preferences
- Character limits
- Reminder schedules

**User Preferences:**
- Individual access overrides
- Personal reminder frequency settings
- Last reminder sent timestamps

### What Data Is Sent Via Email

Email notifications may include:

- Note content (the text of the note)
- Page titles and URLs
- Author display names
- Timestamps
- Direct links back to your WordPress site

**Important:** All emails are sent using WordPress's built-in `wp_mail()` function. No third-party email services are used unless you have configured them separately in WordPress.

### Third-Party Data Sharing

**Page Notes does NOT:**
- Send any data to external servers
- Use any tracking or analytics services
- Make any external API calls
- Share data with third parties
- Include telemetry or usage tracking

All data remains within your WordPress installation.

### Browser Storage (localStorage/Cookies)

Page Notes uses browser localStorage only for:

- User interface preferences (panel visibility)
- Temporary form state (draft notes before saving)

**No tracking cookies are set.** All data is stored locally in the user's browser and is never sent to external services.

### Privacy Requests and Data Removal

Page Notes provides tools and documentation to support privacy requests (such as data access or deletion requests).

**When a user requests their data be removed:**

1. **For individual user data removal:**
   - Administrators can manually delete notes authored by that user (via the Page Notes panel on relevant pages)
   - Administrators can clear the user's Page Notes user meta from their WordPress profile
   - The user's WordPress account can then be deleted following standard WordPress procedures

2. **For complete plugin data removal (all users):**
   - Enable "Remove all plugin settings and data when uninstalled" in Settings → Page Notes → Data Management
   - Uninstall the plugin to permanently delete all data

**Important considerations:**
- Notes are often part of team conversations, so removing a user's notes may affect project history
- Consider anonymizing notes instead of deleting them if they contain critical context
- Full plugin uninstall affects all users' data, not just one individual

---

## Data Retention and Removal

### What Happens on Deactivation

When you deactivate Page Notes:

- **All data is preserved** (notes, settings, user preferences)
- Database tables remain intact
- Scheduled cron jobs are cleared
- The plugin UI is no longer visible
- Reactivating the plugin restores full functionality with all data intact

### What Happens on Uninstall

**Default Behaviour (Recommended):**

When you uninstall Page Notes with the default settings:

- **All data is preserved** in the database
- Database tables remain intact with all notes and activity logs
- Plugin settings and user preferences are retained
- Scheduled cron jobs are cleared
- You can reinstall the plugin later and all data will be available

**Opt-In Full Removal:**

If you enable "Remove all plugin settings and data when uninstalled" in Settings → Page Notes → Data Management:

- All custom database tables created by the plugin (using your site's `$wpdb->prefix`) are dropped
- All plugin settings are deleted
- All user preferences are removed
- This action is **permanent and cannot be undone**

### Manual Data Removal

To manually remove specific data:

1. **Delete Individual Notes:** Click the delete button on any note you created
2. **Remove All Plugin Data:** To remove all plugin data, enable "Remove all plugin settings and data when uninstalled" in Settings → Page Notes → Data Management, then uninstall the plugin (this permanently removes all plugin data). **Back up your database before enabling full removal.**
3. **Export Before Deletion:** WordPress does not provide a built-in export for custom plugin data. Contact your developer if you need to export notes before deletion.

### Data Portability

Page Notes data is stored in standard WordPress database tables using `$wpdb`. If you need to export or migrate data:

- Tables are created with your site's `$wpdb->prefix`, e.g., `wp_page_notes`, `wp_page_notes_activity`, and `wp_page_notes_completions` on a default install
- Standard SQL export tools can backup this data
- Contact a WordPress developer for custom export scripts if needed

---

## Support

For issues, questions, or feature requests, contact your site administrator or development team.

**Common Resources:**

- WordPress Admin: Settings > Page Notes
- User Profile: Reminder preferences
- Activity Digest: Check with Notes Manager
- Technical Issues: Browser console logs

---

## WordPress.org Readiness Checklist

This section documents compliance with WordPress.org plugin directory requirements and best practices.

### ✅ Security

- **Nonce verification:** All AJAX requests verify nonces using `check_ajax_referer()`
- **Capability checks:** All AJAX handlers check `current_user_can('edit_posts')` and user permissions
- **Input sanitization:** All user input is sanitized using `sanitize_text_field()`, `wp_kses_post()`, and `intval()`
- **Output escaping:** All output uses `esc_html()`, `esc_attr()`, `esc_url()`
- **SQL injection prevention:** All database queries use `$wpdb->prepare()` with parameterized statements
- **XSS prevention:** Content is escaped before display, HTML is filtered through `wp_kses_post()`

### ✅ Privacy and Data Handling

- **No external calls:** Plugin does not make any requests to external servers
- **No tracking/telemetry:** No usage data or analytics are collected or transmitted
- **No third-party services:** All functionality runs within WordPress
- **localStorage only:** Browser storage used only for UI preferences (panel visibility)
- **No tracking cookies:** Plugin does not set any tracking or analytics cookies
- **Supports privacy requests:** Clear documentation on what data is stored and how to remove it
- **Transparent data retention:** Data is preserved on uninstall by default with opt-in deletion

### ✅ Email Behaviour

- **Conservative defaults (all opt-in):**
  - Instant emails: **Disabled by default** (admin must enable)
  - Activity digest: **Disabled by default** (admin must enable)
  - Task reminders: **Disabled by default** (admin must enable globally, users must opt-in via profile)
- **WordPress mail function:** All emails use `wp_mail()` (no external email services)
- **User control:** Clear settings to disable each notification type
- **No spam risk:** All automated email sending is opt-in, and admins can manually trigger digests/batches on-demand
- **Reminder behaviour:** Only sends to users who have incomplete tasks assigned to them (via @mentions) AND have opted in
- **Manual controls:** Pending notifications and digests can be sent on-demand by admins/Notes Manager

### ✅ User Interface

- **Capability-based access:** UI only loads for users with `edit_posts` capability
- **No admin hijacking:** No persistent banners across unrelated admin screens
- **No marketing notices:** No promotional messages or upgrade nags
- **Minimal injection:** UI appears only as admin bar button and opt-in overlay panel
- **No forced branding:** Plugin respects WordPress admin design patterns

### ✅ Code Quality

- **CSS selector safety:** `querySelector()` wrapped in try-catch, graceful failure if element not found
- **Selector resilience:** Notes remain visible even if original element is removed from page
- **No reliance on external libraries:** Pure vanilla JavaScript (no jQuery dependency)
- **Proper WordPress hooks:** Uses standard `add_action()`, `add_filter()` patterns
- **Database best practices:** Uses `dbDelta()` for table creation, `$wpdb` for queries

### ✅ Licensing and Distribution

- **GPL v2 or later:** Plugin is GPL-licensed and compatible with WordPress.org
- **No license checks:** Plugin operates fully without external license validation
- **No "phone home":** No activation, deactivation, or usage tracking calls
- **No paid-only features hidden:** This is a complete, functional plugin (no artificial limitations)

### ✅ Uninstall Behaviour

- **Data preserved by default:** Uninstalling does NOT delete data unless explicitly opted in
- **Clear user control:** Admin setting to enable full data removal on uninstall
- **Proper cleanup:** Cron jobs always cleared, even when preserving data
- **Transparent behaviour:** Documentation clearly explains deactivate vs uninstall

### Permissions Model

**Access is controlled by WordPress capabilities:**

- **View/Create Notes:** `edit_posts` capability + individual user access setting
- **Manage Settings:** `manage_options` capability (administrators only)
- **Send Notifications:** `manage_options` or Notes Manager user
- **Delete Own Notes:** User must be the note creator
- **View All Notes:** Notes Manager role OR administrator

**Role-based defaults:**
- Administrators: Full access (always enabled)
- Editors: Access if enabled in settings
- Authors/Contributors: Access if enabled in settings (have `edit_posts` capability)
- Subscribers: Cannot use Page Notes (lack `edit_posts` capability)

### Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- JavaScript required (no fallback for non-JS environments)
- Uses standard ES6+ features with graceful degradation

### Performance Considerations

- Notes loaded asynchronously (no page load blocking)
- Database queries use indexed fields (`page_id`, `user_id`, `created_at`)
- Cron jobs scheduled at appropriate intervals (no minute-by-minute tasks)
- UI elements loaded only for authorised users

---

_Last Updated: January 2026_
_Plugin Version: 1.4.0_
