# Page Notes Plugin Documentation

Version 1.1.0

## Overview

Page Notes is a WordPress plugin that enables team collaboration by allowing users to attach notes to specific elements on any page or post. Notes are context-aware, threaded, and include features like @mentions, task management, and automated notifications.

---

## Table of Contents

1. [Core Features](#core-features)
2. [Getting Started](#getting-started)
3. [Creating and Managing Notes](#creating-and-managing-notes)
4. [Advanced Features](#advanced-features)
5. [Settings Configuration](#settings-configuration)
6. [Email Notifications](#email-notifications)
7. [User Permissions](#user-permissions)
8. [Best Practices](#best-practices)

---

## Core Features

### Element-Specific Notes
- Attach notes to any HTML element on a page
- Notes are linked to specific page elements using CSS selectors
- Visual highlighting shows which element a note refers to
- Notes persist across page visits and are visible to authorized users

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
- Color-coded warnings (orange at 90%, red at limit)
- Server-side validation prevents abuse

---

## Getting Started

### Enabling Page Notes

**For Administrators:**
1. Go to Settings > Page Notes
2. Configure which user roles can use Page Notes
3. Select a Notes Manager (receives digest emails)
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

**Method 1: Click to Add**
1. Open the Page Notes panel
2. Click the "Add Note" button
3. Click on the page element you want to annotate
4. The form will appear with a text area
5. Type your note (watch the character counter)
6. Click "Save Note"

**Method 2: Quick Note**
1. Simply type in the note form
2. The note will be attached to the current page context
3. No specific element selection required

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
- Color changes to orange at 90% capacity
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

**Behavior:**
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
- Options: Administrator, Editor, Author, Contributor, Subscriber
- Default: Administrator and Editor only
- Multiple selections allowed

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
- Recommended: Enabled
- Ensures assigned users are notified immediately

**Auto-Send Interval:**
- Hours between automatic pending notification batches
- Default: 24 hours
- Range: 1-168 hours (1 week)
- Set to 0 to disable auto-send

**Activity Digest:**
- Enable/disable daily activity summary email
- Sent to Notes Manager
- Sent at same time as task reminders
- Only sent if activity occurred

### Content Settings

**Note Character Limit:**
- Maximum characters allowed per note
- Default: 150 characters
- Recommended range: 150-500
- Set to 0 for no limit
- Prevents overly long notes

### Reminder Settings

**Enable Task Reminders:**
- Send periodic reminders about incomplete notes
- Reminders sent to note creators
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
- Default: Weekly

---

## Email Notifications

### Instant Assignment Notifications

**Triggered by:** @mentions in notes or replies

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

**Triggered by:** Daily at configured reminder time

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

**Subscriber:**
- Must be explicitly enabled
- Basic note functionality only
- Cannot access WordPress admin features

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
- Call-to-action optimization

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
- Cookies/localStorage used for preferences

### Performance
- Notes loaded asynchronously
- Minimal impact on page load
- Activity logging runs in background
- Cron jobs scheduled efficiently

### Data Storage
- Custom database tables for notes and activity
- Standard WordPress options for settings
- User meta for individual preferences
- Automatic cleanup on uninstall

### Security
- Nonce verification on all AJAX requests
- Capability checks for all operations
- XSS prevention via escaping
- SQL injection protection via prepared statements
- Character limit prevents content abuse

---

## Support

For issues, questions, or feature requests, contact your site administrator or development team.

**Common Resources:**
- WordPress Admin: Settings > Page Notes
- User Profile: Reminder preferences
- Activity Digest: Check with Notes Manager
- Technical Issues: Browser console logs

---

*Last Updated: January 2026*
*Plugin Version: 1.1.0*
