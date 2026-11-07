# SETUP.md - SM Apx Store Setup & Deployment Guide

Product: SM Apx Store (short name: Apx)
Developer: Shiva Miri
Tagline: "Discover. Download. Explore."
Target Environment: InfinityFree Free Shared Hosting (Core PHP 8 + MySQL)
Workflow: 100% Phone-Friendly via Mobile Browser, Hosting Control Panel & phpMyAdmin
Canonical Reference Documents: PRD.md, DESIGN.md, ARCHITECTURE.md, DATABASE.md, API.md, AGENTS.md, PLAN.md

---

## 1. Prerequisites & Required Accounts

Before beginning setup on your phone, ensure you have active credentials and accounts for the following services:

1. Free Shared Web Hosting Account:
   * Provider: InfinityFree (or comparable cPanel/LAMP free host).
   * Web Stack: PHP 8.0, 8.1, or 8.2 with standard PDO MySQL extensions enabled.
   * Web Root Directory: htdocs/
2. MySQL Database:
   * Engine: MySQL 5.7+ or MariaDB 10.3+ with InnoDB support.
   * Access Tool: phpMyAdmin (provided inside the InfinityFree control panel).
3. Google Account & Google Cloud Console:
   * Account: Standard Google account for developer authentication and Google OAuth 2.0 Web Client provisioning.
4. Mobile Tools Needed:
   * Mobile Web Browser: Chrome, Firefox, or Brave on Android (capable of toggling "Desktop site" mode).
   * Text/Code Editor on Phone: Any lightweight mobile file editor (e.g. Acode, QuickEdit, or the built-in InfinityFree File Manager editor).
   * Note on Build Tools: No laptop, command-line terminal, Git CLI, Node.js, npm, or Composer is required. All setup operations are executed directly via mobile web interfaces.

---

## 2. Obtaining the Code & Placing Folders on Hosting

All web files must reside inside the hosting account document root directory named `htdocs/`.

### 2.1 Downloading Code on Your Phone
1. Open your GitHub repository in your phone browser.
2. Switch browser view to "Desktop site" if the mobile GitHub view hides repository menus.
3. Tap the green "Code" button and tap "Download ZIP".
4. Using your phone file manager, locate the downloaded ZIP archive and extract it.

### 2.2 Uploading Folders to InfinityFree File Manager
1. Log into your InfinityFree Client Area on your phone browser.
2. Select your hosting account and tap "File Manager" (Monsta FTP web interface).
3. Navigate into the `htdocs/` directory. If a default `index2.html` exists, delete it.
4. Tap the Upload icon in the File Manager toolbar. Upload the files and subfolders so the final layout inside `htdocs/` matches this structure:

```
htdocs/
|-- index.php
|-- app.php
|-- search.php
|-- upload.php
|-- my-apps.php
|-- login.php
|-- logout.php
|-- common/
|   |-- config.php
|   |-- header.php
|   `-- bottom.php
|-- admin/
|   |-- index.php
|   |-- login.php
|   |-- logout.php
|   |-- apps.php
|   |-- users.php
|   |-- config.php
|   `-- common/
|       |-- header.php
|       `-- bottom.php
|-- api/
|   |-- app_config.php
|   |-- get_home.php
|   |-- get_app.php
|   |-- search.php
|   |-- check_updates.php
|   |-- submit_review.php
|   `-- report_app.php
`-- assets/
    |-- css/
    |   |-- style.css
    |   `-- admin.css
    `-- js/
        |-- main.js
        `-- uploader.js
```

Important Rule: Do not place the files inside a subfolder like `htdocs/sm-apx-store/`. All entry points (`index.php`, `common/`, `api/`) must sit directly inside `htdocs/`.

---

## 3. Database Setup & phpMyAdmin Migration Import

### 3.1 Creating the MySQL Database on InfinityFree
1. Log into the InfinityFree Client Area and tap "Control Panel" (VistaPanel).
2. Under the "Databases" section, tap "MySQL Databases".
3. Under "Create New Database", enter a database suffix (for example: `apx`) and tap "Create Database".
4. Note down the full database details displayed on that screen:
   * MySQL Hostname: Displayed under MySQL Details (example: `sql100.infinityfreeapp.com` or `sql205.epizy.com`). It is NEVER `localhost`.
   * MySQL Database Name: Displayed under Current Databases (example: `if0_38472910_apx`).
   * MySQL Username: Displayed under MySQL Details (example: `if0_38472910`).
   * MySQL Password: Your InfinityFree vPanel account password (visible under Account Details -> "Show Password" in the Client Area).

