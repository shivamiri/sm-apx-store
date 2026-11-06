# SM Apx Store (Apx)

> "Discover. Download. Explore."

An open, verified Android application discovery and distribution platform featuring a native Android client and a lightweight companion website with a desktop-only administrative control center.

---

## Screenshot Placeholders

```
+------------------------------------+  +------------------------------------+
|                                    |  |                                    |
|   [ Native Android Client UI ]     |  |     [ Mobile Web Catalog UI ]      |
|                                    |  |                                    |
|        Home & Curated Shelves      |  |       App Details & Spec Strip     |
|                                    |  |                                    |
+------------------------------------+  +------------------------------------+
+------------------------------------+  +------------------------------------+
|                                    |  |                                    |
|    [ Developer Submission UI ]     |  |    [ Desktop Admin Control Panel ] |
|                                    |  |                                    |
|       Metadata & Direct Links      |  |      Review Queue & Moderation     |
|                                    |  |                                    |
+------------------------------------+  +------------------------------------+
```

---

## Key Features

* **Anonymous Discovery & Frictionless Installs:** End users browse, search, and download applications without registration. The native app downloads APK files directly from external links and invokes the Android system package installer via FileProvider.
* **Zero Host Storage & Zero Download Proxying:** The server never stores, proxies, streams, or buffers APK binaries. Applications are distributed directly from developer-hosted external endpoints (GitHub Releases preferred, Google Drive as secondary fallback).
* **Indie Developer Publishing:** Developers authenticate with Google to submit apps, upload icon and screenshot URLs, and track review status across Pending, In Review, Live, and Rejected states.
* **Mandatory Admin Moderation Gate:** All community app submissions and updates require manual administrative review and approval before appearing in public listings.
* **Zero-Downtime App Updates:** Existing Live applications remain active, visible, and downloadable while updated versions undergo administrative review.
* **Device-Wide Installed Updates Scanner:** The native app scans all installed packages across the user device and matches them against store records to alert users of available updates.
* **3-Tier Fallback Search:** Returns exact title and package matches first, automatically providing similar category apps and trending applications when zero direct matches exist.
* **Auto Size Detection Engine:** Automatically detects and formats APK file sizes on submission, backed by a manual input override when size detection cannot be resolved.
* **Force Update & Broadcast Announcements:** Administrators configure minimum required client version thresholds, remote update URLs, and global announcements broadcast directly to the client app.
* **Community Abuse & Flagging System:** Public reporting mechanism enabling users to report broken external links, copyright violations, or malicious content directly to administrators.

---

## Tech Stack

* **Mobile Client:** Native Android application in Kotlin (Min API 26 / Android 8.0+), built inside Google AI Studio.
* **Web Frontend & Backend:** Core PHP 8 and Vanilla JavaScript (ES6+) with zero frameworks, zero Node.js dependencies, zero npm packages, and zero Composer overhead.
* **Database:** MySQL 5.7+ / MariaDB 10.3+ with InnoDB and utf8mb4 encoding, imported via phpMyAdmin.
* **Hosting:** Free shared web hosting environment (such as InfinityFree / standard LAMP).
* **External Distribution:** GitHub Releases (primary) and Google Drive (secondary) for direct-to-device binary delivery.

---

## Quick Start (5 Steps)

