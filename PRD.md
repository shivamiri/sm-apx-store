# Product Requirements Document (PRD)
## SM Apx Store (Apx)

**Document Version:** 1.1.0  
**Target Release:** MVP (Phase 1)  
**Lead Developer / Owner:** Shiva Miri  
**Product Lead:** Senior Product Manager  
**Platform Tagline:** *"Discover. Download. Explore."*  
**Date:** November 2026  
**Status:** Approved for Implementation  

---

## 1. Executive Summary & Problem Statement

### 1.1 Executive Summary
**SM Apx Store (short name: Apx)** is an open, community-centric Android application discovery and distribution platform consisting of a native Android client application and a lightweight core PHP/MySQL web platform. Apx enables regular users to browse, search, and download verified APKs seamlessly without registration friction, while offering indie developers an accessible publishing portal gated by Google authentication and mandatory administrative review.

### 1.2 Problem Statement
Independent Android developers face steep barriers to entry when distributing apps on commercial app stores:
1. **Financial & Administrative Barriers:** Commercial stores require mandatory paid developer registration fees ($25 to $99/year), cumbersome tax verification, and strict organizational verification procedures.
2. **User Download Friction:** Competing distribution hubs frequently gate downloads behind compulsory user accounts, inject intrusive interstitial ads, or redirect users through ad-shorteners and external file lockers.
3. **Bandwidth Costs & Infrastructure Overhead for Hobbyists:** Self-hosting multi-gigabyte APK files requires expensive cloud object storage (AWS S3, Cloudflare R2), rendering independent app store projects financially unsustainable on standard or free hosting.

### 1.3 Solution Hypothesis
By leveraging zero-cost developer-hosted release channels (public Google Drive and GitHub Releases) and serving pure metadata from a lightweight Core PHP and MySQL backend on free shared hosting, Apx provides a completely free, clean, frictionless distribution channel for developers and a 1-tap install store for end users. The platform stores only external resource links and completely bypasses server-side file hosting and proxying.

---

## 2. Target Users & Personas

### Persona 1: "The Friction-Hating Explorer" (End User)
* **Name:** Aarav Sharma (Age: 21, College Student / Modder)
* **Context:** Frequently tests open-source utilities, indie games, and regional apps not hosted on mainstream stores.
* **Pain Points:** Hates creating accounts, dealing with CAPTCHAs, or being redirected through ad-heavy shortlinks just to test an APK.
* **Goal on Apx:** Open the app or mobile web page, search for a utility, inspect the real download size and screenshots, tap "Install", and get the native Android installation prompt immediately without signing in.

### Persona 2: "The Indie Android Creator" (Developer)
* **Name:** Priya Nair (Age: 25, Solo Flutter & Native Developer)
* **Context:** Builds open-source tools and hobby apps; hosts releases on GitHub Releases or personal Google Drive.
* **Pain Points:** Does not want to pay $25+ or navigate enterprise merchant account setups just to distribute side projects to real users.
* **Goal on Apx:** Sign in instantly via Google, paste an app release link, upload media URLs, track the submission status (Pending -> Live), and view ratings/feedback from users.

### Persona 3: "The Gatekeeper" (Store Administrator)
* **Name:** Shiva Miri (Platform Owner & Superadmin)
* **Context:** Manages platform integrity, reviews incoming packages, and prevents malware distribution.
* **Pain Points:** Low operational budget, strict terms on free hosting prohibiting file storage or proxy services, and host account suspension risks.
* **Goal on Apx:** Log into a protected, password-authenticated web admin console, review incoming apps with verification of APK origins and metadata, approve/reject with notes, ban bad actors, and publish platform updates.

---

## 3. Goals and Non-Goals