### 3.2 Importing Schema and Seed Data
1. Return to the "MySQL Databases" page in the InfinityFree Control Panel.
2. Next to your newly created database, tap the "phpMyAdmin" button.
3. Once phpMyAdmin loads in your phone browser, tap the database name in the left column.
4. Tap the "SQL" tab in the top navigation bar.
5. Open `DATABASE.md` on your phone, copy the entire contents of Section 8 (Complete Migration Script) and Section 9 (Seed Data), including the `rate_limits` table.
6. Paste the SQL block into the SQL query box in phpMyAdmin and tap "Go".
7. Verify Database Tables:
   Tap the database name in phpMyAdmin and confirm that all 10 tables exist:
   * `users`
   * `admins`
   * `categories`
   * `apps`
   * `app_submissions`
   * `app_screenshots`
   * `reviews`
   * `reports`
   * `system_settings`
   * `rate_limits`
8. Tap the `apps` table and confirm the seed rows (`SM Weather Ultra` and `Pixel Dungeon Quest`) are present.

---

## 4. Admin Account Security & Password Hash Provisioning

The seed migration script inserts an admin record with an inactive placeholder hash (`$2y$10$placeholderForBcryptHashDoNotUseInProductionXXXXXXXXX`). You must generate a real, secure bcrypt hash for your chosen admin password before logging in.

### 4.1 Step-by-Step Hash Generation
1. In the InfinityFree File Manager, navigate inside `htdocs/`.
2. Tap "New File" and name it `make-hash.php`.
3. Open `make-hash.php` in the file manager editor and paste this code:

```php
<?php
// Temporary script to generate bcrypt hash for admin setup.
// REPLACE 'YourChosenPasswordHere' with your actual desired password!
$password = 'YourChosenPasswordHere';
echo password_hash($password, PASSWORD_BCRYPT);
```

4. Replace `YourChosenPasswordHere` with your real password, save the file, and close the editor.
5. Open your phone browser and navigate to:
   https://yourdomain.com/make-hash.php
6. A long string starting with `$2y$10$` will appear on screen (e.g. `$2y$10$abcdef...`). Copy this entire string.
7. Open phpMyAdmin from your control panel, select your database, and tap the `admins` table.
8. Tap "Edit" on row ID 1 (username: `shivamiri`).
9. Clear the `password_hash` field, paste your copied bcrypt hash string, and tap "Go" to save.

CRITICAL SECURITY WARNING:
Return to the InfinityFree File Manager immediately and DELETE `make-hash.php`. Never leave this file on the public web server, as anyone visiting that URL could view or compute hashes.

---

## 5. Google Cloud Console OAuth 2.0 Web Client Setup

To enable Google sign-in for developers and reviewers, configure a Google OAuth 2.0 Client ID via mobile browser:

1. Open your phone browser and navigate to:
   https://console.cloud.google.com/
2. Switch browser view to "Desktop site" mode.
3. Tap the project dropdown at the top and tap "New Project". Enter project name "SM Apx Store" and tap "Create".
4. Open the left hamburger menu and tap "APIs & Services" -> "OAuth consent screen".
   * Select User Type: "External" and tap "Create".
   * Enter App name: "SM Apx Store".
   * Enter User support email: your personal Gmail.
   * Enter Developer contact email: your personal Gmail.
   * Tap "Save and Continue" through Scopes and Test Users.
5. In the left menu, tap "Credentials".
6. Tap "+ CREATE CREDENTIALS" at the top and select "OAuth client ID".
   * Application type: Select "Web application".
   * Name: "Apx Web Client".
   * Authorized JavaScript origins:
     Add your canonical domain URL:
     https://yourdomain.com
   * Authorized redirect URIs:
     Add the exact OAuth callback URL:
     https://yourdomain.com/login.php
7. Tap "CREATE". A dialog displays your Client ID and Client Secret. Copy both values.

---

## 6. Configuration: Editing `common/config.php`

Open the InfinityFree File Manager, navigate to `htdocs/common/`, and open `config.php` in the editor. Populate your real database credentials, site URLs, and Google OAuth keys:

