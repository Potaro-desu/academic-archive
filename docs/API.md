# API and Action Documentation - Academic Archiving System v1.2

All pages are plain PHP. Form actions are POST requests that redirect with a
flash message, unless the request asks for JSON (fetch with
`Accept: application/json`), in which case the action returns a JSON
response instead. Every protected route re-checks the permission on the
server; hiding a link or button is never the only protection.

## Conventions

- Sessions: PHP sessions, cookie name `academic_archive_session`, HttpOnly,
  SameSite Lax, Secure when on HTTPS. Session id is regenerated on login.
- CSRF: every POST form carries a `csrf_token` hidden field. A missing or
  wrong token returns HTTP 419.
- Auth: passwords are stored with `password_hash` and checked with
  `password_verify`. No plain-text passwords anywhere.
- Flash messages: after a POST action the user is redirected and sees one
  success or error message at the top of the next page.
- File serving: uploads live in `uploads/public/` and `uploads/private/`,
  both blocked by `.htaccess` (`Require all denied`). Files are served
  only through `api/download.php` after a permission check.

## Pages (GET)

| Page | Who can open it | What it shows |
|---|---|---|
| `index.php` | Everyone, including visitors | Public catalog: Approved + Public records only, shown as a card grid (3 columns on desktop, 2 on tablet, 1 on phones). Image records show their real image as the card thumbnail; every other type gets a labelled colour tile. Search by title, description, or keywords; filter by category, type, academic year; sort newest, oldest, or title A to Z. All filters stay in the URL. |
| `view-resource.php?id=N` | Owner, admin, faculty reviewer (pending public student records only), or anyone for Approved + Public records. Everyone else gets HTTP 403. | One record with full metadata, preview or player (PDF iframe, video, audio, image), or safe download. Link records show their destination domain. Download count is shown. Signed-in users can favorite the record. Review panel for reviewers, with async approve, reject, and return. Edit and Delete actions for authorized users only. |
| `login.php` | Visitors (signed-in users are sent to the dashboard) | Sign-in form. |
| `register.php` | Visitors | Self-registration for student or faculty only. Admin accounts cannot be created here. |
| `submit.php` | Signed-in student, faculty, or admin | File or web link submission form with Public or Private choice. Admins also see a "Publish immediately" checkbox. |
| `edit.php?id=N` | Owner of a Pending or Returned record, or admin for any record. Others get HTTP 403. | Prefilled edit form for title, description, keywords, category, academic year, visibility, and optional file or link replacement. |
| `dashboard.php` | Signed-in users | Role dashboard: admin review queue and counts (including total downloads), faculty review queue and own submissions, student own submissions. All roles see a notifications section and a My favorites section. My Submissions can be sorted and filtered by visibility, with a link to the full page. |
| `admin-users.php` | Admin only (others get HTTP 403) | User directory with account search and each account's submission history. |
| `my-submissions.php` | Signed-in users | The signed-in user's own records on one page, with status badges, feedback, download counts, sort, visibility filter, and favorites-only filter. Edit and Delete links for authorized records. |
| `admin-audit.php` | Admin only (others get HTTP 403) | Audit log, newest first, with action and user filters and 50 entries per page. |

## Actions (POST)

### `api/auth.php` - action=login

- Inputs: `email`, `password`.
- Permission: anyone. A failed login waits briefly and shows a generic error.
- Lockout: after 5 failed attempts the account is locked for 15 minutes. While locked, even the correct password is refused with a generic "temporarily locked" message that does not reveal the unlock time. A successful login resets the failure count and clears the lock. Lockout events are written to `audit_logs` (`account_locked`, `login_locked`).
- Success: session created, redirect to `dashboard.php`. A login row is written to `audit_logs`.

### `api/auth.php` - action=register

- Inputs: `name`, `email`, `role` (student or faculty only), `password`, `password_confirm` (minimum 10 characters, must match).
- Permission: anyone. `role=admin` is rejected.
- Success: account created, signed in at once, redirect to `dashboard.php`.

### `api/auth.php` - action=logout

- Inputs: none apart from the CSRF token.
- Permission: signed-in user. Destroys the session, redirect to `index.php`.

### `api/submit.php` - create a record

- Inputs: `title`, `description`, `keywords`, `category_id`, `academic_year`, `resource_type` (file or link), `resource_file` (when file), `external_url` (when link), `visibility` (public or private). Admins may also send `publish_now=1`.
- Permission: signed-in student, faculty, or admin.
- Validation (server side): title and description required with length limits, category must exist, academic year must be empty or in the form YYYY-YYYY with the second year one after the first (for example 2025-2026), file extension must be in the allowlist (19 types), file size 0 to 100 MB, MIME type detected with finfo and compared with the extension (a renamed file is rejected with a clear error and no record is created), URL must be valid HTTP or HTTPS with a host.
- Behaviour: files are stored under a random 48 character hex name in `uploads/public/` or `uploads/private/` by visibility. Student and faculty records start Pending. Admin records start Pending unless `publish_now` is ticked, in which case they are Approved and published at once.
- Success: redirect to `dashboard.php` with a success message, or, when the request asks for JSON, `{"success": true, "id": N, "status": "pending", "message": "...", "url": "view-resource.php?id=N", "redirect": "../dashboard.php"}`. Validation failures return HTTP 422 with `{"success": false, "message": "..."}`. An `audit_logs` row is written.

