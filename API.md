# API Specification: API.md

Specification Version: 1.0.0  
Target Release: MVP (Phase 1)  
Product Name: SM Apx Store (short name: Apx)  
Developer: Shiva Miri  
Tagline: "Discover. Download. Explore."  
Base Canonical References: PRD.md (v1.1.0), ARCHITECTURE.md (v1.0.0), DATABASE.md (v1.0.0)  

---

## 1. Global API Standards & Architectural Conventions

### 1.1 Base URL & Environment Addressing
All public REST JSON endpoints live inside the api/ directory relative to the web server root.
* Base Production Endpoint URL: https://example.com/api/
* Protocols: Strict HTTPS (TLS 1.2+ mandatory). Plain HTTP requests are rejected or redirected.
* Content Types: All requests sending payloads must supply Content-Type: application/json; charset=utf-8. All responses return Content-Type: application/json; charset=utf-8.
* Constraint Check (No URL Markdown Links): All URLs throughout this document and all examples are formatted strictly as plain text without Markdown brackets or link formatting.

### 1.2 Standard Unified Response Envelope
Every response dispatched by the API adheres strictly to the single canonical envelope specified in ARCHITECTURE.md:

```json
{
  "success": true,
  "data": {},
  "error": null
}
```

* `success` (boolean): `true` when the operation succeeds (HTTP status 200 or 201), `false` when any client, validation, or server error occurs.
* `data` (object, array, or null): Payload container when `success` is `true`. Always `null` on failure.
* `error` (object or null): Error detail container when `success` is `false`. Always `null` on success.

### 1.3 Unified Error Response Format
When `success` is `false`, the `error` object contains structured fields enabling deterministic error handling in the native Kotlin app and web client:

```json
{
  "success": false,
  "data": null,
  "error": {
    "code": "ERROR_CODE_STRING",
    "message": "Human readable summary of the issue.",
    "details": []
  }
}
```

#### Standard Error Code Registry
| HTTP Status | Error Code (`code`) | Trigger Condition |
| :--- | :--- | :--- |
| 400 Bad Request | `INVALID_PAYLOAD` | Request body is not valid JSON or contains malformed data types. |
| 400 Bad Request | `VALIDATION_FAILED` | Missing mandatory fields or failing validation constraints. |
| 401 Unauthorized | `AUTH_TOKEN_MISSING` | Authorization header omitted on protected endpoints. |
| 401 Unauthorized | `AUTH_TOKEN_INVALID` | Google ID token verification failed or expired. |
| 403 Forbidden | `ACCOUNT_BANNED` | Google user account has `is_banned = 1` in the `users` table. |
| 404 Not Found | `RESOURCE_NOT_FOUND` | App package, ID, or setting does not exist or `status != 'Live'`. |
| 409 Conflict | `DUPLICATE_RESOURCE` | Attempting to submit a second review for the same app. |
| 429 Too Many Requests | `RATE_LIMIT_EXCEEDED` | Request threshold exceeded for client IP. |
| 500 Internal Error | `DATABASE_ERROR` | Database query failure (PDOException) handled securely. |
| 500 Internal Error | `SERVER_ERROR` | Unhandled runtime exception or host issue. |

### 1.4 API Versioning Strategy
* Strategy: URI query parameter or header-based semantic versioning fallback (`/api/get_app.php?v=1`).
* Baseline: The current public release is Version 1 (`v1`).
* Backward Compatibility Guarantee: The schema envelope (`success`, `data`, `error`) and database mapping field names remain immutable for `v1`. Deprecations or breaking schema modifications will introduce an explicit `v2/` directory structure.

### 1.5 Pagination, Sorting & Filtering Rules
* Query Parameters:
  * `page` (integer, optional, default: `1`): Current 1-based page index.
  * `limit` (integer, optional, default: `20`, maximum: `50`): Number of items per page.
* Standard Pagination Meta Object:
  Any endpoint returning a paginated list includes a `pagination` block inside `data`:
  ```json
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total_records": 48,
    "total_pages": 3,
    "has_next_page": true,
    "has_prev_page": false
  }
  ```