### 3.1 Primary Goals
* **Frictionless Consumption:** Users can discover, browse, view details, and download APKs on web and native app without logging in.
* **Zero Direct Binary Storage & Zero Proxying:** Store only external links (GitHub Releases / Google Drive) in the database. Never store APK files on the host, and never proxy, stream, or pipe APK bytes through PHP scripts.
* **Direct Client-to-Source Downloading:** All downloads on both the native app and the website trigger directly from GitHub Releases or Google Drive links to maintain compliance with shared hosting terms.
* **Strict Quality & Safety Gate:** 100% of community-submitted applications must receive human admin approval before appearing in public listings.
* **Uninterrupted Live Service During Updates:** When a developer submits a new version of an already Live app, the previous version remains fully Live and downloadable until the new submission is officially reviewed and approved.
* **Device-Wide Update Tracking:** Native app scans all installed packages on the user's device (not just apps installed via Apx) and matches them against the Apx live catalog to alert users of available updates.
* **Automated Package Profiling:** Server automatically discovers and displays accurate APK byte sizes upon submission whenever technically feasible, supported by manual fallback input.
* **Native-Level Experience:** Native app triggers direct `PackageInstaller` / package-archive intent prompts upon completed background downloads.

### 3.2 Non-Goals (Out of Scope for MVP & V2)
* **No Direct File Uploads or Local Binary Hosting:** Server will never accept raw `.apk` file uploads to local PHP shared hosting disks.
* **No Backend Download Proxying or Streaming:** PHP will never read, buffer, or pipe binary APK data.
* **No Automatic Token Resolution for Large Drive Files:** For Google Drive files over 100 MB that trigger virus-scan confirmation pages, the system will not attempt automated bypass token extraction.
* **No Paid / Monetized Apps:** No in-app purchases, paid downloads, payment gateways, or subscription integrations.
* **No Automated Antivirus Sandbox:** Moderation relies on manual admin verification and community reporting.
* **No Multi-Tenant Organization Accounts:** No teams, organizational roles, or multi-seat developer accounts.

---

## 4. Product Architecture & System Parts

### 4.1 Architecture Overview
Apx consists of three main operational tiers:
1. **Native Android App (User Panel Only):** Client application consuming the web API, performing device-wide package matching for updates, and downloading binaries directly from external sources.
2. **Core PHP Web Platform:**
   * **Public User Panel:** Mobile-first web catalog with direct-to-source download triggers.
   * **Developer Portal:** Google OAuth 2.0 authenticated portal for submitting and managing apps.
   * **Admin Panel (Desktop Web Only):** Dedicated password-authenticated console for catalog curation, approvals, bans, and force updates.
   * **JSON API (`/api/`):** Lightweight endpoints serving catalog, update matching, metadata, and review data to the Android app.
3. **External Distribution CDNs:** GitHub Releases and Google Drive serving binaries directly to end-user devices.

---

## 5. Feature Breakdown: MVP vs. V2 vs. Later

| Feature Domain | Phase 1: MVP (Must-Have) | Phase 2: V2 (Should-Have) | Phase 3: Later (Nice-to-Have) |
| :--- | :--- | :--- | :--- |
| **User Access** | Anonymous browsing & downloading. Google Sign-In for ratings/reviews. | User wishlist/favorites tab; download history. | Granular notification preferences; custom avatars. |
| **Home & Discovery** | Search bar, category filters, curated shelves (Featured, Hot Today, New Arrivals, Top Rated, Trending, All Apps). | Algorithmic "You Might Also Like" based on installed tags. | Dynamic personalized discovery feed; editorial badges. |
| **Search Engine** | Exact match search + 3-tier fallback (similar titles, category matches, trending apps). | Tag-based search and developer name search. | Typo-tolerance / Fuzzy search. |
| **App Details** | Metadata, icon, screenshots (1-8), real size, about text, install button, optional YouTube embed, Report button. | Share sheet links (deep links); developer profile page listing all developer's apps. | Changelog history tree; user-voted pros/cons. |
| **Reviews & Ratings** | 1-5 Star rating + text review (Google-authenticated users only, 1 review per app per user). | Upvote/downvote helpful reviews; developer public replies. | Review moderation sentiment analysis; automated spam flagger. |
| **Download & Install** | Direct-to-source downloads (no proxy). Native app background download + auto-trigger install popup. Web: direct browser download trigger. | Download pause/resume handling; hash checksum verification (SHA-256). | Delta/patch updates; split-APK (APKS/XAPK) installer engine. |
| **App Updates** | Updates screen checking all device-installed packages against live database version codes. Force update dialog. | Silent background update check notifications. | Automated scheduled background updates over Wi-Fi. |
| **Developer Upload** | Google Auth login, submission form (Package Name, Version, URLs for icon, feature, 1-8 screenshots, Drive/GitHub link). Auto size detection with manual fallback. | Edit live apps (triggers re-review while previous version remains Live); submission drafts. | In-app analytics (impressions, download clicks, country breakdown). |
| **Admin Panel** | Metric counters (Total, Approved, Pending, Rejected, Banned). Review/Approve/Reject with notes. Ban user/app. Force update manager. Direct Admin uploads. | Category CRUD management; Featured banner position manager; Global announcements banner. | Audit logs; session/device invalidation; CSV data exports; multi-role RBAC (Moderator vs Superadmin). |

