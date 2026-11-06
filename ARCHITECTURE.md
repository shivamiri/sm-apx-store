# Architecture & Technical Specifications: ARCHITECTURE.md

**Document Version:** 1.0.0  
**Target Platform:** Production  
**Product Name:** SM Apx Store (Short: **Apx**)  
**Developer:** Shiva Miri  
**Tagline:** *"Discover. Download. Explore."*  
**Canonical References:** `PRD.md` (v1.1.0), `DESIGN.md` (v1.0.0)  

---

## 1. Executive Summary & Recommended Tech Stack

SM Apx Store is engineered for high simplicity, minimal operational overhead, and full compliance with free shared hosting limits. It operates under a zero-storage, zero-proxy model where the server hosts pure metadata and links, and client devices download binaries directly from GitHub Releases or Google Drive.

| Layer | Recommended Technology | One-Line Justification |
| :--- | :--- | :--- |
| **Web Frontend** | Vanilla HTML5, CSS3, & Vanilla JavaScript (ES6+) | Eliminates heavy build tools, npm steps, and node dependencies while matching the high-performance dark/light design system natively. |
| **Mobile Client** | Native Android in Kotlin (Min API 26 / Android 8.0+) | Gives full, direct access to Android `DownloadManager`, `PackageManager`, and `FileProvider` package installer intents without cross-platform overhead. |
| **Backend API & Web**| Core PHP (7.4 - 8.2+) Procedural / Clean OOP | Runs out of the box on standard free shared hosting (such as InfinityFree) without Composer or server daemons. |
| **Database** | MySQL (5.7+ / MariaDB) with `PDO_MYSQL` | Universal availability across shared hosts with ACID reliability and index-optimized relational lookups. |
| **Web & Admin Auth** | Native PHP Sessions (`$_SESSION`) with secure cookies | Zero-complexity state management without JWT token invalidation headaches or external auth microservices. |
| **Hosting (Server)** | Free Shared Hosting (InfinityFree / LAMP) | Zero financial cost for indie operations; hosts metadata and HTML templates while binaries reside on free external CDNs. |
| **Binary Storage** | GitHub Releases (Primary) & Google Drive (Secondary) | Provides high-bandwidth, global CDN asset delivery for APK packages without consuming server disk space or host bandwidth. |

---

## 2. Hard Architectural Constraints

1. **Strict Zero-Storage & Zero-Proxy Enforcement:**
   * In strict adherence to free hosting terms of service, the PHP server **never** stores APK files locally and **never** streams, proxies, or buffers APK bytes through PHP scripts.
   * There is **no download-proxy script**. The server provides direct external URLs, and client browsers/apps download directly from GitHub or Google Drive.
2. **Native App Installation Model:**
   * The native app downloads the APK directly from external links using Android's native download mechanisms.
   * Upon download completion, the app invokes `FileProvider` and fires the system package installer intent (`ACTION_VIEW` / `ACTION_INSTALL_PACKAGE`).
   * **There is no automatic, silent, or background installation.** The user always sees and confirms the native Android system install sheet.
3. **Mobile Website Download Model:**
   * The mobile web browser triggers a direct client-side file download.
   * The web page cannot trigger system installation intents due to browser sandbox constraints; the user must manually tap the completed notification or file in their `Downloads/` directory.
4. **Google Drive Unverified Status:**
   * Google Drive direct downloading is unverified and prone to quota limits and virus-scan warning interstitials on files over 100 MB.
   * It is treated as a secondary fallback. The system strongly emphasizes GitHub Releases across developer upload guidelines.
5. **Host Bot-Protection Risk:**
   * Free hosts like InfinityFree use client-side JavaScript bot challenges (e.g., `aes.js`) that block non-browser programmatic HTTP requests.
   * This is flagged as a high-priority risk requiring immediate connectivity testing from the native Kotlin client.
6. **No Redis, No Message Queues:**
   * Background tasks, cleanup, or metric aggregations rely solely on lightweight database updates or web-triggered cron jobs.

