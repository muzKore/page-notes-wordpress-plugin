# Page Notes - Future Enhancements

This document tracks potential features and improvements considered for future versions of the Page Notes plugin.

## Status Legend
- 🔵 **Proposed** - Idea stage, needs evaluation
- 🟡 **Under Consideration** - Being evaluated for feasibility
- 🟢 **Planned** - Approved, scheduled for future release
- ⚪ **Deferred** - Not currently prioritized

---

## Multi-User Assignment & Mentions

### Multiple User Tagging (🔵 Proposed)
**Feature:** Support tagging multiple users in a single note with `@mentions`

**Current Limitation:**
- Only the first `@mentioned` user is assigned to a note
- Database schema uses single `assigned_to` column
- Additional tagged users are ignored for assignment and notifications

**Required Changes:**
1. Database: Create many-to-many assignment table
2. Backend: Update `extract_mention_user_id()` to extract all mentions
3. Visibility: Modify visibility logic to check multiple assignees
4. Notifications: Send notifications to all tagged users
5. UI: Display multiple assignee badges
6. API: Update all endpoints to handle arrays of assignees

**Benefits:**
- Better team collaboration on shared tasks
- More flexible task delegation
- Improved visibility for team members

**Considerations:**
- Database migration complexity
- Backward compatibility with existing single assignments
- UI complexity for displaying multiple assignees
- Performance impact on visibility queries

**Related Files:**
- [page_notes_plugin.php:87](../page_notes_plugin.php#L87) - Database schema
- [page_notes_plugin.php:1862-1876](../page_notes_plugin.php#L1862-L1876) - Mention extraction logic
- [page_notes_plugin.php:360](../page_notes_plugin.php#L360) - Visibility rules

---

## Proposed Ideas for Future Versions

### Feature Categories

#### 📝 Note Management
- Priority levels for notes (low/medium/high/critical)
- Note templates for common feedback types
- Bulk operations (bulk status update, bulk delete, etc.)
- Note expiration/auto-archive after completion
- Rich text formatting (bold, italic, lists)

#### 👥 Collaboration
- Team/group mentions (e.g., `@developers`, `@content-team`)
- Note reactions/emoji responses
- Follow/watch feature for notes
- Collaborative editing of notes

#### 🔔 Notifications
- Slack/Discord integration for notifications
- Custom notification preferences per note type
- Digest frequency options (hourly, twice daily, custom)
- In-app notification center

#### 📊 Analytics & Reporting
- Note analytics dashboard
- Response time metrics
- User activity reports
- Export reports to PDF/CSV

#### 🎨 UI/UX Improvements
- Dark mode for notes interface
- Customizable note colors/categories
- Drag-and-drop note positioning
- Keyboard shortcuts
- Mobile-responsive improvements

#### 🔧 Technical Enhancements
- REST API endpoints for external integrations
- Webhooks for note events
- Import/export functionality
- Multi-site support improvements
- Performance optimizations for large datasets

#### 🔒 Security & Permissions
- Custom role capabilities
- Note privacy levels (private/team/public)
- Two-factor authentication for sensitive notes
- Audit log enhancements

---

## Contribution Guidelines

Have an idea for a future enhancement?

1. Check if similar feature already exists in this document
2. Create a GitHub issue with detailed description
3. Include use case and potential benefits
4. Consider implementation complexity and backward compatibility

---

## Version History

- **2026-01-16** - Document created
  - Added: Multiple user tagging feature proposal