---

## 6. Detailed Functional Specifications

### 6.1 Catalog, Sections & Fallback Search
* **Curated Shelves on Home:**
  1. *Featured:* Handpicked by Admin flag.
  2. *Hot Today:* Highest download count within the trailing 24 hours.
  3. *New Arrivals:* Ordered by approved date descending (last 14 days).
  4. *Top Rated:* Ordered by average rating descending (minimum 3 reviews).
  5. *Trending:* Ranked by combined 48-hour download and review velocity.
  6. *All Apps:* Infinite scroll or paginated grid.
* **3-Tier Search Fallback Algorithm:**
  * When a user searches for a query:
    * *Tier 1 (Exact/Prefix Match):* Look up app title or package name.
    * *Tier 2 (Fallback Trigger):* If Tier 1 produces zero matches:
      * 1. Display banner informing the user that no direct matches were found and displaying recommended alternatives.
      * 2. Query similar apps matching category or search terms.
      * 3. Query Top 5 apps from the matching category.
      * 4. Query Top 5 current Trending apps.

### 6.2 App Size Auto-Detection & Link Guidelines
* **GitHub Release Direct APK:**
  * Developer inputs direct release asset URL.
  * Server sends a lightweight HTTP header request to retrieve the reported content size without downloading the file.
  * Server converts the byte value into formatted megabytes (MB) and populates the record.
* **Google Drive APK:**
  * Developer inputs a shareable Google Drive link.
  * Server extracts the file identifier and attempts to read the content length header from the direct export link.
  * **Handling Files Over 100 MB:** Google Drive displays a virus-scan confirmation warning page for files larger than 100 MB, preventing automated header reading. The platform will **not** attempt automated bypass token extraction.
  * **Platform Recommendation & Fallback:**
    * The upload form explicitly instructs and strongly encourages developers with APKs over 100 MB to use GitHub Releases for fast, reliable delivery.
    * If size auto-detection fails or cannot be determined (e.g., Drive files over 100 MB or throttled links), the submission form unlocks a mandatory manual size input field where the developer inputs the exact APK size in MB.
    * The user-facing app page always displays the real, validated size.

### 6.3 Direct In-Platform Download & Installation Architecture
* **Hosting Terms & Direct Download Rule:** To comply strictly with free shared hosting policies (such as InfinityFree) prohibiting file hosting and proxy services, the server never stores or proxies APK binaries. Both native app and website download actions connect the client device directly to the original GitHub Release or Google Drive source.
* **Native Android App Execution:**
  1. User taps "Install".
  2. Native app initiates download directly from the external GitHub/Drive URL via Android's native download manager or a dedicated background task, saving to scoped internal application storage.
  3. Real-time visual progress bar updates inside the app's install button.
  4. Upon download completion, the app uses Android's secure file provider to generate a content URI.
  5. Native app immediately dispatches the package installer action intent.
  6. The system package installer prompt appears on the user's screen without ever opening an external browser or file-sharing landing page.