* Catalog Filtering Constraints:
  Public endpoints (`get_home.php`, `get_app.php`, `search.php`, `check_updates.php`) query strictly `status = 'Live'` and `deleted_at IS NULL`. Any app in `Pending`, `In Review`, `Rejected`, or `Banned` status is never exposed to the public API.

### 1.6 Rate Limiting Strategy (Free Shared Hosting Compatible)
* Shared Hosting Constraint: No Redis, Memcached, or asynchronous daemons exist on free shared hosting (such as InfinityFree).
* Plain PHP + MySQL Implementation:
  Rate limiting is enforced at the PHP layer using a lightweight, self-purging MySQL table `rate_limits` or an in-memory database lock keyed by client IP and endpoint:
  * Schema Reference:
    ```sql
    CREATE TABLE IF NOT EXISTS rate_limits (
      ip_address VARCHAR(45) NOT NULL,
      endpoint VARCHAR(64) NOT NULL,
      hits INT UNSIGNED NOT NULL DEFAULT 1,
      window_start INT UNSIGNED NOT NULL,
      PRIMARY KEY (ip_address, endpoint)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ```
  * Enforced Limits:
    * Public Read Endpoints (`get_home.php`, `get_app.php`, `app_config.php`): 120 requests per minute per IP.
    * Search & Updates (`search.php`, `check_updates.php`): 60 requests per minute per IP.
    * Write Actions (`submit_review.php`, `report_app.php`): 10 requests per minute per IP.
  * Headers Emitted:
    * `X-RateLimit-Limit`: Maximum permitted hits in window.
    * `X-RateLimit-Remaining`: Remaining hits in current window.
    * `X-RateLimit-Reset`: Unix timestamp when counter resets.

### 1.7 Free Host Bot Protection Risk & Health Probe Strategy
* Risk Analysis: Free shared hosting providers (such as InfinityFree) frequently deploy client-side JavaScript bot challenges (e.g. `aes.js` or Cloudflare challenge gates) that require a browser JavaScript engine to solve. Native mobile clients (Kotlin OkHttp / HttpURLConnection) do not execute JavaScript and will receive a HTTP 503 or HTML challenge block instead of JSON.
* Proactive Health Probe Endpoint:
  The native Android app must execute a lightweight diagnostic probe upon cold boot before dispatching functional catalog requests:
  * Probe Target: `GET https://example.com/api/app_config.php`
  * Evaluation: If the native app receives HTTP 200 and a valid JSON response containing `"success": true`, the host pathway is open.
  * Fallback Action: If the response is HTML, HTTP 503, or contains `<script>`, the app alerts the user gracefully: *"Platform service temporarily unreachable due to network security checks. Please check for updates."*

---

## 2. Resource Mapping Table

Every endpoint documented maps directly to features in PRD.md and database tables in DATABASE.md:

| Endpoint Script | Resource Group | PRD Feature Section | Target Database Tables |
| :--- | :--- | :--- | :--- |
| `api/app_config.php` | System & Config | PRD Section 6.4 (Force Update) | `system_settings` |
| `api/get_home.php` | Catalog & Discovery | PRD Section 6.1 (Curated Shelves) | `apps`, `categories` |
| `api/get_app.php` | App Details & Reviews | PRD Section 6.1, 6.2, 6.3 (Details, Real Size, Screenshots) | `apps`, `app_screenshots`, `reviews`, `users`, `categories` |
| `api/search.php` | Discovery & Search | PRD Section 6.1 (3-Tier Fallback Search) | `apps`, `categories` |
| `api/check_updates.php`| Device Updates | PRD Section 6.4 (Device-Wide Updates Scanner) | `apps` |
| `api/submit_review.php`| User Feedback | PRD Section 6.1 (Google Auth Reviews) | `reviews`, `users`, `apps` |
| `api/report_app.php` | Platform Moderation | PRD Section 6.1 (Abuse & Broken Link Reporting) | `reports`, `apps` |

