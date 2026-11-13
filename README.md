# ISPSC Sta. Maria: Academic Archiving System

**Version: v1.3.1**

A PHP 8.1+ / MySQL academic archive built for the Advanced Web Development group final project. It supports student, faculty, admin/clerk and public visitor workflows, including private file authorization on the server.

The campus repository header uses the ISPSC seal from the [official ISPSC website](https://www.ispsc.edu.ph/images/misc/logo.png).

## Features

- Responsive public catalog showing **approved + public** records only, as a card grid with real image thumbnails and labelled colour tiles for every other type.
- Student/faculty registration and sign-in; role-based dashboards. After 5 failed sign-in attempts an account is locked for 15 minutes.
- Separate role-specific dashboard menus for archive review, faculty contributions, and student coursework.
- Distinct admin Control Center, faculty Reading Room, and student Study Desk layouts, with a shared ISPSC shell.
- Anime.js 4.5.0 is vendored locally for optional interface motion. DM Sans and Manrope variable fonts are also served locally.
- Faculty reviewer queue for pending public student submissions; private student submissions remain restricted to the student and administrators.
- Admin-only user directory with account search and each account's submission history.
- Admin-only audit log page listing every recorded action, newest first, with action and user filters and pagination.
- File and HTTP/HTTPS link submissions with Public/Private visibility. The file content is checked against its extension (MIME type), so a renamed file is rejected.
- Academic year must be in the form YYYY-YYYY, for example 2025-2026.
- Admin/clerk dashboard, official record creation, and review actions: approve, reject, or return for revision with feedback.
- PDF, image, audio and video previews where browser-supported; safe download for other accepted formats. Download counts are shown on each record and in the admin dashboard.
- Signed-in users can favorite records from the detail page and see them in a My favorites section on the dashboard.
- My Submissions is its own page (`my-submissions.php`) with sort, visibility, and favorites filters.
- Link records show their destination domain on the detail page.
- Search by title, description and keywords; filter by category and resource type.
- Sort the public catalog by newest, oldest, or title, and filter by academic year; My Submissions can be sorted and filtered by visibility.
- Edit and delete your own Pending or Returned records (admin can edit or delete any record); a Returned record sent back for review goes Pending again.
- Feedback is required when a reviewer rejects or returns a record.
- Admins see a "Publish immediately" checkbox on the submit form: ticked, the record is approved and published at once; unticked, it goes Pending like everyone else and must be approved through review.
- Owners get a dashboard notification for every review decision (approved, rejected, returned).
- JSON search endpoint `api/search.php` for the public catalog (see `docs/API.md`). Submit and review also work over fetch with loading, success, and error states; the plain form posts still work with JavaScript off.
- Real demo files: `database/seed_files.php` generates a PDF, PNG, audio, video, Word, and PowerPoint sample plus a private, a pending, and a returned record (run once after import).
- My Submissions, review status, audit log, CSRF protection, password hashing, PDO prepared statements and server-side validation.
- Files are stored in separate `uploads/public` and `uploads/private` folders. Apache denies direct requests; all downloads go through `api/download.php`, which checks permissions.

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `fileinfo`, `mbstring` enabled.
- MySQL 5.7+ or MariaDB 10.4+.
- Apache with `mod_rewrite` not required; `.htaccess` access control must be enabled (`AllowOverride All`).
- XAMPP or Laragon.

## Install on XAMPP

1. Extract the `academic-archiving-system` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Import `database/schema.sql` (it creates/selects the `academic_archiving` database and tables).
5. Import `database/seeds.sql`.
6. Run the demo file seeder once from the project folder (creates 10 real demo files and matching records; safe to run twice, it skips titles that already exist):
   ```bash
   php database/seed_files.php
   ```
7. If `seeds.sql` says to run the PHP seed script, open a terminal in the project folder and run:
   ```bash
   php database/seed_demo.php
   ```
   Or visit `http://localhost/academic-archiving-system/database/seed_demo.php` once, then delete or restrict that script after setup.
7. Open `config/database.php` and confirm the local MySQL host, database, username and password. Default XAMPP values are `127.0.0.1`, `academic_archiving`, `root`, and an empty password.
8. Visit `http://localhost/academic-archiving-system/`.

## Install on Laragon

1. Extract the folder into `C:\laragon\www\`.
2. Start **Apache** and **MySQL** from Laragon.
3. Import `database/schema.sql` and `database/seeds.sql` using HeidiSQL or phpMyAdmin.
4. Run `php database/seed_files.php` from the project directory to create the 10 real demo files and matching records (run once; safe to repeat, it skips existing titles).
5. Run `php database/seed_demo.php` from the project directory if the SQL seed did not insert accounts.
5. Check `config/database.php` for your MySQL credentials.
6. Visit `http://localhost/academic-archiving-system/` (or the Laragon virtual host if enabled).

## Demo accounts

All demo accounts use this password:

**`ArchiveDemo2026!`**

| Role | Email |
|---|---|
| Admin / College Clerk | `admin@college.edu` |
| Student | `student@college.edu` |
| Faculty | `faculty@college.edu` |

Change these credentials before any shared or deployed use.

## Run in VS Code

Open the extracted project folder in VS Code. Start Apache and MySQL through XAMPP/Laragon, then use the browser URL above. VS Code is the editor; PHP and MySQL services must still be running.

## v1.3 visual update

The v1.3 update changes presentation only. It does not change the database
schema or require a database re-import, migration, or seed run. Anime.js 4.5.0
is served from `assets/vendor/anime/anime.umd.min.js`, and animations are
skipped when reduced motion is requested or the library is unavailable. The
font files in `assets/fonts/` are variable versions of DM Sans and Manrope;
their Open Font License texts are included alongside them.

The role dashboards are arranged as follows: admin review queue, archive
overview, then user and audit links; faculty review cards, contributions, and
the campus collection; student coursework, published-work browsing, then the
how-it-works guide. The public catalog and authentication pages keep their
existing structure.

## Storage and security notes

- Upload maximum is 100 MB per file. In `php.ini`, set `upload_max_filesize=100M` and `post_max_size=105M`, then restart Apache. Accepted extensions: PDF, MP4, WebM, MP3, WAV, M4A, JPG/JPEG, PNG, GIF, WebP, DOC/DOCX, TXT, PPT/PPTX, XLS/XLSX, CSV and ZIP. The file content is compared with its extension using the detected MIME type, so a renamed file (for example a PNG saved as `.pdf`) is rejected with a clear error and no record is created.
- Sign-in lockout: 5 failed attempts lock an account for 15 minutes. A successful sign-in clears the failure count. Lockouts are written to the audit log.
- Uploads are assigned random server-side filenames. Original names are retained as metadata.
- Do not remove the `.htaccess` files from upload folders. They prevent direct web access on Apache. The PHP download endpoint performs the authorization check for both previews and downloads.
- On Nginx, configure an equivalent `deny all` rule for `/uploads/`; `.htaccess` is Apache-only.
- Keep `uploads/` writable by the web server, but not world-writable. Suggested permissions on Linux: directories `0750`, files `0640`.
- Set database credentials through environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) or update `config/database.php`.
- Use HTTPS, a strong unique database password, backups, malware scanning, and server-level request/body limits before internet deployment. This starter is suitable for a local college demonstration; a real production deployment needs operational hardening, monitoring, backups, email notifications and security review.
- The demo seed script should be removed or blocked after initial setup. Public registration intentionally cannot create admin accounts.