```php
<?php
// common/config.php - Global Configuration & Core Database Connection
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------------------
// Database Credentials (from InfinityFree MySQL Control Panel)
// ----------------------------------------------------------------------------
define('DB_HOST', 'sql100.infinityfreeapp.com'); // Put your real MySQL Host here (NOT localhost)
define('DB_NAME', 'if0_38472910_apx');          // Your full database name
define('DB_USER', 'if0_38472910');              // Your database username
define('DB_PASS', 'YourVPanelPasswordHere');    // Your vPanel account password
define('DB_CHARSET', 'utf8mb4');

// ----------------------------------------------------------------------------
// Platform Information & Base URL
// ----------------------------------------------------------------------------
define('SITE_URL', 'https://yourdomain.com');   // Full URL without trailing slash
define('APP_NAME', 'SM Apx Store');
define('APP_SHORT_NAME', 'Apx');
define('DEV_NAME', 'Shiva Miri');

// ----------------------------------------------------------------------------
// Google OAuth 2.0 Credentials (from Google Cloud Console)
// ----------------------------------------------------------------------------
define('GOOGLE_CLIENT_ID', 'xxxxxxxxxxxx-xxxxxxxx.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/login.php');

// ----------------------------------------------------------------------------
// Database Connection Helper (PDO)
// ----------------------------------------------------------------------------
function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die(json_encode([
                "success" => false,
                "data"    => null,
                "error"   => [
                    "code"    => "DATABASE_ERROR",
                    "message" => "Service temporarily unavailable. Database connection failed.",
                    "details" => []
                ]
            ]));
        }
    }
    return $pdo;
}

// ----------------------------------------------------------------------------
// Unified JSON Envelope Helper (Matches ARCHITECTURE.md and API.md)
// ----------------------------------------------------------------------------
function jsonResponse(bool $success, mixed $data = null, ?array $error = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'error'   => $error
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------------------------------------------------------------
// Table-Based Rate Limiting Helper (Matches DATABASE.md rate_limits table)
// ----------------------------------------------------------------------------
function checkRateLimit(string $ip, string $endpoint, int $limit, int $windowSeconds): bool {
    $db = getDbConnection();
    $currentTime = time();
    $windowStartThreshold = $currentTime - $windowSeconds;

    $stmt = $db->prepare("SELECT hits, window_start FROM rate_limits WHERE ip_address = ? AND endpoint = ?");
    $stmt->execute([$ip, $endpoint]);
    $record = $stmt->fetch();

    if ($record) {
        if ($record['window_start'] < $windowStartThreshold) {
            $update = $db->prepare("UPDATE rate_limits SET hits = 1, window_start = ? WHERE ip_address = ? AND endpoint = ?");
            $update->execute([$currentTime, $ip, $endpoint]);
            return true;
        }

        if ($record['hits'] >= $limit) {
            return false;
        }

        $increment = $db->prepare("UPDATE rate_limits SET hits = hits + 1 WHERE ip_address = ? AND endpoint = ?");
        $increment->execute([$ip, $endpoint]);
        return true;
    } else {
        $insert = $db->prepare("INSERT INTO rate_limits (ip_address, endpoint, hits, window_start) VALUES (?, ?, 1, ?)");
        $insert->execute([$ip, $endpoint, $currentTime]);
        return true;
    }
}
```

Save and close the file.

---

## 7. Verification Steps: Testing All Components from Your Phone

Perform these verification checks in order using your phone browser:

### 7.1 Verify System Config API & Bot Probe
1. Open this URL in your mobile browser:
   https://yourdomain.com/api/app_config.php
2. Expected Result: The browser displays clean JSON with HTTP 200 status:
   ```json
   {
     "success": true,
     "data": {
       "min_app_version_code": 1,
       "latest_app_version_code": 1,
       "update_apk_url": "https://github.com/shivamiri/sm-apx-store/releases/download/v1.0.0/apx-store.apk",
       "force_update_message": "A critical update is required to continue exploring and downloading apps seamlessly on SM Apx Store.",
       "announcement_banner": "Welcome to SM Apx Store: Discover, Download, and Explore verified indie apps with zero friction!"
     },
     "error": null
   }
   ```
   If this JSON appears, the database connection and the hosting PHP API layer are working.

### 7.2 Verify Storefront Home & Shelves
1. Open your main website URL:
   https://yourdomain.com/
2. Expected Result:
   * The dark navy `#070B14` interface renders with electric cyan `#00E5FF` accents.
   * Category filter chips (Tools, Games, Productivity) scroll horizontally.
   * The Featured carousel shows `SM Weather Ultra`.
   * Shelf rows render seed cards with squircle icons, titles, and "18.4 MB" real size badges.

### 7.3 Verify App Details & Direct Download
1. On the home page, tap on `SM Weather Ultra`.
2. URL loads: https://yourdomain.com/app.php?id=1
3. Expected Result:
   * Spec strip displays rating (4.8), size (18.4 MB), age, and downloads.
   * Screenshots slider displays preview images.
   * Tap "Install / Download APK": Browser triggers direct file download from the external GitHub Release URL without redirecting away or proxying bytes through PHP.

### 7.4 Verify 3-Tier Search Fallback
1. Open https://yourdomain.com/search.php
2. Type "weather" into the search bar: confirm direct result `SM Weather Ultra` is displayed.
3. Type "nonexistentappname" into search bar: confirm the fallback recommendation banner appears stating no exact matches were found, accompanied by recommended apps.

### 7.5 Verify Admin Panel Authentication (Desktop Site Mode)
1. In your phone browser menu, enable "Desktop site" mode.
2. Navigate to:
   https://yourdomain.com/admin/login.php