*Zero Download-Proxy Rule:* In strict compliance with PRD and Architecture directives, no endpoint streams, buffers, or proxies APK binary data. All download actions in `get_app.php` provide direct public URLs pointing to GitHub Releases or Google Drive.

---

## 3. Endpoints Documentation

### 3.1 Group: System & Configuration

#### 3.1.1 `GET /api/app_config.php`
* Purpose: Delivers global store configuration parameters, broadcast announcements, and version threshold data for the native Android app's force-update modal dialog. Acts as the zero-dependency bot-protection probe on app cold boot.
* HTTP Method: `GET`
* URL: `https://example.com/api/app_config.php`
* Authentication: None (Public access).
* Request Headers:
  * `Accept: application/json`
* Query Parameters: None.
* Request Body: None.
* Database Interaction: Reads `system_settings` table keys (`min_app_version_code`, `latest_app_version_code`, `update_apk_url`, `force_update_message`, `announcement_banner`).

##### Success Response (HTTP 200 OK)
```json
{
  "success": true,
  "data": {
    "min_app_version_code": 1,
    "latest_app_version_code": 2,
    "update_apk_url": "https://github.com/shivamiri/sm-apx-store/releases/download/v1.1.0/apx-store-v1.1.0.apk",
    "force_update_message": "A critical update is required to continue exploring and downloading apps seamlessly on SM Apx Store.",
    "announcement_banner": "Welcome to SM Apx Store: Discover, Download, and Explore verified indie apps with zero friction!",
    "server_time": 1793962560
  },
  "error": null
}
```

##### Error Responses
* HTTP 500 Internal Server Error
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "DATABASE_ERROR",
      "message": "Unable to load system configuration.",
      "details": []
    }
  }
  ```

---

### 3.2 Group: Catalog & Discovery

#### 3.2.1 `GET /api/get_home.php`
* Purpose: Returns all curated shelves for the storefront home view (Featured carousel, Hot Today, New Arrivals, Top Rated, Trending) as well as the active category filter list.
* HTTP Method: `GET`
* URL: `https://example.com/api/get_home.php`
* Authentication: None (Public access).
* Request Headers:
  * `Accept: application/json`
* Query Parameters:
  * `category` (string, optional): Category slug to filter shelves (e.g. `tools`, `games`). Defaults to `all`.
* Request Body: None.
* Database Interaction: Reads `categories` (ordered by `sort_order ASC`) and `apps` (`WHERE status = 'Live' AND deleted_at IS NULL`) segmented by shelf criteria (`is_featured = 1`, `download_count DESC`, `created_at DESC`, `rating_average DESC`).

