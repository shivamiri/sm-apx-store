# PLAN.md - Step-by-Step Implementation Roadmap

**Product:** SM Apx Store (short name: Apx)  
**Developer:** Shiva Miri  
**Tagline:** "Discover. Download. Explore."  
**Target Environment:** Core PHP 8 + MySQL on free shared hosting (InfinityFree) & Native Android App (Kotlin, Google AI Studio)  
**Build Methodology:** One file at a time via Gemini. Phone-first workflow with zero CLI/npm/Node/Composer dependencies.  
**Canonical Sources:** PRD.md, DESIGN.md, ARCHITECTURE.md, DATABASE.md, API.md, AGENTS.md, README.md, SETUP.md  

---

## Roadmap Overview

* **Phase 1: Foundation & Database** (Schema synchronization, configuration, DB connection, base styling)
* **Phase 2: Storefront Discovery & Read APIs** (Home catalog, details view, 3-tier search, public read APIs)
* **Phase 3: Public Direct Downloads, Reporting & Connectivity Probe** (External link triggers, community reporting, InfinityFree bot probe)
* **Phase 4: Developer Portal & Google Authentication** (Google sign-in, developer dashboard, app submissions, size auto-detection)
* **Phase 5: Desktop Admin Control Panel & Moderation** (Admin auth, review queue, app approvals, bans, direct uploads, force update)
* **Phase 6: Reviews & Native Android Updates Engine** (Authenticated reviews, device-wide updates scanner API)
* **Phase 7: Native Android App Implementation (Kotlin)** (App shell, networking, catalog UI, background downloader, system installer)
* **Phase 8: Polish, Edge Cases & Production Launch** (Empty states, rate limiting, legal pages, final pre-flight checks)

---

## Phase 1: Foundation & Database

Goal: Establish the database schema with the resolved rate-limiting gap, initialize the core PHP database connection with the unified JSON envelope helper and rate-limit helper, provision the admin credentials hash, and lay down the DESIGN.md CSS design system.

### Tasks

- [ ] **Task 1.1: Resolve Database Schema Gap (Add rate_limits table to DATABASE.md)**
  * **Build:** Update DATABASE.md to include the formal DDL definition for the `rate_limits` table (`ip_address`, `endpoint`, `hits`, `window_start`) along with index definitions, honoring the AGENTS.md rule that forbids writing rate-limiting code without prior schema definition.
  * **Files Touched:** `DATABASE.md`
  * **Dependencies:** None
  * **Verification:** Open DATABASE.md on phone browser/editor, verify `rate_limits` table definition is present with correct types and keys, and check that migration order includes table 10 (`rate_limits`).

- [ ] **Task 1.2: Database Provisioning & phpMyAdmin Seed Import**
  * **Build:** Log into the hosting control panel (InfinityFree), create the MySQL database, open phpMyAdmin, copy the complete migration SQL script from DATABASE.md (including seed records and the new `rate_limits` table), and execute the import.
  * **Files Touched:** MySQL Database via phpMyAdmin (no local file modified)
  * **Dependencies:** Task 1.1
  * **Verification:** In phpMyAdmin on phone browser, check that all 10 tables exist (`users`, `admins`, `categories`, `apps`, `app_submissions`, `app_screenshots`, `reviews`, `reports`, `system_settings`, `rate_limits`) and verify seed apps appear in the `apps` table.

- [ ] **Task 1.2b: Generate Admin Bcrypt Password Hash**
  * **Build:** Create a temporary helper script `make-hash.php` on the hosting server that runs `password_hash($adminPassword, PASSWORD_BCRYPT)` for your chosen admin password. Load `make-hash.php` once in the browser, copy the output hash starting with `$2y$10$`, update the seed admin row (`id = 1`, username `shivamiri`) in the `admins` table via phpMyAdmin, and immediately delete `make-hash.php` from the server.
  * **Files Touched:** `make-hash.php` (temporary, deleted immediately), MySQL Database (`admins` table via phpMyAdmin)
  * **Dependencies:** Task 1.2
  * **Verification:** Open phpMyAdmin, inspect table `admins` row 1, and verify that `password_hash` no longer contains the placeholder string and now holds the real bcrypt hash. Confirm `make-hash.php` is permanently deleted from the file manager.