* **Mobile Web Execution:**
  1. User taps "Install / Download APK".
  2. The web page directly triggers a client-side browser file download from the external release URL while the user remains on the Apx web page.
  3. The website displays a helpful bottom sheet guiding the user to tap the completed download notification to complete installation.

### 6.4 Versioning, Updates & Force Update Mechanism
* **Device-Wide Installed App Check (Native Client):**
  * The native app checks **all installed applications** on the user's device (querying the device package manager), not just apps originally installed through SM Apx Store.
  * App transmits the list of installed package names and version codes to the updates API endpoint.
  * The API checks for matching package names in the database with active Live status where the store version code is greater than the device's installed version code.
  * The app displays the list of apps that have newer versions available with one-tap update buttons.
* **Independent Version Updating for Developers:**
  * When a developer submits an updated version code for an app that is already "Live", the existing live version **remains active, visible, and downloadable** in the store.
  * The new version enters the admin moderation queue under "Pending Review".
  * Once the admin reviews and approves the update, the live listing is updated to the new version code, binary URL, and changelog. If rejected, the existing version continues to remain live without interruption.
* **Force Update Control (Apx Client Self-Update):**
  * Handled via database configuration keys for minimum required app version, latest app version, and direct update URL.
  * Native app checks configuration on launch.
  * If the client version is lower than the minimum required version, a persistent, non-dismissible modal appears requiring the user to update.
  * Once the updated APK is installed and the app restarts, the modal dismisses automatically.

### 6.5 Developer Submission Workflow & Validation
* **Form Fields:** App Name, Unique Package Name, Category, Icon URL, Feature Graphic URL, 1 to 8 Screenshot URLs, Optional YouTube Video URL, Description, Version Code, Direct APK link (GitHub Release or Google Drive).
* **Validation Rules:**
  * Package name must follow valid Android package identifier structure and be unique.
  * For updates to existing apps, the submitted version code must be strictly greater than the currently published version code.
  * Upload form provides dynamic messaging advising developers to choose GitHub Releases for packages larger than 100 MB.
* **Review States:**
  * `Pending`: Submission waiting in moderation queue.
  * `In Review`: Admin actively inspecting app details.
  * `Live`: Approved and visible to public.
  * `Rejected`: Admin declined submission with mandatory explanation note.
  * `Banned`: Blocked due to violations.

### 6.6 Admin Control Panel (Website Only)
* **Access & Security:** Dedicated administration login route authenticated via separate username and bcrypt-hashed password, with session expiration and brute-force throttling.
* **Capabilities:**
  * Metrics dashboard showing total, approved, pending, rejected, and banned apps.
  * Approval queue with quick metadata preview and direct test download link.
  * One-click approval or rejection with mandatory feedback note.
  * User and app management (search, ban, unban).
  * Direct Admin publishing: Admin-uploaded apps publish immediately to "Live" status and display an official Admin badge.
  * Force update controller to define mandatory client version numbers and update links.

---

## 7. User Stories

### 7.1 End Users (Unauthenticated & Authenticated)
* **US-01:** *As an anonymous user*, I want to browse categorized Android apps without registering an account, so that I can explore tools quickly and protect my personal data.
* **US-02:** *As an anonymous user*, I want to download an APK directly without being redirected to third-party file-sharing web pages, so that the download process is clean and fast.
* **US-03:** *As an anonymous user*, I want to see the real file size of an APK before downloading, so that I can manage my device storage and data usage.
* **US-04:** *As an anonymous user*, I want to see suggested and trending apps when my search has no exact match, so that I can continue discovering relevant content.
* **US-05:** *As an authenticated user*, I want to sign in with Google to post ratings and reviews, so that I can share feedback and help other users identify quality software.
* **US-06:** *As a native app user*, I want the system installation screen to trigger automatically as soon as a download finishes, so that I do not have to search for the file manually.
* **US-07:** *As a native app user*, I want the Updates tab to scan all apps installed on my phone and show available updates from the Apx store, so that I can keep all my apps up to date in one place.