### `api/edit.php` - edit a record

- Inputs: `submission_id`, `title`, `description`, `keywords`, `category_id`, `academic_year`, `resource_type`, `resource_file` (optional replacement), `external_url` (optional replacement), `visibility`.
- Permission: the owner, only while the record is Pending or Returned, or admin for any record. Others get HTTP 403.
- Validation (server side): same rules as submit, including the academic year format and the MIME type check when a replacement file is given.
- Behaviour: replacing the file or link is optional; when nothing new is given the existing file or link is kept. A visibility change moves a stored file between `uploads/public/` and `uploads/private/` at once, so public preview and download stop working immediately when a record becomes Private. An owner saving a Returned record sends it back to Pending and clears the old feedback. An admin edit never changes the review status.
- Success: redirect to `view-resource.php?id=N` with a success message. An `audit_logs` row (`submission_edited`) is written.

### `api/delete.php` - delete a record

- Inputs: `submission_id`.
- Permission: the owner, only while the record is Pending, Returned, or Rejected, or admin for any record. Others get HTTP 403.
- Behaviour: deletes the database row AND the stored file, then writes an `audit_logs` row (`submission_deleted`).
- Success: redirect to `dashboard.php` with a success message.

### `api/review.php` - approve, reject, or return

- Inputs: `submission_id`, `decision` (approved, rejected, or returned), `feedback`.
- Permission: admin for any record, or faculty for pending public student submissions only. Others get HTTP 403.
- Validation: feedback is required for rejected and returned decisions; approving with empty feedback is allowed. A re-review with empty feedback keeps the existing feedback instead of wiping it.
- Behaviour: sets status, feedback, reviewed_by, reviewed_at, and published_at when approved. Writes an `audit_logs` row and a notification for the owner.
- Success: redirect to the detail page (admin) or the dashboard review queue (faculty). When the request asks for JSON, returns `{"success": true, "status": "approved", "status_label": "Approved / Published", "feedback": "...", "message": "..."}` and the detail page updates the status badge in place. Validation failures return HTTP 400 or 422 with `{"success": false, "message": "..."}`.

### `api/download.php?id=N&inline=1` - download or preview a file

- Inputs: `id`, optional `inline=1` for in-browser preview.
- Permission (server side): the record must be Approved + Public, or the requester must be the owner, an admin, or a faculty reviewer of that record. Everyone else gets HTTP 403. The stored file name is checked against a strict pattern first.
- Behaviour: streams the file with the stored MIME type. Inline preview only for PDF, MP4, WebM, MP3, WAV, M4A, JPG, JPEG, PNG, GIF, and WebP; every other type is sent as an attachment download. A successful attachment download (not an inline preview) adds 1 to the record's `download_count`. Forbidden or missing files never count.
- Errors: HTTP 404 when the record or the stored file does not exist.

### `api/favorites.php` - favorite or unfavorite

- Inputs: `submission_id`.
- Permission: signed-in user who is allowed to open the record (same rule as the detail page). Others get HTTP 403.
- Behaviour: adds a `favorites` row, or removes it when the record is already favorited. Writes an `audit_logs` row (`favorite_added` or `favorite_removed`).
- Success: redirect to the detail page with a success message, or, when the request asks for JSON, `{"success": true, "favorited": true, "message": "..."}`.

### `api/notifications.php` - action=mark_all_read

- Inputs: `action=mark_all_read`.
- Permission: signed-in user. Only that user's own notifications are changed.
- Behaviour: marks all of the current user's unread notifications as read.
- Success: redirect to `dashboard.php` with a success message.

## JSON endpoint

### `api/search.php` - public catalog search (GET, JSON)

- Inputs (all optional): `q` (search text), `category` (category id), `type` (file or link), `year` (academic year), `sort` (newest, oldest, or title; default newest).
- Permission: none needed. Returns only Approved + Public records, exactly like `index.php`.
- Success (HTTP 200): `{"success": true, "data": [...], "count": N}`. Each item has `id`, `title`, `description` (trimmed), `category`, `file_type`, `academic_year`, `author`, `resource_type`, `url` (the detail page link), `thumbnail_url` (image records only, the inline download URL, otherwise `null`), `tile_label`, and `tile_class` (the labelled colour tile for non-image records).
- Errors: non-GET requests return HTTP 405 with `{"success": false, "message": "Method not allowed."}`.
- Frontend use: `assets/js/app.js` intercepts the catalog form submit, fetches this endpoint, shows "Searching the archive…" while loading, renders the results, and shows an error state if the request fails. With JavaScript off, the plain GET form works as before.

## Database tables

- `users`: accounts, password hashes, roles (student, faculty, admin), failed sign-in count (`failed_logins`), and lockout time (`locked_until`).
- `categories`: archive categories.
- `submissions`: metadata, visibility, review state, file or link reference, feedback, reviewer, published date, and download count (`download_count`).
- `favorites`: one row per user and record, with a unique pair so a user cannot favorite the same record twice.
- `notifications`: review-decision messages for owners, with a read flag.
- `audit_logs`: login, registration, submission, review, edit, delete, favorite, and lockout actions with user, record, details, and IP address.