- [ ] **Task 1.3: Core Configuration, PDO Database Connector & Rate Limit Helper**
  * **Build:** Create `common/config.php` defining database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`), site constants (`APP_NAME`, `APP_SHORT_NAME`, `SITE_URL`), Google OAuth constants (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`), session boot (`session_start()`), and a robust PDO database connection helper function `getDbConnection()`. Include the standard JSON envelope helper function `jsonResponse($success, $data, $error, $httpCode)` and the database-driven rate-limiting helper function `checkRateLimit($ip, $endpoint, $limit, $windowSeconds)` using the `rate_limits` table created in Task 1.2.
  * **Files Touched:** `common/config.php`
  * **Dependencies:** Task 1.2, Task 1.2b
  * **Verification:** Upload `common/config.php` via hosting file manager, create a temporary 3-line test script calling `getDbConnection()` and `checkRateLimit('127.0.0.1', 'test', 10, 60)`, load it in phone browser, and confirm no database errors occur. Delete test script.

- [ ] **Task 1.4: Design System CSS Foundation**
  * **Build:** Create `assets/css/style.css` containing all color tokens from DESIGN.md (dark navy `#070B14`, surface `#0D1527`, electric cyan `#00E5FF`, cobalt `#0070F3`, text, status colors), typography scales, 8pt spacing system, squircle app icon classes, and dark/light mode CSS custom property overrides.
  * **Files Touched:** `assets/css/style.css`
  * **Dependencies:** None
  * **Verification:** Open `assets/css/style.css` directly in phone browser, check for valid CSS syntax, and verify color variables match DESIGN.md HEX codes.

- [ ] **Task 1.5: Shared Web Header & Footer Partials**
  * **Build:** Create `common/header.php` and `common/bottom.php`. `header.php` includes HTML head, meta viewport, stylesheet link, top navbar with logo, search icon, and theme toggle placeholder. `bottom.php` includes bottom navigation bar for mobile, script tags, and toast notification container.
  * **Files Touched:** `common/header.php`, `common/bottom.php`
  * **Dependencies:** Task 1.4
  * **Verification:** Create a temporary test page including both files, load in mobile browser, verify responsive layout, top brand bar, and bottom navigation bar render without visual overflow.

### Definition of Done for Phase 1
Database is fully imported with 10 tables in phpMyAdmin; admin bcrypt hash is provisioned; `common/config.php` connects cleanly to MySQL with JSON and rate-limit helpers; design tokens and shared header/bottom partials render correctly in mobile viewports.

---

## Phase 2: Storefront Discovery & Read APIs

Goal: Deliver a fully runnable vertical slice of the public storefront where anonymous users can browse curated shelves, view app details, and search with the 3-tier fallback algorithm on both the website and the REST API.

### Tasks

- [ ] **Task 2.1: System Configuration & Bot Probe Endpoint (`api/app_config.php`)**
  * **Build:** Create `api/app_config.php` reading `system_settings` from MySQL and returning `min_app_version_code`, `latest_app_version_code`, `update_apk_url`, `force_update_message`, and `announcement_banner` wrapped inside the standard `{ success, data, error }` JSON envelope.
  * **Files Touched:** `api/app_config.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Navigate to `https://example.com/api/app_config.php` in phone browser. Confirm response headers are `Content-Type: application/json` and response body displays `{ "success": true, "data": { ... }, "error": null }` with seed settings.

- [ ] **Task 2.2: Storefront Catalog API (`api/get_home.php`)**
  * **Build:** Create `api/get_home.php` querying categories from `categories` and live apps from `apps` (`status = 'Live' AND deleted_at IS NULL`) segmented into shelves: Featured (`is_featured = 1`), Hot Today (`download_count DESC`), New Arrivals (`created_at DESC`), Top Rated (`rating_average DESC`), and Trending.
  * **Files Touched:** `api/get_home.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Open `https://example.com/api/get_home.php` in phone browser. Confirm JSON output lists categories and shelves with populated sample apps from seed data.

- [ ] **Task 2.3: Mobile-First Public Web Home (`index.php`)**
  * **Build:** Create `index.php` using `common/header.php` and `common/bottom.php`. Render the horizontal category chip scroller, featured hero banner, horizontal scrolling shelf rows (Hot Today, New Arrivals, Trending), and vertical All Apps grid. Cards display app squircle icon, title, category, byte size pill, and "GET" action button.
  * **Files Touched:** `index.php`
  * **Dependencies:** Task 1.5, Task 2.2
  * **Verification:** Load `https://example.com/index.php` in mobile browser. Verify category chips scroll horizontally, cards render with correct spacing and dark navy styling, and tapping an app card routes to `app.php?id=X`.

