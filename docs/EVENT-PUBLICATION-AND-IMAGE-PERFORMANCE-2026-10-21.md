# Collaborative events and public image performance

## Delivery boundary
Local implementation only. The user explicitly requested no deployment. The 25 ambiguous CRM source facts remain unchanged and flagged for Client Relations to correct in production; unresolved facts must not enter scoring. That is a data-owner action, not a reason to stop independent feature development.

## Events
Active internal Client Relations and Operations/Projects members can create events at `/portal/events`. CVO and Super Admin retain access. Creators can update their own event; team managers and executives can manage shared entries. Draft/published state, optimistic revision checks, confirmation prompts and audit records are included. The shared list is available to both teams.

Flyers accept JPG/PNG/WebP, at most 6 MB and 12 MP. They are decoded and converted to WebP, with original metadata stripped. New files are stored privately and served through controlled preview/public endpoints. Draft images are not served publicly.

Duplicate checks cover normalised event title/date, similar title/date, exact file digest, two visual fingerprints with colour/aspect checks, and meaningful matching filenames on the same date. Transaction locking serialises publication and unique constraints backstop exact duplicates. Renaming or resizing the same test flyer is rejected. Heavily cropped or redesigned flyers can evade perceptual checks; this is not OCR or a guarantee against every visual alteration. Existing flyers need `events:fingerprint-existing`; unreadable/missing files are reported without modifying event details.

Public endpoints: `/api/public/events` (published upcoming/ongoing only; pagination capped at 24; 30-second cache and ETag), `/api/public/events/{event}/flyer` (published only). The feed uses an explicit public field allowlist and excludes staff contacts, authors, file hashes and private paths.

## Website consumer
The website uses `PUBLIC_EVENTS_FEED_URL`, with a 30-second feed cache and bounded 5-minute last-good fallback. Unavailable feeds do not break the page; expired events are filtered. Home and News display actual feed events. No synthetic placeholder events are substituted when the list is empty. Local connection points to port 8093; production connection is not enabled.

## Images
About, Home, News, Portfolio and Portfolio detail templates use responsive image components. Generated WebP sizes are 480/960/1600 pixels, bounded by source size, with explicit dimensions, lazy loading except prioritised images, and asynchronous decoding. Originals are preserved. Server filenames are versioned by source path/size/modification time.

`images:optimise-public` prepares existing assets before traffic; public page requests only select existing variants and do not decode large originals. Website image model saves prepare variants. Remote/non-local files and unsupported formats retain their original URL. Images beyond the processing safety limit remain original until separately prepared.

139 existing images were prepared locally: source total 253,827,898 bytes; smallest variants total 2,966,904 bytes. This is a file-size comparison, not a measured live page-speed or mobile transfer guarantee. Browser checks confirmed generated WebP delivery for the About team and Solomon images.

The website service worker caches only same-origin public image/storage/build assets, never private pages, navigation HTML or API responses. Versioned images use cache-first, other public assets refresh in the background; cache is bounded at 180 assets and preserves URL version query strings. Apache rules make generated versioned images immutable. It does not make every page fully offline or eliminate first-load network access.

## Verification
- Portal full suite: 507 tests / 3,468 assertions pass; see `/tmp/cmih-events-final-suite.log`.
- Website focused PHP tests: 4 tests / 23 assertions pass; 72 existing PHPUnit annotation deprecations remain.
- Website service-worker simulation tests: 3 pass (private paths ignored, exact-version cache-first, bounded cache/background refresh).
- Website production asset build passed after restoring missing local macOS native build dependencies; no lockfile change required.
- Event migration rollback and re-apply passed against a disposable copy of the preview database.
- Browser: demo upload, confirmation, publication, automatic website display and renamed duplicate rejection were exercised. About images loaded as generated WebP. Website 390px viewport had no horizontal overflow. Demo data is explicitly marked local-only.

## Future release steps (not performed)
Deploy both applications only after user authorisation. Migrate the portal; fingerprint existing event flyers. Set the website feed URL to the authorised portal HTTPS API, ensure APP_URL is correct, and prepare public images with `images:optimise-public`. Preserve generated image files/shared upload storage across releases. Ensure GD/WebP and writable generated-image storage are available. Verify real email credentials/scheduler, external integration credentials and production database behavior in the release environment. No emails or events were published to production during this work.