---

## 3. Complete Folder and File Structure

The project is structured to keep file counts low, preserve existing core paths, and facilitate direct file-by-file generation.

```
sm-apx-store/
├── PRD.md                       # Canonical Product Requirements Document
├── DESIGN.md                    # Canonical Design System Specification
├── ARCHITECTURE.md              # Canonical System Architecture (This file)
│
├── index.php                    # Public Web Home, Shelves & Catalog
├── app.php                      # Public Web App Details & Reviews Page
├── search.php                   # Public Web Search & Fallback Results
├── upload.php                   # Developer App Submission & Update Form
├── my-apps.php                  # Developer Dashboard (Google Sign-In protected)
├── login.php                    # Developer Google OAuth Start & Callback Handler
├── logout.php                   # Web Session Destroyer
│
├── common/                      # Shared Web Components & Utilities
│   ├── config.php               # Database connection (PDO), constants, session boot
│   ├── header.php               # Shared HTML head, navigation bar, theme init
│   └── bottom.php               # Shared footer, bottom navigation, toast host
│
├── admin/                       # Desktop-Only Admin Control Panel
│   ├── index.php                # Admin Dashboard & Live Moderation Queue
│   ├── login.php                # Isolated Admin Login (Username/Password)
│   ├── apps.php                 # Admin All Apps Management & Direct Upload
│   ├── users.php                # Admin User Management & Banning
│   ├── config.php               # Admin Force Update & System Settings
│   └── common/                  # Admin Layout Partials
│       ├── header.php           # Admin desktop sidebar & metric header
│       └── bottom.php           # Admin closing tags & modal hosts
│
├── api/                         # Native Android App REST JSON Endpoints
│   ├── get_home.php             # Curated shelves (Featured, Hot, New, Top, Trending)
│   ├── get_app.php              # App details, screenshot gallery, review payload
│   ├── search.php               # Search query with 3-tier fallback engine
│   ├── check_updates.php        # Device-wide package list comparison
│   ├── app_config.php           # System version check for force-update dialog
│   ├── submit_review.php        # Google ID token verified review creation
│   └── report_app.php           # Public community app abuse reporting
│
├── assets/                      # Shared Static Web Assets
│   ├── css/
│   │   ├── style.css            # Core design system stylesheet (DESIGN.md tokens)
│   │   └── admin.css            # Desktop-specific admin layout styling
│   └── js/
│       ├── main.js              # Theme switcher, search debounce, toast engine
│       └── uploader.js          # Client size checker trigger & URL validation
│
└── android/                     # Native Android App Source (Kotlin)
    └── app/src/main/
        ├── AndroidManifest.xml  # Permissions (Internet, Install, Storage) & Providers
        ├── res/
        │   ├── xml/file_paths.xml # FileProvider secure path mapping
        │   └── values/colors.xml  # Colors synced with DESIGN.md
        └── java/com/shivamiri/apx/
            ├── MainActivity.kt        # App frame, bottom navigation routing
            ├── data/
            │   ├── ApiClient.kt       # OkHttp / HttpURLConnection network client
            │   └── Models.kt          # App, Shelf, Update, Config data models
            ├── ui/
            │   ├── HomeFragment.kt    # Shelves and category feed
            │   ├── SearchFragment.kt  # Search input & fallback view
            │   ├── AppDetailActivity.kt # App details, progress bar, install trigger
            │   └── UpdatesFragment.kt # Device PackageManager scanner & updates list
            └── utils/
                ├── DownloadHelper.kt  # Direct CDN download manager integration
                └── InstallHelper.kt   # FileProvider content URI & package intent
```

---

## 4. End-to-End Data Flows

### 4.1 Anonymous App Browsing & Direct Installation (Native App)