- [ ] **Task 2.4: App Details API (`api/get_app.php`)**
  * **Build:** Create `api/get_app.php` accepting `id` or `package_name`. Queries `apps` where `status = 'Live' AND deleted_at IS NULL`, joins category, developer user info, screenshots from `app_screenshots`, and approved reviews from `reviews`. Returns real byte size, external APK URL, and YouTube embed URL.
  * **Files Touched:** `api/get_app.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Open `https://example.com/api/get_app.php?id=1` and `https://example.com/api/get_app.php?package_name=com.shivamiri.weather` in phone browser. Confirm both return full app metadata and screenshot arrays. Test invalid ID (`id=9999`) and confirm HTTP 404 with standard error JSON.

- [ ] **Task 2.5: Public Web App Details Page (`app.php`)**
  * **Build:** Create `app.php` fetching app details via internal query. Displays 72px squircle icon, app title, developer name, 4-column key spec strip (Rating, Real Size, Age, Downloads), 1-8 screenshot horizontal gallery, expandable markdown description, optional YouTube video embed, and user reviews list.
  * **Files Touched:** `app.php`
  * **Dependencies:** Task 1.5, Task 2.4
  * **Verification:** Open `https://example.com/app.php?id=1` on phone. Verify screenshots swipe horizontally, real size pill displays "18.4 MB", spec strip aligns properly, and YouTube player loads if present.

- [ ] **Task 2.6: 3-Tier Fallback Search API (`api/search.php`)**
  * **Build:** Create `api/search.php` implementing the PRD search fallback engine: Tier 1 matches query against `app_name` or `package_name` (or FULLTEXT). If zero matches found, Tier 2 & 3 query similar category apps and top trending apps, returning `fallback_applied: true` with a user-friendly recommendation message.
  * **Files Touched:** `api/search.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Test exact match: `https://example.com/api/search.php?q=weather`. Verify Tier 1 hit with `fallback_applied: false`. Test non-existent query: `https://example.com/api/search.php?q=nonexistentappxyz`. Verify fallback results return with `fallback_applied: true`.

- [ ] **Task 2.7: Public Web Search Page (`search.php`)**
  * **Build:** Create `search.php` with a top search input bar and auto-submit/debounce. Renders direct match list or the 3-tier fallback recommendation shelves with an informational banner when no exact matches exist.
  * **Files Touched:** `search.php`
  * **Dependencies:** Task 1.5, Task 2.6
  * **Verification:** Search for "weather" in phone browser and verify direct result card appears. Search for "randomquery" and verify the fallback banner appears showing recommended and trending apps.

### Definition of Done for Phase 2
The public storefront is completely browseable and searchable without an account. Endpoints `app_config.php`, `get_home.php`, `get_app.php`, and `search.php` return verified JSON according to API.md.

---

## Phase 3: Public Direct Downloads, Reporting & Connectivity Probe

Goal: Wire up direct external APK download actions on the web (no server proxying), implement the community abuse reporting pipeline, and verify host connectivity for automated clients.

### Tasks

- [ ] **Task 3.1: Client-Side Direct Download Handler & Instructions Bottom Sheet**
  * **Build:** Update `app.php` and `index.php` so the "Install" / "Download APK" button directly triggers browser file download from the external `apk_source_url` (GitHub Releases or Google Drive). Add vanilla JavaScript to open a bottom instruction sheet on click ("Download started! Tap the file in your notification bar or downloads to complete installation"). Include download counter increment query.
  * **Files Touched:** `app.php`, `assets/js/main.js`
  * **Dependencies:** Task 2.5
  * **Verification:** Tap "Download APK" on `app.php` in phone browser. Confirm the browser initiates direct file download from GitHub/Drive without redirecting to raw GitHub preview pages or proxying through PHP, and confirm `download_count` in MySQL increments by 1.

- [ ] **Task 3.2: Community Abuse Reporting API (`api/report_app.php`)**
  * **Build:** Create `api/report_app.php` accepting POST JSON with `app_id`, `reason`, and `details`. Captures `reporter_ip` from `$_SERVER['REMOTE_ADDR']` and inserts record into `reports` table with `status = 'pending'`.
  * **Files Touched:** `api/report_app.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Use a web-based REST tester or phone browser console script to send POST JSON to `api/report_app.php`. Check phpMyAdmin `reports` table to verify row insertion with correct IP, reason, and pending status.

- [ ] **Task 3.3: Web App Abuse Reporting Modal**
  * **Build:** Add a "Report App" flag button to `app.php` that opens a clean modal containing reason dropdown (Broken Link, Malware, False Version, Other), details textarea, and submit button. Submits asynchronously to `api/report_app.php` and shows a confirmation toast.
  * **Files Touched:** `app.php`, `assets/js/main.js`
  * **Dependencies:** Task 3.2
  * **Verification:** Open `app.php?id=1` on phone, tap Report flag, select "Broken Download Link", submit form, confirm toast notification appears, and verify entry in phpMyAdmin.

- [ ] **Task 3.4: Host Bot-Protection Probe & Mobile User-Agent Test**
  * **Build:** Create a diagnostic test script `api/test_probe.php` that inspects incoming User-Agent and headers and returns clean JSON. Test accessing this endpoint from phone browser with custom User-Agent mimicking Android OkHttp (`okhttp/4.12.0`).
  * **Files Touched:** `api/test_probe.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Load `api/test_probe.php` with mobile headers. Verify whether InfinityFree serves clean JSON or intercepts with an `aes.js` cookie challenge. If blocked, document the API sub-domain fallback according to ARCHITECTURE.md. Remove test script after validation.