##### Success Response (HTTP 200 OK)
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Tools",
        "slug": "tools"
      },
      {
        "id": 2,
        "name": "Games",
        "slug": "games"
      },
      {
        "id": 3,
        "name": "Productivity",
        "slug": "productivity"
      }
    ],
    "shelves": {
      "featured": [
        {
          "id": 1,
          "app_name": "SM Weather Ultra",
          "package_name": "com.shivamiri.weather",
          "category": "Tools",
          "version_name": "1.2.0",
          "version_code": 5,
          "size_formatted": "18.4 MB",
          "size_bytes": 19293798,
          "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
          "feature_graphic_url": "https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=1024",
          "rating_average": 4.80,
          "download_count": 1420,
          "is_admin_app": true
        }
      ],
      "hot_today": [
        {
          "id": 2,
          "app_name": "Pixel Dungeon Quest",
          "package_name": "com.aarav.pixeldungeon",
          "category": "Games",
          "version_name": "2.1.0",
          "version_code": 12,
          "size_formatted": "43.0 MB",
          "size_bytes": 45088768,
          "icon_url": "https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=256",
          "feature_graphic_url": "https://images.unsplash.com/photo-1511512578047-dfb367046420?w=1024",
          "rating_average": 4.50,
          "download_count": 890,
          "is_admin_app": false
        }
      ],
      "new_arrivals": [
        {
          "id": 1,
          "app_name": "SM Weather Ultra",
          "package_name": "com.shivamiri.weather",
          "category": "Tools",
          "version_name": "1.2.0",
          "version_code": 5,
          "size_formatted": "18.4 MB",
          "size_bytes": 19293798,
          "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
          "feature_graphic_url": "https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=1024",
          "rating_average": 4.80,
          "download_count": 1420,
          "is_admin_app": true
        }
      ],
      "top_rated": [
        {
          "id": 1,
          "app_name": "SM Weather Ultra",
          "package_name": "com.shivamiri.weather",
          "category": "Tools",
          "version_name": "1.2.0",
          "version_code": 5,
          "size_formatted": "18.4 MB",
          "size_bytes": 19293798,
          "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
          "feature_graphic_url": "https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=1024",
          "rating_average": 4.80,
          "download_count": 1420,
          "is_admin_app": true
        }
      ],
      "trending": [
        {
          "id": 2,
          "app_name": "Pixel Dungeon Quest",
          "package_name": "com.aarav.pixeldungeon",
          "category": "Games",
          "version_name": "2.1.0",
          "version_code": 12,
          "size_formatted": "43.0 MB",
          "size_bytes": 45088768,
          "icon_url": "https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=256",
          "feature_graphic_url": "https://images.unsplash.com/photo-1511512578047-dfb367046420?w=1024",
          "rating_average": 4.50,
          "download_count": 890,
          "is_admin_app": false
        }
      ]
    }
  },
  "error": null
}
```

##### Error Responses
* HTTP 429 Too Many Requests
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "RATE_LIMIT_EXCEEDED",
      "message": "Too many requests. Please slow down.",
      "details": []
    }
  }
  ```
* HTTP 500 Internal Server Error
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "DATABASE_ERROR",
      "message": "Error retrieving storefront catalog.",
      "details": []
    }
  }
  ```

---

#### 3.2.2 `GET /api/get_app.php`
* Purpose: Provides complete metadata for an application detail view, including real auto-detected byte size, external APK download URL, 1 to 8 screenshot gallery URLs, YouTube trailer URL, developer identity, and approved user reviews.
* HTTP Method: `GET`
* URL: `https://example.com/api/get_app.php`
* Authentication: None (Public access).
* Request Headers:
  * `Accept: application/json`
* Query Parameters:
  * Either `id` (integer) OR `package_name` (string) must be supplied:
    * `id`: e.g. `1`
    * `package_name`: e.g. `com.shivamiri.weather`
* Request Body: None.
* Database Interaction: Reads `apps` (`WHERE status = 'Live' AND deleted_at IS NULL`), joins `categories` and `users` (developer name/avatar), joins `app_screenshots` (`ORDER BY sort_order ASC`), and fetches `reviews` (`WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 20`).

##### Success Response (HTTP 200 OK)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "app_name": "SM Weather Ultra",
    "package_name": "com.shivamiri.weather",
    "developer": {
      "name": "Shiva Miri",
      "is_admin": true
    },
    "category": {
      "id": 1,
      "name": "Tools",
      "slug": "tools"
    },
    "version": {
      "version_name": "1.2.0",
      "version_code": 5
    },
    "size": {
      "formatted": "18.4 MB",
      "bytes": 19293798
    },
    "download": {
      "source_type": "github",
      "apk_url": "https://github.com/shivamiri/weather/releases/download/v1.2.0/weather-release.apk"
    },
    "media": {
      "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
      "feature_graphic_url": "https://images.unsplash.com/photo-1534088568595-a066f410bcda?w=1024",
      "youtube_url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
      "screenshots": [
        {
          "id": 1,
          "url": "https://images.unsplash.com/photo-1504608524841-42fe6f032b4b?w=600",
          "sort_order": 0
        },
        {
          "id": 2,
          "url": "https://images.unsplash.com/photo-1561484930-998b6a7b22e8?w=600",
          "sort_order": 1
        }
      ]
    },
    "description": "Real-time glassmorphic meteorological radar with hyper-local forecasts and lightning trackers.",
    "metrics": {
      "download_count": 1420,
      "rating_average": 4.80,
      "review_count": 2
    },
    "reviews": [
      {
        "id": 1,
        "user_name": "Aarav Dev",
        "user_avatar": "https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150",
        "rating": 5,
        "comment": "Incredible glassmorphic interface and loads instantly without ads. Best weather utility on the store!",
        "created_at": "2026-11-01 10:14:00"
      }
    ]
  },
  "error": null
}
```

##### Error Responses
* HTTP 400 Bad Request
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "VALIDATION_FAILED",
      "message": "Missing required parameter. Provide either 'id' or 'package_name'.",
      "details": []
    }
  }
  ```