```
[ Native App (Kotlin) ]              [ Free Host (PHP/MySQL) ]        [ GitHub / Google Drive ]
        |                                       |                                |
        |--- 1. GET /api/get_app.php?id=X ----->|                                |
        |<-- 2. JSON: {apk_url, size, ...} -----|                                |
        |                                                                        |
        |=== User taps "Install" ================================================|
        |                                                                        |
        |--- 3. Direct HTTP GET to external CDN link --------------------------->|
        |<-- 4. Binary Stream (Bypasses PHP Host completely) -------------------|
        |                                                                        |
        |--- 5. Writes to Context.getExternalFilesDir("downloads")               |
        |--- 6. Invokes FileProvider.getUriForFile()                             |
        |--- 7. Dispatches ACTION_INSTALL_PACKAGE / ACTION_VIEW                  |
        v                                                                        v
 [ Android System Installer Prompts User: "Do you want to install this app?" ]
```

### 4.2 Device-Wide Package Update Check (Native App)

```
[ Native App (Kotlin) ]              [ Free Host (PHP/MySQL) ]
        |                                       |
        |--- 1. PackageManager queries all ---->| (Local on device)
        |       installed packages & versions   |
        |                                       |
        |--- 2. POST /api/check_updates.php --->|
        |       Body: [{pkg, vCode}, ...]       |
        |                                       |--- 3. SELECT * FROM apps
        |                                       |       WHERE package_name IN (...)
        |                                       |       AND status = 'Live'
        |                                       |       AND version_code > client_vCode
        |                                       |
        |<-- 4. JSON: Array of updatable apps --|
        |                                       |
        |--- 5. Renders list with "UPDATE" CTA  |
```

### 4.3 Developer Submission & Zero-Downtime Update

```
[ Developer Browser ]                [ Web Server (Core PHP) ]        [ GitHub / Google Drive ]
        |                                       |                                |
        |--- 1. Inputs APK URL (GitHub/Drive) ->|                                |
        |--- 2. Taps "Verify & Auto-Detect" --->|                                |
        |                                       |--- 3. HTTP HEAD (No body) ---->|
        |                                       |<-- 4. Content-Length Header ---|
        |<-- 5. Auto-fills size (or fallback) --|                                |
        |                                                                        |
        |--- 6. POST /upload.php (Submit) ----->|                                |
        |                                       |--- 7. Check if package exists  |
        |                                       |    |                           |
        |                                       |    +-- NEW APP:                |
        |                                       |    |   INSERT status='Pending' |
        |                                       |    |                           |
        |                                       |    +-- EXISTING LIVE APP:      |
        |                                       |        Old stays 'Live'        |
        |                                       |        New queues as 'Pending' |
        |<-- 8. Redirect to my-apps.php --------|                                |
```

---

## 5. State Management & Error Handling

### 5.1 State Management

* **Web Platform (Public & Developer):**
  * State is managed through native PHP session storage (`$_SESSION['user_id']`, `$_SESSION['user_email']`, `$_SESSION['user_name']`).
  * Anonymous users carry zero session overhead; the public catalog is completely stateless.
* **Admin Panel:**
  * Strict isolated session namespace (`$_SESSION['admin_logged_in']`, `$_SESSION['admin_id']`).
  * Verified on every admin request via a gatekeeper function in `admin/common/header.php`.
* **Native Android App (Kotlin):**
  * Light, reactive state using Kotlin ViewModels and StateFlow / LiveData for UI state.
  * In-memory caching for catalog shelves to eliminate redundant network hits during orientation or tab changes.
  * Download progress is tracked locally using `DownloadManager` broadcast receivers or explicit worker callbacks mapped to package names.

### 5.2 Error Handling Strategy

* **API Responses:**
  * All JSON endpoints strictly return a unified payload schema:
    ```json
    {
      "success": true,
      "data": {},
      "error": null
    }
    ```
  * In case of failure:
    ```json
    {
      "success": false,
      "data": null,
      "error": {
        "code": "PACKAGE_NOT_FOUND",
        "message": "The requested application does not exist or is not approved."
      }
    }
    ```