### Definition of Done for Phase 3
Direct downloads initiate from external CDN links with incrementing download stats; abuse reports save cleanly to MySQL; host automated bot challenge behavior is verified.

---

## Phase 4: Developer Portal & Google Authentication

Goal: Allow indie developers to authenticate via Google OAuth, view their personal submission dashboard, submit new apps with APK link size auto-detection, and submit updates with zero live downtime.

### Tasks

- [x] **Task 4.1: Google OAuth 2.0 Web Authentication Flow**
  * **Build:** Create `login.php` and `logout.php`. `login.php` redirects to Google OAuth consent screen using `GOOGLE_CLIENT_ID` and `GOOGLE_REDIRECT_URI`. On callback, exchanges authorization code for user profile (ID, email, name, avatar), provisions or updates row in `users` table, and sets `$_SESSION['user_id']`, `$_SESSION['user_email']`, and `$_SESSION['user_name']`. `logout.php` clears the session.
  * **Files Touched:** `login.php`, `logout.php`, `common/header.php`, `common/https.php`, `assets/css/layout.css`, `common/config.sample.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Click "Sign in with Google" on website from phone browser. Complete Google account consent. Verify redirection back to site with user avatar in header, and check phpMyAdmin `users` table for new user record.
  * **Note:** `common/https.php` requires `common/cacert-bundle.pem` for verified HTTPS calls. This file is device-generated and intentionally not committed. To create it on Android/AWebServer: concatenate the PEM files from `/apex/com.android.conscrypt/cacerts` (fallback `/system/etc/security/cacerts`) into `common/cacert-bundle.pem`. The helper's certificate-folder path (CAPATH) is Android-specific; running it on a non-Android host would need that path adjusted - the CA bundle alone does not make it portable.

- [x] **Task 4.2: Developer Submission Form UI (`upload.php`)**
  * **Build:** Create `upload.php` protected by Google session check. Renders two-column desktop / single-column mobile form: App Name, Package Name (with regex validator), Category dropdown, Icon URL (with live preview thumbnail), Feature Graphic URL, 1-8 Screenshot URLs, YouTube URL, Description, Version Code, Version Name, and APK Source Link (GitHub Release or Google Drive). Includes UI guidance advising GitHub Releases for APKs > 100MB.
  * **Files Touched:** `upload.php`
  * **Dependencies:** Task 4.1, Task 1.5
  * **Verification:** Navigate to `upload.php` while signed in. Verify all fields render, live icon preview updates when typing an image URL, and unauthenticated access redirects to `login.php`.

- [x] **Task 4.3: APK Link Verification & Size Auto-Detection Engine**
  * **Build:** Create `assets/js/uploader.js` and an internal PHP helper endpoint `ajax_check_size.php`. Issues lightweight HTTP `HEAD` request with `CURLOPT_NOBODY = true` to the submitted GitHub Release or Google Drive link. If `Content-Length` header is returned, auto-populates formatted MB and bytes. If file is on Google Drive and exceeds 100MB (or auto-detection fails), unlocks the mandatory manual size input field.
  * **Files Touched:** `ajax_check_size.php`, `assets/js/uploader.js`, `upload.php`
  * **Dependencies:** Task 4.2
  * **Verification:** In `upload.php`, input a valid public GitHub Release `.apk` link, tap "Verify Link & Calculate Size", and confirm size auto-populates. Test with an unresolvable link and confirm manual size field unlocks for entry.

- [x] **Task 4.4: App Submission & Zero-Downtime Update Processing**
  * **Build:** Update `upload.php` POST processing logic. If the submission is for a brand new app, insert a record into `app_submissions` with `submission_type = 'new'` and `status = 'pending'`. If it is an update for an already Live app owned by this user, verify new `version_code` > existing `version_code`, leave the current live app untouched in `apps`, and insert the update into `app_submissions` with `submission_type = 'update'`, `app_id = :id`, and `status = 'pending'`.
  * **Files Touched:** `upload.php`
  * **Dependencies:** Task 4.3
  * **Verification:** Submit a new test app. Verify row in `app_submissions` has `submission_type = 'new'`. Then submit an update for an existing app; verify existing app in `apps` table remains `status = 'Live'` while new row in `app_submissions` has `submission_type = 'update'`.

- [ ] **Task 4.5: Developer "My Apps" Dashboard (`my-apps.php`)**
  * **Build:** Create `my-apps.php` listing all apps and pending submissions owned by the logged-in developer. Displays App Icon, Name, Package Name, Version, and status pills: Pending (Amber), In Review (Blue), Live (Green), Rejected (Red). If status is Rejected, displays an expandable alert box showing the admin's mandatory rejection note.
  * **Files Touched:** `my-apps.php`, `common/header.php`
  * **Dependencies:** Task 4.4
  * **Verification:** Open `my-apps.php` on phone. Verify submitted apps appear with correct status badges and pending version indicators.

### Definition of Done for Phase 4
Developers can sign in with Google, submit new apps and version updates, verify APK sizes with manual fallback, and monitor submission status from their dashboard without disrupting live apps.

---

## Phase 5: Desktop Admin Control Panel & Moderation

Goal: Deliver a secure, password-authenticated desktop admin control panel where the platform owner can review submissions, approve/reject apps, ban bad actors, publish direct admin apps, and configure force-update thresholds.

### Tasks

- [ ] **Task 5.1: Isolated Admin Authentication (`admin/login.php`)**
  * **Build:** Create `admin/login.php` with dedicated styling (`assets/css/admin.css`). Authenticates against `admins` table using username and `password_verify()` against `password_hash`. Uses the `checkRateLimit()` helper defined in `common/config.php` to lock the IP after 5 failed attempts for 15 minutes. Sets isolated session variable `$_SESSION['admin_logged_in'] = true`. Create `admin/logout.php`.
  * **Files Touched:** `admin/login.php`, `admin/logout.php`, `assets/css/admin.css`
  * **Dependencies:** Task 1.2b, Task 1.3
  * **Verification:** Open `https://yourdomain.com/admin/login.php` in browser, test invalid credentials (confirm error display and rate limit increment in `rate_limits` table), and submit valid credentials with the password set in Task 1.2b (confirm redirect to `admin/index.php`).