### 7.2 Developers
* **US-08:** *As an indie developer*, I want to sign in with Google and submit my app using an external GitHub or Google Drive link, so that I can distribute my apps without hosting fees or account charges.
* **US-09:** *As an indie developer*, I want clear guidance to use GitHub Releases for files over 100 MB, so that my users have reliable downloads without warning pages.
* **US-10:** *As an indie developer*, I want my existing Live app to stay available to users while my new version is being reviewed, so that my users experience zero downtime.
* **US-11:** *As an indie developer*, I want to see detailed admin rejection notes if my submission is rejected, so that I understand exactly what to fix.

### 7.3 Store Administrator
* **US-12:** *As the store administrator*, I want to review every submitted app before it goes live, so that I can keep malware and broken links off the platform.
* **US-13:** *As the store administrator*, I want to publish my own apps directly with an Admin badge, so that I can deliver official tools quickly without redundant review steps.
* **US-14:** *As the store administrator*, I want to trigger a force-update popup for older versions of the Apx native app, so that users stay on secure and compatible versions.

---

## 8. Key User Flows (Step-by-Step)

### Flow 1: End User Discovery & Installation (Native App)
```
[ Open Apx App ]
       |
       v
[ Browse / Search Catalog ]
       |
       +---> [ Search Query Hit? ]
                  |-- YES --> [ Render Results List ]
                  |-- NO  --> [ Render Fallback: Similar + Category + Trending ]
       |
       v
[ Select App Details Page ]
       |-- Display Real Size, Screenshots, YouTube Embed, Ratings
       v
[ Tap "Install" Button ]
       |
       v
[ Background Download Directly from GitHub / Drive Link ]
       |-- Show Progress Bar (0% -> 100%) inside button
       v
[ Download Complete ]
       |
       v
[ Secure FileProvider Resolves Content URI ]
       |
       v
[ System Dispatches Package Installer Intent ]
       |
       v
[ Android Native Package Installer Prompts User to Install ]
```

### Flow 2: Developer App Submission & Update Flow
```
[ Developer Signs In via Google on Web ]
       |
       v
[ Access "Upload App" Form ]
       |
       v
[ Fill Details: Package Name, Icon, Screenshots, YouTube URL ]
       |
       v
[ Enter APK Link: GitHub Release or Google Drive ]
       |
       +---> [ Size Auto-Detection ]
                  |-- Success --> [ Real size filled automatically ]
                  |-- Fails / Drive >100MB --> [ Developer inputs size in manual field ]
       |
       v
[ Developer Submits App ]
       |
       +---> [ Is this an update to an existing Live app? ]
                  |-- YES --> [ Old version remains Live in store; New version queued as 'Pending' ]
                  |-- NO  --> [ New app queued as 'Pending' ]
```

### Flow 3: Device-Wide App Updates Flow (Native App)
```
[ User Opens "Updates" Tab in Native App ]
       |
       v
[ App Queries Device PackageManager for All Installed Apps ]
       |
       v
[ App Sends List of Package Names & Version Codes to /api/check-updates ]
       |
       v
[ Server Compares Against Live Database Apps ]
       |
       v
[ Server Returns Apps Having Live Version Code > Installed Version Code ]
       |
       v
[ Native App Displays List of Updatable Apps with "Update" Buttons ]
```

### Flow 4: Admin Moderation Flow
```
[ Admin Logs In at /admin/login.php ]
       |
       v
[ Open Moderation Queue ]
       |
       v
[ Test Link, Verify Package Metadata & Assets ]
       |
       +---> [ Decision ]
                 |
                 +-- APPROVE --> [ Status updated to 'Live' ]
                 |               [ If update: replaces previous live version ]
                 |
                 +-- REJECT  --> [ Admin enters mandatory rejection note ]
                                 [ Status updated to 'Rejected' ]
                                 [ If update: previous version remains Live ]
```