* **Database & PHP Exceptions:**
  * All database operations execute inside `try/catch` blocks using PDO prepared statements.
  * Production environments run with `display_errors = Off` and log errors internally to prevent credential leaks.
* **Native App Download Failures:**
  * If a download fails (e.g., dead GitHub/Drive link), the button state transitions back from "Downloading" to "Install", and a bottom toast alerts the user: *"Download link unreachable. Please report this app."*

---

## 6. Coding Standards, Naming Conventions & Environment Variables

### 6.1 Naming Conventions & Code Style

* **PHP Backend:**
  * Follows PSR-12 formatting principles (clean 4-space indentation, no closing tags on pure PHP files).
  * Variable names: `$snake_case` (e.g., `$app_name`, `$package_name`).
  * Functions: `camelCase()` (e.g., `getFeaturedApps()`, `detectFileSize()`).
  * Constants: `UPPER_SNAKE_CASE` (e.g., `DB_HOST`, `SITE_URL`).
* **Kotlin (Android):**
  * Standard Kotlin style guide.
  * Classes: `PascalCase` (e.g., `AppDetailActivity`, `DownloadHelper`).
  * Functions & Variables: `camelCase` (e.g., `triggerInstallIntent()`, `currentPackage`).
* **Database (MySQL):**
  * Tables and columns: `snake_case`, pluralized table names (`apps`, `users`, `reviews`, `reports`).
  * Primary keys: `id INT AUTO_INCREMENT PRIMARY KEY`.
  * Foreign keys: `[singular_table]_id` (e.g., `app_id`, `user_id`).

### 6.2 Environment & Configuration Setup

Configuration is encapsulated within `common/config.php` using conditional definitions or an optional `.env.php` file excluded from version control:

```php
// common/config.php (Structural blueprint)
define('DB_HOST', 'sqlxxx.infinityfree.com');
define('DB_NAME', 'if0_xxxxxxxx_apx');
define('DB_USER', 'if0_xxxxxxxx');
define('DB_PASS', 'your_secure_password');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL', 'https://yourdomain.com');
define('APP_NAME', 'SM Apx Store');
define('APP_SHORT_NAME', 'Apx');
define('DEV_NAME', 'Shiva Miri');

// Google OAuth Web Client Config
define('GOOGLE_CLIENT_ID', 'xxxxxxxxxxxx.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxxxxxxxxxxxxxxx');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/login.php');
```

---

## 7. Scalability, Performance & Technical Risks

### 7.1 Scalability on Free Shared Hosting
* **Zero Bandwidth Load:** Because APK binaries are hosted by GitHub/Google, 10,000 downloads per day consume essentially **zero server file bandwidth**, protecting the free hosting account from quota bans.
* **Low Query Complexity:** All user-facing catalog queries are single-table lookups indexed on `status` and `category`.
* **Cache Headers:** The `/api/` endpoints send HTTP `Cache-Control: public, max-age=300` headers, enabling client-side caching of catalog queries for 5 minutes.

### 7.2 Technical Risk & Mitigation Ledger

| Identified Risk | Severity | Mitigation Strategy in Architecture |
| :--- | :---: | :--- |
| **InfinityFree Bot-Challenge Blocking App API** | **High** | Free hosts often inject `aes.js` cookie challenges that block native HTTP clients. **Immediate Step:** Deploy a simple `test.php` returning JSON on day 1 and query it via Android OkHttp. If blocked, point `/api/` to an uninhibited free PHP tier (e.g., Koyeb, Render, or free cPanel host) while keeping the web portal on InfinityFree. |
| **Google Drive Quotas & Virus Warning Interstitial** | **Medium** | Google Drive fails direct downloads on files $> 100	ext{ MB}$. Architecture enforces UI warnings advising developers to use GitHub Releases, and provides manual size input fallback. |
| **Outdated Packages via External Link Drift** | **Low** | Because files reside externally, developers could alter or delete assets. Solved by the "Report App" API and manual admin re-verification on updates. |

---
*End of Architecture Document. Preserved in repository root as `ARCHITECTURE.md`.*