- [ ] **Task 5.2: Admin Shared Header, Sidebar & Layout Partials**
  * **Build:** Create `admin/common/header.php` and `admin/common/bottom.php`. Header enforces admin authentication gatekeeper, displays left sidebar navigation (Dashboard, Submissions Queue, All Apps, Users, System Config, Logout), and renders top metric cards.
  * **Files Touched:** `admin/common/header.php`, `admin/common/bottom.php`
  * **Dependencies:** Task 5.1
  * **Verification:** Load `admin/common/header.php` via test page without active session; confirm immediate redirect to `admin/login.php`. Load with active admin session; verify sidebar and desktop layout render cleanly.

- [ ] **Task 5.3: Admin Dashboard & Pending Moderation Queue (`admin/index.php`)**
  * **Build:** Create `admin/index.php` displaying live metric counters: Total Apps, Live Apps, Pending Submissions, and Banned Users. Displays a data table of pending submissions from `app_submissions` with app icon, name, developer email, APK test link, reported size, and action buttons: "Approve" and "Reject".
  * **Files Touched:** `admin/index.php`
  * **Dependencies:** Task 5.2
  * **Verification:** Open `admin/index.php` in desktop view. Verify metric cards match table counts and pending submissions appear in table rows.

- [ ] **Task 5.4: Submission Approval & Rejection Logic**
  * **Build:** Implement approval and rejection handlers in `admin/index.php`.
    * **Approve New App:** Moves submission data into `apps` (`status = 'Live'`), copies screenshots to `app_screenshots`, and marks submission `'approved'`.
    * **Approve Version Update:** Updates target `apps` record with new `version_code`, `version_name`, `apk_source_url`, `size_bytes`, `size_formatted`, and media URLs, and marks submission `'approved'`.
    * **Reject Action:** Opens modal requiring mandatory `admin_notes` feedback. Sets submission status to `'rejected'`. The live app (if update) remains active with zero downtime.
  * **Files Touched:** `admin/index.php`
  * **Dependencies:** Task 5.3
  * **Verification:** Approve a pending new app and verify it immediately appears on the public `index.php` and in `api/get_home.php`. Reject a test update with note "Broken APK link"; verify rejection note appears in developer's `my-apps.php` and live version remains unaffected.