* HTTP 404 Not Found
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "RESOURCE_NOT_FOUND",
      "message": "The requested application was not found or is currently not available.",
      "details": []
    }
  }
  ```

---

#### 3.2.3 `GET /api/search.php`
* Purpose: Delivers application search results using the mandatory 3-Tier Fallback algorithm defined in PRD Section 6.1. When an exact or prefix match yields results, it returns Tier 1. When zero direct matches exist, it automatically falls back to Tier 2 and Tier 3 (similar category apps and trending apps) with an explicit `fallback_applied` indicator.
* HTTP Method: `GET`
* URL: `https://example.com/api/search.php`
* Authentication: None (Public access).
* Request Headers:
  * `Accept: application/json`
* Query Parameters:
  * `q` (string, required): Search query string (minimum 1 character).
  * `page` (integer, optional, default: `1`).
  * `limit` (integer, optional, default: `20`).
* Request Body: None.
* Database Interaction:
  * Tier 1: Queries `apps` matching `app_name LIKE '%q%'` or `package_name LIKE '%q%'` or `FULLTEXT` where `status = 'Live' AND deleted_at IS NULL`.
  * Tier 2 / 3 Fallback (if Tier 1 count is 0): Queries similar apps in the same or nearest category, plus top trending apps (`ORDER BY download_count DESC LIMIT 5`).

##### Success Response: Direct Matches (Tier 1 Hit - HTTP 200 OK)
```json
{
  "success": true,
  "data": {
    "query": "weather",
    "fallback_applied": false,
    "fallback_message": null,
    "results": [
      {
        "id": 1,
        "app_name": "SM Weather Ultra",
        "package_name": "com.shivamiri.weather",
        "category": "Tools",
        "version_name": "1.2.0",
        "size_formatted": "18.4 MB",
        "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
        "rating_average": 4.80,
        "download_count": 1420
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total_records": 1,
      "total_pages": 1,
      "has_next_page": false,
      "has_prev_page": false
    }
  },
  "error": null
}
```

##### Success Response: Fallback Triggered (Tier 2/3 Fallback - HTTP 200 OK)
```json
{
  "success": true,
  "data": {
    "query": "supermario",
    "fallback_applied": true,
    "fallback_message": "No exact matches found for 'supermario'. You might like these recommendations:",
    "results": [
      {
        "id": 2,
        "app_name": "Pixel Dungeon Quest",
        "package_name": "com.aarav.pixeldungeon",
        "category": "Games",
        "version_name": "2.1.0",
        "size_formatted": "43.0 MB",
        "icon_url": "https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=256",
        "rating_average": 4.50,
        "download_count": 890,
        "recommendation_reason": "Trending in Games"
      },
      {
        "id": 1,
        "app_name": "SM Weather Ultra",
        "package_name": "com.shivamiri.weather",
        "category": "Tools",
        "version_name": "1.2.0",
        "size_formatted": "18.4 MB",
        "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
        "rating_average": 4.80,
        "download_count": 1420,
        "recommendation_reason": "Top Rated on Apx"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total_records": 2,
      "total_pages": 1,
      "has_next_page": false,
      "has_prev_page": false
    }
  },
  "error": null
}
```