---

## 9. Technical Architecture & Constraints (Free Shared Hosting & Core PHP)

### 9.1 Shared Hosting Operating Constraints
Free shared web hosts (such as InfinityFree) enforce strict operational boundaries:
* **Terms of Service Compliance:** Explicitly forbid file storage hubs and proxy/streaming scripts. Violations result in immediate account termination.
* **Server Timeouts & Memory Limits:** Execution limits of 20 to 30 seconds and low memory caps prevent server-side media handling.
* **Zero-Proxy Architecture:** The server operates solely as an application metadata catalog. All binary downloads must be executed directly by the client browser or native app using the external GitHub or Drive links.

### 9.2 API & Integration Architecture
* The backend provides lightweight JSON endpoints returning structured metadata, image URLs, and external download links.
* The native app consumes endpoints via standard HTTP requests and manages its own downloads locally on the client device.
* Google Sign-In is verified on the backend via standard Google OAuth 2.0 token verification without requiring custom session databases for unauthenticated users.

---

## 10. Success Metrics (KPIs)

* **Catalog Health & Moderation Velocity:**
  * Median Approval Turnaround Time: Under 24 hours from submission to Live or Rejected.
  * Rejection Resolution Rate: Greater than 50% of rejected apps resubmitted successfully.
* **User Engagement & Friction Metrics:**
  * Anonymous Download Conversion Rate: At least 65% of users viewing an App Page trigger a download.
  * Search Success Rate: At least 80% of search queries lead to an app view or download.
* **System Stability & Reliability:**
  * API Response Latency: Median response time under 400 ms on free shared hosting.
  * Auto Size Detection Success Rate: Greater than 95% for GitHub Releases.
  * Fatal Crash Rate: Under 0.5% of sessions on the native Android client.

---

## 11. Risks, Assumptions, and Open Questions

### 11.1 Key Assumptions
1. **Developer Release Maintenance:** Developers will maintain public accessibility of their GitHub Release assets and Google Drive links.
2. **Device Permission Grant:** Users will grant installation permission for unknown sources when prompted by Android 8.0+.
3. **External Media Delivery:** App icons and screenshots will be reliably hosted on fast public CDNs or image hosts.

### 11.2 Risk Matrix

| Risk Event | Severity | Probability | Mitigation Strategy |
| :--- | :---: | :---: | :--- |
| **Host Bot Protection Blocking Native App API Requests** | High | High | Free hosts (like InfinityFree) often use JavaScript challenges (such as `aes.js`) to block automated traffic, which will reject native Android API calls. **Mitigation:** Must be tested immediately in development. If blocked, consider a free tier host without client-side JS challenges (such as Render, Koyeb, or standard free PHP tiers) for the `/api/` endpoints. |
| **Shared Hosting Account Suspension** | High | Low | Fully mitigated by strict zero-storage, zero-proxy design. The site stores only external URLs and never handles APK bytes through PHP. |
| **Google Drive Download Limits & Interstitials** | Medium | High | For files over 100 MB, automated size detection is bypassed in favor of developer manual size input. Developers are strongly encouraged via UI guidelines to use GitHub Releases for large files. |
| **Malicious APK Uploads** | Critical | Medium | Every app requires manual admin verification before publishing. Community reporting feature provides rapid flagging of bad packages. |

### 11.3 Technical Feasibility Summary
* **Direct Downloads on Free Hosting:** Fully feasible on both native app and mobile web because neither server storage nor PHP streaming is utilized. The client downloads directly from external CDNs.
* **Bot-Protection Early Testing:** Critical item. Early testing must confirm whether InfinityFree's security filters permit mobile HTTP requests. If not, API routing must be accommodated on an uninhibited free PHP tier.

---
*End of PRD. Document preserved in repository root as `PRD.md`.*