- [ ] **Task 5.5: All Apps Management & Direct Admin Publishing (`admin/apps.php`)**
  * **Build:** Create `admin/apps.php` allowing search, category filtering, featured toggling (`is_featured`), and app banning (`status = 'Banned'`). Include a "Direct Admin Upload" tab that publishes apps directly to `apps` with `status = 'Live'`, `is_admin_app = 1`, and the official `[Admin Verified]` badge.
  * **Files Touched:** `admin/apps.php`
  * **Dependencies:** Task 5.2
  * **Verification:** Publish an app directly via admin form; verify it goes live instantly without entering the pending queue and displays the Admin Verified badge on `app.php`.

- [ ] **Task 5.6: User Management & Moderation (`admin/users.php`)**
  * **Build:** Create `admin/users.php` listing registered Google developers and users from `users`. Supports search by email or name, display of uploaded app count, and "Ban" / "Unban" toggle button updating `is_banned`.
  * **Files Touched:** `admin/users.php`
  * **Dependencies:** Task 5.2
  * **Verification:** Ban a test user from `admin/users.php`. Confirm user is flagged `is_banned = 1` in phpMyAdmin, and verify that user cannot submit apps or reviews.

- [ ] **Task 5.7: Force Update & Platform Announcement Manager (`admin/config.php`)**
  * **Build:** Create `admin/config.php` allowing administrators to update keys in `system_settings`: `min_app_version_code`, `latest_app_version_code`, `update_apk_url`, `force_update_message`, and `announcement_banner`.
  * **Files Touched:** `admin/config.php`
  * **Dependencies:** Task 5.2, Task 2.1
  * **Verification:** Change `min_app_version_code` to `2` in `admin/config.php`. Load `api/app_config.php` in browser and confirm JSON output reflects `min_app_version_code: 2`.

### Definition of Done for Phase 5
The admin panel is completely functional, password-gated, and allows the platform owner to approve/reject submissions, publish admin apps directly, ban bad actors, and control client force updates.

---

## Phase 6: Reviews & Native Android Updates Engine

Goal: Complete the remaining two backend API endpoints: Google ID token-authenticated app reviews and the device-wide package matching update scanner.

### Tasks

- [ ] **Task 6.1: Google ID Token Authenticated Reviews API (`api/submit_review.php`)**
  * **Build:** Create `api/submit_review.php` accepting POST JSON (`app_id`, `rating`, `comment`) and HTTP `Authorization: Bearer <GOOGLE_ID_TOKEN>`. Verifies Google ID token via Google tokeninfo endpoint, resolves user in `users`, verifies `is_banned = 0`, checks that user is not reviewing their own app (`user_id != apps.user_id`), inserts into `reviews`, and updates `apps.rating_average` and `apps.review_count`. Enforces 1 review per user per app.
  * **Files Touched:** `api/submit_review.php`
  * **Dependencies:** Task 1.3, Task 4.1
  * **Verification:** Send test review payload with mock/real token. Confirm review inserts into `reviews` table, and `rating_average` in `apps` updates accordingly. Resubmit same review and confirm HTTP 409 Conflict with `DUPLICATE_RESOURCE` code.

- [ ] **Task 6.2: Web App Review Submission Form & Session Fallback**
  * **Build:** Update `app.php` review section. If user is signed in via web session, display star selector (1-5) and comment textarea submitting directly to an internal review action handler. If unauthenticated, display "Sign in with Google to write a review" button.
  * **Files Touched:** `app.php`, `assets/js/main.js`
  * **Dependencies:** Task 6.1
  * **Verification:** Sign in with Google on phone browser, open `app.php?id=1`, submit a 5-star review, verify page updates with the new review and average score.

- [ ] **Task 6.3: Device-Wide Updates Scanner API (`api/check_updates.php`)**
  * **Build:** Create `api/check_updates.php` accepting POST JSON with array `packages: [{ package_name: string, version_code: int }]`. Executes covering query against `apps` matching `package_name` where `status = 'Live' AND deleted_at IS NULL`. Filters and returns only packages where database `version_code` > client `version_code`, returning app title, icon, new version, real size, and external APK URL.
  * **Files Touched:** `api/check_updates.php`
  * **Dependencies:** Task 1.3
  * **Verification:** Send POST JSON with `[{"package_name": "com.shivamiri.weather", "version_code": 1}]` to `api/check_updates.php`. Confirm response lists `com.shivamiri.weather` with `latest_version_code: 5` and download URL. Send `version_code: 10` and confirm empty updates array.

### Definition of Done for Phase 6
All 7 REST API endpoints from API.md are 100% implemented, tested, and verified against MySQL and the unified JSON envelope.

---

## Phase 7: Native Android App Implementation (Kotlin)