##### Error Responses
* HTTP 400 Bad Request
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "VALIDATION_FAILED",
      "message": "Search query parameter 'q' is required.",
      "details": []
    }
  }
  ```

---

### 3.3 Group: Application Updates

#### 3.3.1 `POST /api/check_updates.php`
* Purpose: Accepts a device-wide batch list of installed Android packages and their current installed `version_code` integers (collected by the native app querying device `PackageManager`), matches them against the live store database using the covering index `idx_apps_pkg_status`, and returns apps that have a strictly higher `version_code` available.
* HTTP Method: `POST`
* URL: `https://example.com/api/check_updates.php`
* Authentication: None (Public access).
* Request Headers:
  * `Content-Type: application/json`
  * `Accept: application/json`
* Query Parameters: None.
* Request Body Format: JSON object containing an array of packages:

##### Request Body Example
```json
{
  "packages": [
    {
      "package_name": "com.shivamiri.weather",
      "version_code": 3
    },
    {
      "package_name": "com.aarav.pixeldungeon",
      "version_code": 12
    },
    {
      "package_name": "com.unrelated.installedapp",
      "version_code": 100
    }
  ]
}
```

* Database Interaction: Executes a parameterized covering query against `apps`:
  `SELECT id, app_name, package_name, version_code, version_name, apk_source_url, size_formatted, size_bytes, icon_url FROM apps WHERE package_name IN (...) AND status = 'Live' AND deleted_at IS NULL`
  Filters where database `version_code` > client `version_code`.

##### Success Response (HTTP 200 OK)
```json
{
  "success": true,
  "data": {
    "updates_available_count": 1,
    "updates": [
      {
        "id": 1,
        "app_name": "SM Weather Ultra",
        "package_name": "com.shivamiri.weather",
        "installed_version_code": 3,
        "latest_version_code": 5,
        "latest_version_name": "1.2.0",
        "size_formatted": "18.4 MB",
        "size_bytes": 19293798,
        "icon_url": "https://images.unsplash.com/photo-1592210454359-9043f067919b?w=256",
        "apk_url": "https://github.com/shivamiri/weather/releases/download/v1.2.0/weather-release.apk"
      }
    ]
  },
  "error": null
}
```

##### Error Responses
* HTTP 400 Bad Request
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "INVALID_PAYLOAD",
      "message": "Invalid or missing 'packages' array in request body.",
      "details": []
    }
  }
  ```

---

### 3.4 Group: User Interaction & Community

#### 3.4.1 `POST /api/submit_review.php`
* Purpose: Allows Google-authenticated users to submit a 1-to-5 star rating and feedback comment for an application. Enforces the strict rule of maximum one review per user per app. Recalculates `rating_average` and `review_count` in `apps`.
* HTTP Method: `POST`
* URL: `https://example.com/api/submit_review.php`
* Authentication Required: Yes.
  * Role: Authenticated User / Developer (`users` table).
  * Method: Authorization Bearer header transmitting a verified Google ID Token (`id_token`).
* Request Headers:
  * `Authorization: Bearer <GOOGLE_ID_TOKEN>`
  * `Content-Type: application/json`
  * `Accept: application/json`
* Query Parameters: None.
* Request Body Schema:
  * `app_id` (integer, required): Target application ID.
  * `rating` (integer, required): Star score (integer between `1` and `5`).
  * `comment` (string, required): Feedback text (5 to 1,000 characters).

##### Request Body Example
```json
{
  "app_id": 1,
  "rating": 5,
  "comment": "The dark cyan glassmorphism looks gorgeous and works smoothly on my phone!"
}
```

* Database Interaction:
  1. Validates Google token, resolves or provisions `users` row.
  2. Verifies `users.is_banned = 0`.
  3. Verifies `user_id != apps.user_id` (developers cannot review their own apps).
  4. Inserts into `reviews (app_id, user_id, rating, comment)`.
  5. Updates `apps.rating_average` and `apps.review_count`.

##### Success Response (HTTP 201 Created)
```json
{
  "success": true,
  "data": {
    "review_id": 3,
    "app_id": 1,
    "rating": 5,
    "comment": "The dark cyan glassmorphism looks gorgeous and works smoothly on my phone!",
    "updated_rating_average": 4.87,
    "updated_review_count": 3,
    "created_at": "2026-11-06 16:16:00"
  },
  "error": null
}
```

