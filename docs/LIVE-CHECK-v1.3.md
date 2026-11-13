# Live check report, v1.3

## Environment and scope

- Local Laragon site: `http://localhost/academic-archiving-system/`
- PHP CLI: 8.1.10; browser checks at 1280px and 360px viewport widths.
- Demo data was read only. No submissions, favorites, reviews, or downloads were
  created. Successful demo logins added their normal audit-log entries.
- Browser testing used JavaScript enabled. Form submission, review decisions,
  downloads, and favorite changes were not triggered to avoid changing demo
  data.

## Results

| # | Check | Result |
|---:|---|---|
| 1 | PHP lint for the seven changed PHP files | Pass |
| 2 | JavaScript syntax check for `assets/js/app.js` | Pass |
| 3 | Public catalog loads and shows the archive title | Pass |
| 4 | v1.3 footer and cache-busted CSS, Anime.js, and app script tags | Pass |
| 5 | DM Sans and Manrope load from local font files | Pass |
| 6 | No Google Fonts or Google static font requests | Pass |
| 7 | Catalog renders six approved public resource cards | Pass |
| 8 | Search returns one match for “Campus Research Paper Sample” | Pass |
| 9 | Matching public detail page renders its preview panel | Pass |
| 10 | Public catalog at 360px has no document horizontal overflow | Pass |
| 11 | Detail page at 360px has no document horizontal overflow | Pass |
| 12 | Admin demo sign-in and dashboard load | Pass |
| 13 | Admin dashboard theme uses deep green hero/action and gold rule | Pass |
| 14 | Admin DOM order is queue, overview, shortcuts, notifications, favorites | Pass |
| 15 | Admin review queue lists pending records first | Pass |
| 16 | Admin dashboard at 1280px has no document horizontal overflow | Pass |
| 17 | Admin dashboard at 360px has no document overflow; table scroll is contained | Pass |
| 18 | Admin role menu opens and Escape closes it | Pass |
| 19 | Admin user directory loads three demo accounts at desktop and mobile widths | Pass |
| 20 | Admin audit page loads and displays recorded entries | Pass |
| 21 | Faculty demo sign-in and dashboard load | Pass |
| 22 | Faculty overview, review queue, contributions, and shared sections are ordered | Pass |
| 23 | Faculty queue renders two pending public-student review cards | Pass |
| 24 | Faculty dashboard at 360px has no document horizontal overflow | Pass |
| 25 | Faculty My submissions page loads six records | Pass |
| 26 | Student demo sign-in and dashboard load | Pass |
| 27 | Student order is coursework, browse, guide, totals, notifications, favorites | Pass |
| 28 | Student progress chip shows the existing five-submission total | Pass |
| 29 | Student dashboard at 360px has no document horizontal overflow | Pass |
| 30 | Resource-type form switch, reduced-motion CSS, Anime.js pause/completion, and missing-library menu fallback | Pass |

## Screenshots

Captured in `docs/screenshots/v1.3/`:

- Role dashboards: `admin-1280.png`, `admin-360.png`,
  `faculty-1280.png`, `faculty-360.png`, `student-1280.png`,
  `student-360.png`.
- Public catalog and detail: `catalog-1280.png`, `catalog-360.png`,
  `detail-1280.png`, `detail-360.png`.
- Student submission form: `student-submit-1280.png`,
  `student-submit-360.png`.
- Admin pages: `admin-users-1280.png`, `admin-users-360.png`.

## Limits

This was a live UI smoke check, not a full workflow or security test. Form
submission, review actions, favorite writes, file downloads, private-record
denials, and JavaScript-disabled operation were not exercised. The source keeps
the existing non-JavaScript form paths, and no animation target is hidden by
CSS before script execution.