Goal: Implement the native Android client in Google AI Studio using Kotlin, consuming all 7 REST endpoints, implementing the device-wide PackageManager scanner, and handling direct background downloads and system package installation.

### Tasks

- [ ] **Task 7.1: Android Project Manifest, Permissions & FileProvider Configuration**
  * **Build:** Configure `AndroidManifest.xml` with permissions: `INTERNET`, `ACCESS_NETWORK_STATE`, and `REQUEST_INSTALL_PACKAGES`. Define `FileProvider` pointing to `res/xml/file_paths.xml` mapped to internal scoped cache directory (`getExternalFilesDir("downloads")`). Add color tokens to `res/values/colors.xml` matching DESIGN.md.
  * **Files Touched:** `android/app/src/main/AndroidManifest.xml`, `android/app/src/main/res/xml/file_paths.xml`, `android/app/src/main/res/values/colors.xml`
  * **Dependencies:** None (independent mobile module)
  * **Verification:** Build APK in Google AI Studio, install on Android phone, verify application launches without permission crashes.

- [ ] **Task 7.2: HTTP Networking Client & Model Classes**
  * **Build:** Create `data/ApiClient.kt` using standard `HttpURLConnection` or `OkHttp`. Implement unified JSON parser handling `{ success, data, error }`. Create data models in `data/Models.kt` for `AppConfig`, `AppItem`, `CategoryItem`, `ShelfBlock`, `AppDetail`, `UpdateItem`, and `ReviewItem`.
  * **Files Touched:** `android/app/src/main/java/com/shivamiri/apx/data/ApiClient.kt`, `android/app/src/main/java/com/shivamiri/apx/data/Models.kt`
  * **Dependencies:** Task 7.1, Phase 2 APIs
  * **Verification:** Run a simple network test calling `api/app_config.php` and log the parsed `min_app_version_code` to confirm model parsing succeeds.

- [ ] **Task 7.3: Cold-Boot Probe & Force Update Dialog (`MainActivity.kt`)**
  * **Build:** In `MainActivity.kt`, execute cold-boot probe against `api/app_config.php`. If current app version code < `min_app_version_code`, display a non-dismissible AlertDialog detailing `force_update_message` with an "Update Now" action button pointing to `update_apk_url`. Ensure hardware back button and touch-outside are disabled.
  * **Files Touched:** `android/app/src/main/java/com/shivamiri/apx/MainActivity.kt`
  * **Dependencies:** Task 7.2
  * **Verification:** Temporarily set `min_app_version_code` to `99` in admin panel. Launch Android app; verify the non-dismissible update modal appears and cannot be bypassed. Reset to `1` and confirm app loads normally.

- [ ] **Task 7.4: Home Storefront & Shelves Fragment (`HomeFragment.kt`)**
  * **Build:** Create `HomeFragment.kt` fetching `api/get_home.php`. Render top category chip bar, Featured carousel, and horizontal RecyclerViews for Hot Today, New Arrivals, and Trending. Tapping any app navigates to `AppDetailActivity.kt`.
  * **Files Touched:** `android/app/src/main/java/com/shivamiri/apx/ui/HomeFragment.kt`
  * **Dependencies:** Task 7.2
  * **Verification:** Launch app on phone. Verify home shelves populate with real data, squircle icons render, and tapping an app opens the details view.

- [ ] **Task 7.5: Search & Fallback Fragment (`SearchFragment.kt`)**
  * **Build:** Create `SearchFragment.kt` with search input and 250ms debounce calling `api/search.php?q=...`. If `fallback_applied == true`, render the recommendation banner and populate similar and trending app rows.
  * **Files Touched:** `android/app/src/main/java/com/shivamiri/apx/ui/SearchFragment.kt`
  * **Dependencies:** Task 7.2
  * **Verification:** Search for "weather" and confirm direct result appears. Search for "nomatch123" and confirm the recommendation banner and fallback apps appear.

- [ ] **Task 7.6: App Details, Background Downloader & System Installer (`AppDetailActivity.kt`)**
  * **Build:** Create `AppDetailActivity.kt` fetching `api/get_app.php`. Build `utils/DownloadHelper.kt` to download APK directly from external URL into scoped storage with real-time percentage progress inside the Install button. Build `utils/InstallHelper.kt` to resolve content URI via `FileProvider` upon completion and dispatch `ACTION_VIEW` / `ACTION_INSTALL_PACKAGE` intent.
  * **Files Touched:** `android/app/src/main/java/com/shivamiri/apx/ui/AppDetailActivity.kt`, `android/app/src/main/java/com/shivamiri/apx/utils/DownloadHelper.kt`, `android/app/src/main/java/com/shivamiri/apx/utils/InstallHelper.kt`
  * **Dependencies:** Task 7.2
  * **Verification:** Tap "Install" on an app in the native client. Verify progress bar fills from 0% to 100%, and upon completion, the Android system package installer dialog appears asking "Do you want to install this app?".