##### Error Responses
* HTTP 400 Bad Request
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "VALIDATION_FAILED",
      "message": "Rating must be an integer between 1 and 5, and comment must be at least 5 characters.",
      "details": []
    }
  }
  ```
* HTTP 401 Unauthorized
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "AUTH_TOKEN_INVALID",
      "message": "Google authentication token is missing, expired, or invalid.",
      "details": []
    }
  }
  ```
* HTTP 403 Forbidden
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "ACCOUNT_BANNED",
      "message": "Your account has been suspended by the store administrator.",
      "details": []
    }
  }
  ```
* HTTP 409 Conflict (Duplicate Review)
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "DUPLICATE_RESOURCE",
      "message": "You have already reviewed this application. You may edit your existing review.",
      "details": []
    }
  }
  ```

---

#### 3.4.2 `POST /api/report_app.php`
* Purpose: Allows any community user (anonymous or signed-in) to flag an application for malware, broken external download links, copyright violation, or false version information. Creates an actionable item in the admin review center.
* HTTP Method: `POST`
* URL: `https://example.com/api/report_app.php`
* Authentication: None (Public community reporting).
* Request Headers:
  * `Content-Type: application/json`
  * `Accept: application/json`
* Query Parameters: None.
* Request Body Schema:
  * `app_id` (integer, required): ID of the flagged app.
  * `reason` (string, required): Short classification (e.g. `Broken Download Link`, `Malware / Harmful`, `False Version Display`, `Inappropriate Content`).
  * `details` (string, optional): Detailed explanation of the violation (up to 1,000 characters).

##### Request Body Example
```json
{
  "app_id": 2,
  "reason": "Broken Download Link",
  "details": "The GitHub release direct asset URL returns HTTP 404 when tapping Install."
}
```

* Database Interaction: Inserts record into `reports (app_id, reporter_ip, reason, details, status)`.

##### Success Response (HTTP 201 Created)
```json
{
  "success": true,
  "data": {
    "report_id": 5,
    "app_id": 2,
    "status": "pending",
    "message": "Thank you. Your report has been submitted for administrative investigation."
  },
  "error": null
}
```

##### Error Responses
* HTTP 400 Bad Request
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "VALIDATION_FAILED",
      "message": "Invalid report submission. Provide a valid 'app_id' and 'reason'.",
      "details": []
    }
  }
  ```
* HTTP 404 Not Found
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "RESOURCE_NOT_FOUND",
      "message": "The application you are reporting does not exist.",
      "details": []
    }
  }
  ```
* HTTP 429 Too Many Requests
  ```json
  {
    "success": false,
    "data": null,
    "error": {
      "code": "RATE_LIMIT_EXCEEDED",
      "message": "You have submitted too many reports recently. Please try again later.",
      "details": []
    }
  }
  ```

---

## 4. Architectural Conformance & Consistency Ledger

### 4.1 Zero Download-Proxy Compliance
In accordance with PRD Section 6.3 and ARCHITECTURE Section 2:
* All endpoints delivering download targets (`get_app.php`, `check_updates.php`) return the resolved external URL (`apk_url`) pointing to GitHub Releases or Google Drive.
* No endpoint streams, redirects through a binary buffer, or serves raw `.apk` bytes.

### 4.2 Database Table Coverage
Every table defined in DATABASE.md connects directly to this API specification:
* `users`: Authenticated via Google token in `submit_review.php`.
* `apps`: Queried by `get_home.php`, `get_app.php`, `search.php`, and `check_updates.php`.
* `app_screenshots`: Queried by `get_app.php`.
* `categories`: Queried by `get_home.php` and `search.php`.
* `reviews`: Inserted by `submit_review.php`, read by `get_app.php`.
* `reports`: Inserted by `report_app.php`.
* `system_settings`: Read by `app_config.php`.
* `admins` & `app_submissions`: Excluded from the public `/api/` endpoints by architectural mandate. Administrated exclusively via the server-rendered desktop `/admin/` PHP portal.

---
End of API Specification. Document preserved in repository root as API.md.