1. **Upload Web Files:** Upload the repository web files and directories (`index.php`, `app.php`, `search.php`, `upload.php`, `my-apps.php`, `login.php`, `logout.php`, `common/`, `admin/`, `api/`, and `assets/`) to the web hosting document root (e.g., `htdocs/` or `public_html/`) using FTP or the hosting file manager.
2. **Create MySQL Database:** Open the web hosting control panel, navigate to the MySQL section, and create a new MySQL database, username, and secure password.
3. **Import Database Schema:** Launch phpMyAdmin from the hosting panel, select the newly created database, open the Import tab, and execute the complete SQL script provided in `DATABASE.md`.
4. **Configure Constants:** Open `common/config.php` on the server and update the configuration constants (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SITE_URL`, `GOOGLE_CLIENT_ID`, and `GOOGLE_CLIENT_SECRET`) with the actual database and OAuth credentials.
5. **Open Platform:** Access the web platform in any browser using your domain URL, or build and deploy the Android app from the `android/` directory using Google AI Studio.

---

## Configuration Constants (`common/config.php`)

This project does not use `.env` files or configuration loaders. All runtime configuration values are defined as PHP constants inside `common/config.php`:

| Constant Name | Purpose | Example Value |
| :--- | :--- | :--- |
| `DB_HOST` | Hostname or IP address of the MySQL database server | `sqlxxx.infinityfree.com` |
| `DB_NAME` | Name of the database created on the hosting account | `if0_xxxxxxxx_apx` |
| `DB_USER` | Database username with read/write privileges | `if0_xxxxxxxx` |
| `DB_PASS` | Password associated with the database user | `your_secure_password` |
| `DB_CHARSET` | Character set encoding for database connections | `utf8mb4` |
| `SITE_URL` | Canonical public root URL of the web platform | `https://example.com` |
| `APP_NAME` | Full display name of the platform | `SM Apx Store` |
| `APP_SHORT_NAME` | Abbreviated name used in compact headers and mobile titles | `Apx` |
| `DEV_NAME` | Name of the primary developer and owner | `Shiva Miri` |
| `GOOGLE_CLIENT_ID` | OAuth 2.0 Client ID for Google developer authentication | `xxxxxxxxxxxx.apps.googleusercontent.com` |
| `GOOGLE_CLIENT_SECRET` | OAuth 2.0 Client Secret for Google authentication | `GOCSPX-xxxxxxxxxxxxxxxx` |
| `GOOGLE_REDIRECT_URI` | Authorized OAuth redirect callback URL | `https://example.com/login.php` |

---

## Folder Structure Overview

```
sm-apx-store/
|-- PRD.md                       # Canonical Product Requirements Document
|-- DESIGN.md                    # Canonical Design System Specification
|-- ARCHITECTURE.md              # Canonical System Architecture
|-- DATABASE.md                  # Canonical Database Schemas & Seed Data
|-- API.md                       # Canonical REST JSON Endpoints Specification
|-- AGENTS.md                    # Operating Rules for AI Coding Assistants
|-- README.md                    # Public Project Documentation (This file)
|
|-- index.php                    # Public Web Home, Shelves & Catalog
|-- app.php                      # Public Web App Details & Reviews Page
|-- search.php                   # Public Web Search & Fallback Results
|-- upload.php                   # Developer App Submission & Update Form
|-- my-apps.php                  # Developer Dashboard (Google Sign-In protected)
|-- login.php                    # Developer Google OAuth Start & Callback Handler
|-- logout.php                   # Web Session Destroyer
|
|-- common/                      # Shared Web Components & Utilities
|   |-- config.php               # Database connection (PDO), constants, session boot
|   |-- header.php               # Shared HTML head, navigation bar, theme init
|   `-- bottom.php               # Shared footer, bottom navigation, toast host
|
|-- admin/                       # Desktop-Only Admin Control Panel
|   |-- index.php                # Admin Dashboard & Live Moderation Queue
|   |-- login.php                # Isolated Admin Login (Username/Password)
|   |-- apps.php                 # Admin All Apps Management & Direct Upload
|   |-- users.php                # Admin User Management & Banning
|   |-- config.php               # Admin Force Update & System Settings
|   `-- common/                  # Admin Layout Partials
|       |-- header.php           # Admin desktop sidebar & metric header
|       `-- bottom.php           # Admin closing tags & modal hosts
|
|-- api/                         # Native Android App REST JSON Endpoints
|   |-- app_config.php           # System version check for force-update dialog
|   |-- get_home.php             # Curated shelves (Featured, Hot, New, Top, Trending)
|   |-- get_app.php              # App details, screenshot gallery, review payload
|   |-- search.php               # Search query with 3-tier fallback engine
|   |-- check_updates.php        # Device-wide package list comparison
|   |-- submit_review.php        # Google ID token verified review creation
|   `-- report_app.php           # Public community app abuse reporting
|
|-- assets/                      # Shared Static Web Assets
|   |-- css/
|   |   |-- style.css            # Core design system stylesheet (DESIGN.md tokens)
|   |   `-- admin.css            # Desktop-specific admin layout styling
|   `-- js/
|       |-- main.js              # Theme switcher, search debounce, toast engine
|       `-- uploader.js          # Client size checker trigger & URL validation
|
`-- android/                     # Native Android App Source (Kotlin)
    `-- app/src/main/
        |-- AndroidManifest.xml  # Permissions (Internet, Install, Storage) & Providers
        |-- res/
        |   |-- xml/file_paths.xml # FileProvider secure path mapping
        |   `-- values/colors.xml  # Colors synced with DESIGN.md
        `-- java/com/shivamiri/apx/
            |-- MainActivity.kt        # App frame, bottom navigation routing
            |-- data/
            |   |-- ApiClient.kt       # Network client for API endpoints
            |   `-- Models.kt          # App, Shelf, Update, Config data models
            |-- ui/
            |   |-- HomeFragment.kt    # Shelves and category feed
            |   |-- SearchFragment.kt  # Search input & fallback view
            |   |-- AppDetailActivity.kt # App details, progress bar, install trigger
            |   `-- UpdatesFragment.kt # Device PackageManager scanner & updates list
            `-- utils/
                |-- DownloadHelper.kt  # Direct CDN download manager integration
                `-- InstallHelper.kt   # FileProvider content URI & package intent
```

---

## Available Scripts

There are no build, development, testing, or linting scripts in this project; files are uploaded directly to the hosting server and executed as standard Core PHP, native web assets, and native Kotlin source files.

---

## How to Contribute

Contributions are welcome from developers who value lightweight, independent open-source tools:

1. Review the canonical documents in priority order (`PRD.md`, `DESIGN.md`, `ARCHITECTURE.md`, `DATABASE.md`, `API.md`, and `AGENTS.md`) before submitting changes.
2. Adhere strictly to the zero-proxy and zero-storage constraints; never introduce server-side file buffering or local binary storage.
3. Do not add external npm dependencies, Composer packages, or frameworks.
4. If modifying database schemas, propose updates to `DATABASE.md` first.
5. Submit clear, well-described pull requests or file concise issue reports.

---

## License

This project is licensed under the MIT License. See the `LICENSE` file for full terms and conditions.

---

Developer: Shiva Miri  
Tagline: "Discover. Download. Explore."