- [ ] **Task 7.7: Device-Wide Updates Scanner Fragment (`UpdatesFragment.kt`)**
  * **Build:** Create `UpdatesFragment.kt`. Query Android device `PackageManager` for all installed packages (`pm.getInstalledPackages(0)`), construct batch JSON array, send POST request to `api/check_updates.php`, and render list of updatable apps with old vs. new version codes and "UPDATE" action buttons.
  * **Files Touched:** `android/app/src/main/java/com/shivamiri/apx/ui/UpdatesFragment.kt`
  * **Dependencies:** Task 7.6
  * **Verification:** Open Updates tab in the Android app. Confirm it lists test apps installed on phone that have higher version codes in Apx store, and tapping "UPDATE" triggers direct background download and install prompt.

### Definition of Done for Phase 7
Native Android app runs cleanly, browses catalog, searches with fallback, scans device packages for updates, and downloads APKs directly to trigger the system package installer.

---

## Phase 8: Polish, Edge Cases & Production Launch

Goal: Connect rate limiting across all API endpoints using the config helper, audit all UI states across all components, add privacy and terms pages, and execute the pre-launch deployment checklist.

### Tasks

- [ ] **Task 8.1: API Rate Limiting Enforcement & Edge Cases**
  * **Build:** Connect the existing `checkRateLimit()` helper from `common/config.php` across all public API endpoints (`api/search.php`, `api/check_updates.php`, `api/submit_review.php`, and `api/report_app.php`). Apply appropriate thresholds (120/min for read, 60/min for search/updates, 10/min for reviews/reports). If exceeded, return standard HTTP 429 JSON response with `RATE_LIMIT_EXCEEDED`.
  * **Files Touched:** `api/search.php`, `api/check_updates.php`, `api/submit_review.php`, `api/report_app.php`
  * **Dependencies:** Task 1.3, Phase 2, Phase 6
  * **Verification:** Script a loop calling `api/search.php` 65 times in under one minute from phone. Verify the 61st call returns HTTP 429 with error code `RATE_LIMIT_EXCEEDED`.

- [ ] **Task 8.2: Comprehensive UI State Polish (Default, Loading, Error, Empty, Disabled)**
  * **Build:** Audit and polish all UI states in `assets/css/style.css` and JavaScript. Ensure empty search results show a clean graphic and message; loading buttons show spinners; disabled submit buttons appear distinctly; and error toasts trigger on network loss.
  * **Files Touched:** `assets/css/style.css`, `assets/js/main.js`
  * **Dependencies:** Phase 2, Phase 4
  * **Verification:** Test offline state in browser (confirm offline toast), submit empty forms (confirm validation borders and messages), and inspect empty categories (confirm clean empty shelf placeholder).

- [ ] **Task 8.3: Legal Pages (Privacy Policy, Terms of Service & DMCA Notice)**
  * **Build:** Create `privacy.php` and `terms.php` styled with DESIGN.md tokens. Document that Apx stores zero binary files, links only to developer-provided external hosts, respects DMCA takedowns via the abuse reporting system, and collects minimal Google profile data strictly for authentication.
  * **Files Touched:** `privacy.php`, `terms.php`, `common/bottom.php`
  * **Dependencies:** Task 1.5
  * **Verification:** Open `privacy.php` and `terms.php` on phone browser; verify typography, readability, and footer navigation links work cleanly.

- [ ] **Task 8.4: Production Pre-Flight Checklist & README Verification**
  * **Build:** Perform final end-to-end check:
    1. Confirm `admins` password hash in MySQL is secure.
    2. Confirm seed/test spam apps are cleared and real initial apps are reviewed and marked `Live`.
    3. Verify all endpoints adhere to the `{ success, data, error }` envelope.
    4. Verify README.md setup steps match the exact final file tree and configuration keys.
  * **Files Touched:** `README.md`, MySQL Database via phpMyAdmin
  * **Dependencies:** All previous tasks
  * **Verification:** Perform an end-to-end user journey on phone: visit storefront, search an app, download APK, sign in as developer, submit an app, log into admin panel, approve submission, verify live store update.

### Definition of Done for Phase 8
Platform is fully polished, rate-limited, legally compliant, and verified end-to-end for production deployment.

---
*End of Implementation Plan. Preserved in repository root as PLAN.md.*