## Main workflows

1. **Student:** sign in → submit file/link → add metadata → choose visibility → check personal status and review feedback. Students can access only their own unpublished submissions.
2. **Faculty:** sign in → review pending public student submissions → approve, reject, or return them with feedback; manage personal contributions and browse the public archive. Faculty cannot access private student work or admin user management.
3. **Admin/clerk:** sign in → search the user directory and open submission histories → review all submissions, including private records → create official archive records and make review decisions. On the submit form, the "Publish immediately" checkbox (admin only, unchecked by default) publishes the record at once; left unchecked, the record goes Pending and must be approved through review.
4. **Public visitor:** search approved public records → open details → preview/download supported resources. Private or unpublished records are not listed and direct access returns HTTP 403.
5. **Private access:** the owner and admin can view private resources; faculty reviewers and other users receive HTTP 403.

## Database tables

- `users`: accounts, password hashes and roles. Failed sign-in count and lockout time are stored here.
- `categories`: archive categories.
- `submissions`: metadata, visibility, review state and file/link references, plus a download count.
- `favorites`: one row per user and record, so a signed-in user can save records.
- `notifications`: review-decision messages for owners, with a read flag.
- `audit_logs`: account and review actions.

## Important testing checklist

- Log in as each demo role.
- Submit one public file, one private file and one web link as a student.
- Confirm student submissions remain pending and do not appear in public search before approval.
- Approve one public item as admin; confirm it appears in the public catalog.
- Try opening the private file as a different account and as a visitor; expect **403 Forbidden** from both `view-resource.php` and `api/download.php`.
- Return an item with feedback and confirm the owner sees the feedback.
- Test invalid URL schemes, unsupported extensions, empty fields and files over 100 MB.
- Favorite a record from its detail page and check it appears in My favorites; download a file and check the count rises by one; try a private download as a visitor and check the count does not rise.
- Try 5 wrong passwords and check the account locks; sign in with the right password after the lock and check it is refused until the lock clears.
- Upload a PNG renamed to `.pdf` and check it is rejected with a MIME error and no record is created.
- Open My Submissions, sort and filter it, and open the admin audit log page.
- Test at phone, tablet and desktop widths and navigate using keyboard focus.

## Project flow / access matrix

| Role | Submit | Own submissions | Review submissions | User directory | Private records |
|---|---:|---:|---|---:|---|
| Visitor | No | No | No | No | No |
| Student | Yes | Yes | No | No | Own submissions only |
| Faculty | Yes | Yes | Pending public student submissions only | No | No |
| Admin / Clerk | Yes (published) | Yes | All submissions | Yes; includes submission history | Yes |

The implementation follows the supplied project brief: responsive public discovery, role-based authentication, file/link submissions, Public/Private permissions, review workflow, resource detail previews, admin management, search/filter, auditability, and backend-enforced protected downloads.

## Section XII: Project details

| Item | Detail |
|---|---|
| Group name | To be confirmed |
| Section | To be confirmed |
| Members | Jasten Cabacungan, Sharmine Casandig, John Lester Tuzon |
| Dates | To be confirmed |
| Git repository | To be confirmed |
| Deployment link | To be confirmed |