3. Enter your username (`shivamiri`) and the admin password you set in Section 4.
4. Tap "Sign In".
5. Expected Result: You are redirected to `admin/index.php`. The desktop sidebar, metric counters (Total Apps, Live Apps, Pending Submissions), and submission table render cleanly.

---

## 8. Common Setup Errors & Proven Fixes

### Error 1: Database Connection Failed (PDOException: Access denied or Server unreachable)
* Cause: Setting `DB_HOST` to `localhost`. On InfinityFree and most free shared hosts, the MySQL server is hosted on a separate cluster.
* Fix: Open `common/config.php` and verify `DB_HOST` is set to the exact hostname shown in the InfinityFree MySQL control panel (e.g. `sql100.infinityfreeapp.com` or `sql205.epizy.com`), NOT `localhost`. Also verify `DB_USER` matches the account username prefix (e.g. `if0_38472910`).

### Error 2: HTTP 500 Internal Server Error on Every Page
* Cause: Invalid `.htaccess` directives or unsupported Apache modules on free shared hosting (such as `Options +FollowSymLinks`, mod_rewrite syntax errors, or PHP flag directives that free hosts prohibit).
* Fix: Check your `.htaccess` file in `htdocs/`. Ensure it does not contain `php_value`, `php_flag`, or custom server directives. A clean, minimal `.htaccess` for InfinityFree should only contain standard index handling and directory protection:
  ```apache
  DirectoryIndex index.php
  Options -Indexes
  ```

### Error 3: "Headers Already Sent" Warning or Broken JSON Responses
* Cause: Whitespace, blank lines, or UTF-8 BOM characters placed before the opening `<?php` tag or after closing `?>` tags in PHP files (especially `common/config.php` or `api/` scripts).
* Fix: Ensure that `<?php` is the absolute first token on line 1, column 1 of every file with zero leading spaces or newlines. Omit the closing `?>` tag at the end of pure PHP files to completely eliminate trailing whitespace leaks.

### Error 4: Admin Login Fails with "Invalid username or password"
* Cause: Attempting to log in before replacing the placeholder bcrypt hash in the `admins` table, or typing a password that does not match the hash.
* Fix: Follow Section 4 of this guide: create `make-hash.php`, generate a fresh bcrypt hash for your chosen password, paste it into row 1 of the `admins` table in phpMyAdmin, and immediately delete `make-hash.php`.

### Error 5: Google Sign-In Fails with `redirect_uri_mismatch` (Error 400)
* Cause: The redirect URI configured in Google Cloud Console does not match the exact URL from which the request originates (e.g. `http://` vs `https://`, missing `www`, or trailing slash discrepancy).
* Fix: Open Google Cloud Console -> Credentials -> OAuth 2.0 Client IDs. Verify that Authorized redirect URIs contains the exact address configured as `GOOGLE_REDIRECT_URI` in `common/config.php` (e.g. `https://yourdomain.com/login.php`). Ensure protocol (`https://`) and domain match character for character.

### Error 6: InfinityFree Bot-Check Interstitial Page Blocks Non-Browser API Requests
* Cause: InfinityFree uses automated client-side JavaScript challenges (`aes.js`) to block scrapers. Web browsers solve this challenge automatically, but non-browser mobile HTTP clients (such as Android OkHttp or curl) that cannot execute JavaScript receive a HTTP 503 or HTML challenge block instead of JSON.
* Fix: Test your API using the health probe (`api/app_config.php`). If your future native Android app is blocked by InfinityFree's security challenge, use an uninhibited free PHP tier (such as Koyeb, Render, or a free standard cPanel host) for the `api/` directory while keeping your main website hosted on InfinityFree.

### Error 7: Upload Size Limit or Timeout When Submitting Apps
* Cause: Developers attempting to upload raw APK files directly to the server, or free shared hosting terminating requests after 20-30 seconds.
* Fix: Remember that SM Apx Store operates under a strict zero-storage, zero-proxy rule. Developers must NEVER upload `.apk` files to the web server. Developers provide public GitHub Releases or Google Drive URLs only. The server only validates links via lightweight HTTP `HEAD` requests and stores text URLs.

### Error 8: Session Logout / Session Lost Across Pages (HTTP vs HTTPS)
* Cause: Navigating between `http://` and `https://` protocols, or session cookie domain mismatches on free subdomains (e.g. `example.infinityfreeapp.com`).
* Fix: Enforce HTTPS across your entire site. In your hosting control panel, enable "Force HTTPS" or add an SSL redirect rule in `.htaccess`:
  ```apache
  RewriteEngine On
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
  ```
  Ensure `SITE_URL` in `common/config.php` starts with `https://`.

---
End of Setup Guide. Document preserved in repository root as SETUP.md.
