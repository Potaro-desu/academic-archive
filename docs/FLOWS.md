# System Flows and User / Permission Matrix - Academic Archiving System v1.3

The v1.3 dashboard presentation order is role-specific: admin review queue,
archive overview, then user and audit links; faculty review queue, own
contributions, then campus collection; student coursework, published-work
browsing, then the short how-it-works guide. These are presentation changes
only. Submission, permission, and database behavior remain unchanged.

## Flow A - Student / Faculty submission

1. The student or faculty member signs in.
2. They open the submission form and choose Upload File or Submit Link.
3. They enter metadata: title, description, keywords, category, academic year.
4. They choose Public or Private.
5. The server validates everything (required fields, academic year in YYYY-YYYY form, file type and size, MIME type compared with the extension, URL scheme). The form can also be submitted over fetch, with a progress state, a success state, and an error state shown without a reload.
6. The file is stored under a random name, or the link is stored with its metadata. Status becomes Pending.
7. An admin or a faculty reviewer (for pending public student work) reviews the record: approve, reject, or return with feedback. Feedback is required for reject and return.
8. The owner gets a notification on their dashboard naming the record and the decision, and sees the new status and feedback in My Submissions.
9. If the record was Returned, the owner can edit it and save it, which sends it back to Pending for a fresh review.
10. The owner can follow their records on the My Submissions page (sort, visibility filter, favorites filter) and favorite any record they can open.

## Flow B - Admin / Clerk submission

1. The admin or clerk signs in.
2. They open the submission form and attach a file or a web link, add metadata, and choose visibility.
3. They choose whether to publish immediately (checkbox, admin only, unchecked by default) or send the record for review.
4. Unchecked: the record goes Pending like everyone else and must be approved through the review queue.
5. Checked: the record is Approved and published at once, and appears in the public catalog if it is Public.
6. The admin can also edit or delete any record, and review the pending queue from the dashboard.

## Flow C - Search and access

1. A visitor or signed-in user searches the public catalog, with optional filters for category, type, academic year, and sort order. The catalog is a card grid: image records show their real image as the thumbnail, every other type gets a labelled colour tile.
2. The catalog lists only Approved + Public records. Pending, Rejected, Returned, and Private records never appear, in results, lists, or counts.
3. The user opens a record detail page.
4. The server checks permission again: Approved + Public for everyone, otherwise owner, admin, or an authorized faculty reviewer only.
5. If allowed, the page shows the preview or player (PDF, video, audio, image) or a safe download or open-link action. Link records show their destination domain. A real file download adds 1 to the record's download count; a forbidden request never counts. Signed-in users can favorite the record.
6. If not allowed, the page returns HTTP 403 with a clear access-denied message, and no file is served.
7. Changing a record from Public to Private hides it from the public catalog, preview, and download at once, because the permission check reads the current visibility on every request and stored files move folders with the visibility.

## User / Permission matrix

| Action | Public Visitor | Student | Faculty | Admin / Clerk |
|---|---|---|---|---|
| Browse and search the public catalog | Yes, Approved + Public only | Yes | Yes | Yes |
| Open an Approved + Public record, preview, download | Yes | Yes | Yes | Yes |
| Open a Pending record | No (403) | Only own records | Only pending public student records, for review | Yes |
| Open a Private record | No (403) | Only own records | No (403) | Yes |
| Submit a file or web link | No | Yes | Yes | Yes |
| Choose Public or Private on submission | No | Yes | Yes | Yes |
| View own submissions and statuses | No | Yes | Yes | Yes |
| Edit own Pending or Returned record | No | Yes | Yes | Yes (any record) |
| Delete own Pending, Returned, or Rejected record | No | Yes | Yes | Yes (any record) |
| Review the pending queue (approve, reject, return) | No | No | Pending public student records only | Yes, all records |
| Publish an admin record immediately | No | No | No | Yes, with the checkbox |
| Manage users and see submission history | No | No | No | Yes |
| View the audit log | No | No | No | Yes |
| See other users' private records | No | No | No | Yes |
| Favorite a record | No | Yes | Yes | Yes |
| See download counts | On each record | On each record | On each record | On each record and in the dashboard totals |

## Visibility and publication rules

- Public: the submitter requests public availability. The record becomes discoverable and openable by everyone only after approval or publication. Before that it stays Pending and hidden from visitors.
- Private: the record is visible only to its owner and to an authorized admin or clerk. It never appears in public search results, lists, or counts.
- Status: Pending, Approved / Published, Rejected, Returned for Revision. The current state is shown as a badge on every list and detail page where the viewer is allowed to see it.
- Every review decision is recorded in `audit_logs` with who did it and when, and the owner is notified on their dashboard.
- Sign-in lockout: 5 failed attempts lock an account for 15 minutes. While locked, even the correct password is refused with a generic message. A successful sign-in clears the failure count.
- The audit log can be viewed by admins on `admin-audit.php`, newest first, with action and user filters.
