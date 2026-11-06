# SM Apx Store (Apx) Design System: DESIGN.md

**Specification Version:** 1.0.0  
**Status:** Canonical & Enforced Source of Truth for Android (Kotlin) & Web (Core PHP / CSS / Vanilla JS)  
**Lead Developer & Creator:** Shiva Miri  
**Product Name:** SM Apx Store (Short: **Apx**)  
**Brand Tagline:** *"Discover. Download. Explore."*  
**Reference Document:** `PRD.md` (v1.1.0)  

---

## 1. Brand Identity & Design Principles

### 1.1 Brand Feel
SM Apx Store evokes an authentic indie cyberpunk hub meets high-precision mobile engineering:
* **Tactile & Responsive:** Fast, utilitarian, and direct. Actions trigger tactile micro-feedback without unnecessary multi-second loading skeletons.
* **Deep Navy & Electric Cyan Foundation:** Grounded in rich, midnight abyss blues (`#070B14`, `#0D1527`), cut through with high-energy electrical cyan and cobalt accents (`#00E5FF` to `#0070F3`).
* **Deliberate & Architectural:** Strict geometric proportions. Crisp 6px–12px corner radii that frame software and media cleanly, avoiding oversized pill cards or generic template looks.
* **No AI Cliches:** No washed-out purple-on-purple mesh gradients, no floating card soups on drab grey backgrounds, no bloated empty margins, and no generic stock iconography. Every element serves immediate discovery, verification, or installation.

### 1.2 Core Design Principles
1. **Zero-Latency Content Priority:** App icons, byte weights, changelogs, and direct install triggers are front-loaded. Chrome and structural chrome recede into the dark canvas.
2. **Deterministic Dual-Mode Fidelity:** Dark mode is the primary native canvas. Light mode is an intentional, high-contrast, clean technical paper variant (`#F4F7FC`), not an inverted afterthought.
3. **Cross-Platform Uniformity:** The native Android client (Kotlin + XML/Jetpack Compose) and the Core PHP web client share identical structural dimensions, color tokens, and state transitions so a user switching between browser and native app experiences zero cognitive friction.

---

## 2. Color Palette & Theming Tokens

All color tokens are strictly defined below. No developer or designer is permitted to introduce arbitrary HEX values outside this palette.

### 2.1 Dark Mode Palette (Default Canvas)
| Token Name | HEX Code | RGB | Purpose / Usage |
| :--- | :--- | :--- | :--- |
| `bg-base` | `#070B14` | `7, 11, 20` | Root app window, full-bleed canvas, status bar base |
| `bg-surface` | `#0D1527` | `13, 21, 39` | Standard cards, top navigation bar, bottom tab bar |
| `bg-surface-elevated` | `#131F38` | `19, 31, 56` | Modals, bottom sheets, dropdown menus, floating action buttons |
| `bg-surface-hover` | `#1A2A4C` | `26, 42, 76` | Hover state for elevated items, list selection backgrounds |
| `border-subtle` | `#1E2D4A` | `30, 45, 74` | Default card borders, list dividers, structural separators |
| `border-focused` | `#00E5FF` | `0, 229, 255` | Focused inputs, active app cards, selected filters |
| `accent-primary` | `#00E5FF` | `0, 229, 255` | High-energy electric cyan for primary highlights, ratings, pills |
| `accent-secondary` | `#0070F3` | `0, 112, 243` | Precision cobalt blue for primary CTA backgrounds |
| `accent-gradient-start`| `#00E5FF` | `0, 229, 255` | Linear gradient start for primary install action buttons & logos |
| `accent-gradient-end` | `#0070F3` | `0, 112, 243` | Linear gradient end (135-degree angle) |
| `text-primary` | `#F8FAFC` | `248, 250, 252`| App titles, modal headings, primary action text |
| `text-secondary` | `#94A3B8` | `148, 163, 184`| Package names, categories, secondary metadata, inactive tabs |
| `text-tertiary` | `#64748B` | `100, 116, 139`| Disabled states, timestamps, unselected stars, placeholders |
| `status-success` | `#10B981` | `16, 185, 129` | "Live" badge, update available, verified developer tag |
| `status-warning` | `#F59E0B` | `245, 158, 11` | "Pending Review", warning prompts, manual size alert |
| `status-error` | `#EF4444` | `239, 68, 68` | "Rejected", "Banned", form validation errors, report badge |
| `status-admin` | `#8B5CF6` | `139, 92, 246` | Dedicated "[Admin Verified]" badge & root admin panel chrome |

### 2.2 Light Mode Palette
| Token Name | HEX Code | RGB | Purpose / Usage |
| :--- | :--- | :--- | :--- |
| `bg-base` | `#F4F7FC` | `244, 247, 252`| Root page background, app frame |
| `bg-surface` | `#FFFFFF` | `255, 255, 255`| Clean white cards, top headers, bottom bars |
| `bg-surface-elevated` | `#E8EEF8` | `232, 238, 248`| Dialogs, sheet overlays, dropdown menus |
| `bg-surface-hover` | `#DCE5F5` | `220, 229, 245`| Table row hover, list item pressed state |
| `border-subtle` | `#CBD5E1` | `203, 213, 225`| Thin borders, structural lines, dividers |
| `border-focused` | `#0070F3` | `0, 112, 243` | Focus ring outline for inputs and cards |
| `accent-primary` | `#0099B8` | `0, 153, 184` | Deep readable cyan-teal for light backgrounds |
| `accent-secondary` | `#0055B8` | `0, 85, 184`  | Deep cobalt blue for primary CTA backgrounds |
| `accent-gradient-start`| `#00B8D9` | `0, 184, 217` | High-contrast gradient start |
| `accent-gradient-end` | `#0052CC` | `0, 82, 204`  | High-contrast gradient end |
| `text-primary` | `#0F172A` | `15, 23, 42`  | Core headings, high-contrast typography |
| `text-secondary` | `#475569` | `71, 85, 105` | Metadata, subtitle text, labels |
| `text-tertiary` | `#94A3B8` | `148, 163, 184`| Inactive icons, subtle text, helper descriptions |
| `status-success` | `#059669` | `5, 150, 105` | "Live" badge, verified checkmarks |
| `status-warning` | `#D97706` | `217, 119, 6` | "Pending" badge, warnings |
| `status-error` | `#DC2626` | `220, 38, 38` | "Rejected", "Banned", critical flags |
| `status-admin` | `#7C3AED` | `124, 58, 237` | Admin tags and badges |

---

## 3. Typography Hierarchy

### 3.1 Font Family Definitions
* **Primary Sans:** Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif.
* **Monospace / Numerical:** JetBrains Mono, "Fira Code", monospace (used for Package Names, Version Codes, File Byte Sizes, SHA-256 Hashes, and API Endpoints).

### 3.2 Typography Scale
| Role | Size (sp/px) | Weight | Line Height | Letter Spacing | Target Usage |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Display 1** | 28px (28sp) | Bold (700) | 34px | -0.02em | Hero headers, Featured banner titles |
| **Heading 1** | 22px (22sp) | Bold (700) | 28px | -0.01em | Screen headers, App Page app name |
| **Heading 2** | 18px (18sp) | SemiBold (600) | 24px | 0.00em | Shelf titles (Hot Today, Trending, New) |
| **Heading 3** | 15px (15sp) | SemiBold (600) | 20px | 0.00em | Card titles, Modal headers, Admin section headers |
| **Body Large** | 15px (15sp) | Regular (400) | 22px | 0.00em | App description text, Review comments |
| **Body Default**| 13px (13sp) | Regular (400) | 18px | 0.00em | General UI labels, dropdown items, form inputs |
| **Body Medium** | 13px (13sp) | Medium (500) | 18px | 0.00em | Active tab items, table headers, buttons |
| **Caption / Mono**| 11px (11sp)| SemiBold (600) | 14px | +0.02em | Package names, version badges, size tags, admin notes |
| **Micro** | 10px (10sp) | Bold (700) | 12px | +0.04em | Status pills ("LIVE", "PENDING", "ADMIN") |

---

## 4. Spacing Scale, Radius, Shadows & Grid

### 4.1 Spacing Scale (8-Point Harmonic System)
```
space-1  = 4px   (Fine padding, badge borders, icon gaps)
space-2  = 8px   (Button inline gap, tight list padding, chip margins)
space-3  = 12px  (Card inner padding on compact mobile, input padding)
space-4  = 16px  (Standard screen edge margin on mobile, standard card padding)
space-5  = 20px  (Section gaps, modal padding)
space-6  = 24px  (Shelf-to-shelf spacing, desktop card padding)
space-8  = 32px  (Screen top offsets, desktop grid gaps)
space-12 = 48px  (Hero section margins, desktop admin layout padding)
```

### 4.2 Border Radius System
* **Radius-Sharp (`radius-xs`):** `4px` — Monospace badges, pill tags, toast notifications.
* **Radius-Compact (`radius-sm`):** `8px` — Standard action buttons, text inputs, search bar, dropdowns, table cells.
* **Radius-Card (`radius-md`):** `12px` — App cards, screenshot preview thumbnails, dialog modals, bottom sheets.
* **Radius-AppIcon (`radius-icon`):** `22%` squircle (`clip-path` or mask) matching Google Play / Android Adaptive Icon standards.
* **Radius-Full (`radius-full`):** `9999px` — Category filter chips, circular avatar photos, floating quick-action toggles.

### 4.3 Elevation & Shadow Tokens (Dark & Light)
* **Elevation 1 (Cards, Bars):**
  * *Dark Mode:* `0 2px 8px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.05)`
  * *Light Mode:* `0 2px 8px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04)`
* **Elevation 2 (Modals, Popups, Dropdowns):**
  * *Dark Mode:* `0 12px 32px rgba(0, 0, 0, 0.70), 0 0 0 1px #1E2D4A`
  * *Light Mode:* `0 12px 32px rgba(15, 23, 42, 0.12), 0 0 0 1px #CBD5E1`
* **Elevation 3 (Cyan Glow Accent):**
  * `0 0 16px rgba(0, 229, 255, 0.35)` (Reserved exclusively for active "Install / Update" action buttons and Featured badge borders).

### 4.4 Breakpoints & Grid Matrix
* **Mobile (Default Canvas):** `< 640px` (Fluid 1-column layout, fixed 16px edge margins, bottom nav bar enabled).
* **Tablet / Small Desktop:** `640px – 1023px` (Fluid 2-to-3 column app cards, 24px edge margins).
* **Desktop / Admin Panel:** `>= 1024px` (Max container width 1280px, 4-column app shelf, persistent left sidebar for Admin console).

---

## 5. Comprehensive Component Specifications

### 5.1 Buttons

#### Primary CTA (Install / Download / Submit)
* **Structure:** Height `44px`, horizontal padding `20px`, font size `13px` Medium (500), border-radius `8px`.
* **Visual Style:** Gradient fill: `linear-gradient(135deg, #00E5FF 0%, #0070F3 100%)`. Text color `#070B14` (Bold) in Dark Mode, `#FFFFFF` in Light Mode.
* **States:**
  * *Default:* Solid gradient, crisp 1px border `rgba(255,255,255,0.2)`.
  * *Hover (Web):* Glow elevation `0 0 16px rgba(0, 229, 255, 0.45)`, scale `1.01`.
  * *Pressed / Active:* Scale `0.98`, opacity `0.92`.
  * *Disabled:* Fill `#1E2D4A`, text `#64748B`, border `none`, cursor `not-allowed`.
  * *Loading:* Text replaced by centered 18px dual-color spinner (Cyan/Cobalt), pointer events disabled.
  * *In-Progress (Native Download):* Filled with segmented progress indicator: left side fills with `#00E5FF` at `N%`, displaying text `"Downloading 48%..."`.

#### Secondary Button (Cancel / Manual Fallback / Filter)
* **Structure:** Height `44px`, padding `16px`, radius `8px`.
* **Visual Style:** Background `transparent`, border `1px solid #1E2D4A`, text `#94A3B8`.
* **States:**
  * *Hover:* Background `#131F38`, border `#00E5FF`, text `#F8FAFC`.
  * *Focused:* Outline ring `2px solid #00E5FF`, offset `2px`.

#### Danger Button (Reject / Ban / Report)
* **Visual Style:** Background `rgba(239, 68, 68, 0.12)`, border `1px solid #EF4444`, text `#EF4444`.
* **Hover:** Background `#EF4444`, text `#FFFFFF`.

### 5.2 Form Inputs & Textareas
* **Structure:** Height `44px` (Textarea: min `100px`), radius `8px`, horizontal padding `14px`, text `13px`.
* **Base Fill:** Dark: `#0D1527` with border `1px solid #1E2D4A`. Light: `#FFFFFF` with border `1px solid #CBD5E1`.
* **Placeholder:** Text `#64748B`.
* **States:**
  * *Default:* Inactive slate border.
  * *Focus:* Border `#00E5FF`, subtle box-shadow `0 0 0 3px rgba(0, 229, 255, 0.20)`.
  * *Error:* Border `#EF4444`, box-shadow `0 0 0 3px rgba(239, 68, 68, 0.20)`. Error helper message rendered below in `11px` Caption font (`#EF4444`).
  * *Disabled:* Fill `#070B14`, text `#64748B`, border `#131F38`.

### 5.3 App Cards (Shelves & Catalog)
* **Compact Horizontal Card (Shelf Row / Updates List):**
  * Height `76px`, padding `10px 12px`, radius `10px`, background `#0D1527`, border `1px solid #1E2D4A`.
  * Left: `56px` square app icon with `22%` squircle mask.
  * Middle Column: App name (`14px` SemiBold `#F8FAFC`, truncated to 1 line), Package name (`11px` Mono `#94A3B8`), Category badge + Byte Size (`11px` Mono `#00E5FF`).
  * Right: Compact Action Button (Height `32px`, min-width `72px`, text "GET" or "UPDATE").
* **Grid Card (All Apps / Category Grid):**
  * Width: fluid (`100%`), aspect-ratio container with icon centered, title, rating stars (`#F59E0B`), and real size pill (`#131F38` fill with `#00E5FF` text).

### 5.4 Modals, Bottom Sheets & Dialogs
* **Backdrop Overlay:** `#070B14` at `80%` opacity (`rgba(7, 11, 20, 0.80)`) with `backdrop-filter: blur(8px)`.
* **Native App Bottom Sheet:** Slides up from screen bottom, border-radius `16px 16px 0 0`, background `#0D1527`, top border `1px solid #1E2D4A`. Contains centered drag handle (`36px x 4px`, `#64748B`, rounded).
* **Desktop / Web Modal:** Centered, max-width `540px`, border-radius `12px`, background `#0D1527`, border `1px solid #1E2D4A`, box-shadow `Elevation 2`.
* **Force Update Non-Dismissible Dialog:**
  * Same as desktop/mobile modal, but backdrop dismiss, Android hardware back button, and escape keys are strictly disabled.
  * Contains warning icon (`#F59E0B`), Title "Update Required", mandatory changelog list, and a single full-width primary CTA button: "Update Now".

### 5.5 Navigation Bars
* **Native App Top Bar:**
  * Height `56px`, background `#070B14`, border-bottom `1px solid #1E2D4A`.
  * Left: App Brand ("Apx" in Electric Cyan font with custom icon).
  * Center: Integrated Search Input or screen title.
  * Right: Light/Dark theme toggle button (`36px x 36px`) + User Avatar / Google Login button.
* **Native App Bottom Navigation Bar:**
  * Height `60px`, background `#0D1527`, top border `1px solid #1E2D4A`.
  * 4 Tab Destinations:
    1. *Home* (Grid / Storefront icon)
    2. *Categories* (Layer / Shapes icon)
    3. *Updates* (Arrow Clockwise with notification dot if updates available)
    4. *Profile / Settings* (User Profile icon)
  * Active Tab State: Icon and label colored `#00E5FF` with a subtle top accent bar (`2px solid #00E5FF`).
  * Inactive Tab State: Icon and label colored `#64748B`.
* **Web Desktop Navbar:**
  * Height `64px`, sticky top (`z-index: 100`), background `rgba(13, 21, 39, 0.95)` with blur.
  * Left: Logo + "SM Apx Store", Center: Expanded 400px search bar, Right: "Upload App", Theme Switcher, Google Auth button.

### 5.6 Data Tables (Admin Console Only)
* **Container:** Background `#0D1527`, border `1px solid #1E2D4A`, border-radius `8px`, overflow-x auto.
* **Header Row:** Background `#070B14`, text `12px` Medium `#94A3B8`, uppercase, letter-spacing `+0.05em`, padding `12px 16px`.
* **Data Row:** Height `52px`, padding `12px 16px`, border-bottom `1px solid #1E2D4A`, font `13px` Regular.
* **Row Hover:** Background `#131F38`.
* **Action Cells:** Inline flex buttons for "Approve", "Reject", "Ban", and "Inspect".

### 5.7 Toast Notifications
* **Position:**
  * Mobile / Native App: Anchored at bottom, `80px` from base (clearing the bottom navigation bar), centered.
  * Desktop Web: Anchored at top-right, `24px` from edges.
* **Structure:** Height `44px`, padding `12px 16px`, radius `6px`, background `#131F38`, border `1px solid #1E2D4A`, shadow `Elevation 2`.
* **Variants:**
  * *Success:* Left accent border `3px solid #10B981`, icon checkmark.
  * *Error:* Left accent border `3px solid #EF4444`, icon alert.
  * *Info:* Left accent border `3px solid #00E5FF`, icon info.

---

## 6. Page & Screen Layout Specifications

### 6.1 Native Android App Screens

#### Screen N-01: Home Screen
* **Status Bar:** Dark navy background (`#070B14`), light icons.
* **App Header:** Short logo (`Apx`) on left, search bar icon on right, theme toggle icon.
* **Category Filter Bar:** Horizontal scrolling chip list (`All`, `Tools`, `Games`, `Social`, `Media`, `Productivity`). Height `32px`. Active chip has `#00E5FF` background with `#070B14` bold text.
* **Vertical Feed:**
  * *Featured Carousel:* Top hero banner showing admin-selected app with feature graphic, gradient overlay, app title, real size pill, and "GET" CTA.
  * *Hot Today Shelf:* Horizontal scrolling cards (width `260px`).
  * *New Arrivals Shelf:* Horizontal scrolling cards.
  * *Top Rated Shelf:* Vertical list of top 3 apps with review star scores.
  * *Trending Shelf:* Ranked numbers `1` through `5` with green velocity arrows.
  * *All Apps Section:* Infinite 1-column list with app icon, name, real size, and download button.

#### Screen N-02: Search & Fallback Screen
* **Top Header:** Full-width search input with auto-focus, clear button ("X"), and back arrow.
* **Active Typing State:** Instant debounced (250ms) search against cached index.
* **Zero Results State (PRD Fallback Engine):**
  * Notification Box: Warning banner with icon: *"No exact matches found for '[query]'. Check out these recommendations:"*
  * Shelf 1: "Similar Apps" (apps matching keywords/category).
  * Shelf 2: "Top Apps in [Category]".
  * Shelf 3: "Current Trending Apps".

#### Screen N-03: App Details Screen
* **Top Bar:** Back button, App Name (visible only after scrolling past header), Share button, Report button (flag icon).
* **Hero Block:**
  * Left: Large `72px` app icon (`22%` squircle).
  * Right: App Name (`20px` Bold), Developer Name (`13px` Medium with verified checkmark), Category, and Version Code pill.
* **Key Spec Strip (Horizontal Metric Grid):**
  * 4 Columns:
    1. Rating: `★ 4.8` (Gold `#F59E0B`)
    2. Real File Size: `18.4 MB` (Cyan `#00E5FF`, JetBrains Mono)
    3. Age / Rating: `Everyone`
    4. Downloads: `1.2K+`
* **Primary Action Area:**
  * Full-width "Install" CTA button (`48px` tall).
  * Live Download State: Changes to progress bar filling from left to right showing real-time percentage and byte counter (`12.4 MB / 18.4 MB`).
  * Completion State: Prompts system package installer immediately via Android FileProvider.
* **Screenshots Strip:** Horizontal gallery of screenshots (1 to 8 images), rounded corners `8px`, tap to open full-screen lightbox.
* **Optional YouTube Video:** Embedded 16:9 player container if YouTube URL is provided in metadata.
* **About this App:** Expandable markdown description box with changelog notes.
* **Ratings & Reviews:**
  * Overall score breakdown bar chart.
  * User review cards with author photo, name, star score, and timestamp.
  * "Write a Review" button (opens Google Sign-In prompt if unauthenticated).

#### Screen N-04: Updates Screen
* **Header:** Title "Updates", subtitle showing total updates found (e.g., "3 updates available").
* **Mechanism:** Checks all apps installed on the device against Apx live database.
* **Action Header:** "Update All" primary button on top right.
* **List Item:** Each item shows app icon, app name, current device version code vs. new store version code (e.g., `v1.2.0 (12) -> v1.3.0 (14)`), real update size, and "UPDATE" CTA button.

#### Screen N-05: Profile & Settings Screen
* **Unauthenticated State:** Google Sign-In banner: *"Sign in with Google to rate apps and publish your own projects."* with full-width Google CTA.
* **Authenticated State:** User avatar, name, Gmail address, "My Uploaded Apps" navigation item, "Submit New App" link.
* **Settings List:**
  * Theme Switcher: Segmented control (`Dark`, `Light`, `System`).
  * About Apx: Version number, Developer Shiva Miri credits, Tagline.
  * Privacy Policy: Modal view.
  * Support: Direct email/contact link.
  * Logout: Secondary outlined button.

---

### 6.2 Website Pages (Core PHP + Mobile-First)

#### Page W-01: Public Catalog & Details (Mobile-First Web)
* Matches screens N-01, N-02, and N-03 visually with responsive CSS.
* **Download Button Behavior on Web:** Clicking "Download APK" does not navigate away; triggers browser file download directly from GitHub Releases or Google Drive source link, while sliding up a bottom instruction sheet: *"Download started! When finished, open your notification bar to install."*

#### Page W-02: Developer Submission Portal (Desktop & Mobile Web)
* **Auth Guard:** Requires Google Sign-In. Redirects unauthenticated users immediately.
* **Form Layout (Two-Column on Desktop, Single-Column on Mobile):**
  * *Column 1 (Identity & Assets):*
    * App Name (`input`)
    * Package Name (`input` mono, with validation check indicator)
    * Category (`select` dropdown)
    * Icon URL (`input`, with live 64px image preview thumbnail)
    * Feature Graphic URL (`input`, with 16:9 preview container)
    * Screenshot URLs 1 through 8 (`repeatable input fields` with preview strip)
    * YouTube URL (`input` optional)
  * *Column 2 (Binary & Description):*
    * Version Code (`number input`, must be integer)
    * Version Name (`input`, e.g., `1.0.0`)
    * APK Source Link (`input`, GitHub Release or Google Drive link)
    * Guidance Banner: *"For APKs larger than 100 MB, please use GitHub Releases for fast, uninterrupted downloads."*
    * Auto-Detect Size Trigger: Button "Verify Link & Calculate Size".
    * Size Result Display: Shows detected size in MB. If Google Drive throttles or files exceed 100 MB, unlocks the manual input field: *"Manual Size Input (MB): [   ]"*.
    * App Description (`textarea` with markdown preview).
  * *Action Footer:* "Submit for Review" (Primary Gradient Button), "Save Draft" (Secondary).

#### Page W-03: Developer "My Apps" Dashboard
* Table / list showing all submitted apps by logged-in Google user.
* Columns: App Name, Package Name, Version, Status Pill (`Pending` [Amber], `In Review` [Blue], `Live` [Green], `Rejected` [Red]).
* Rejection Details: If status is `Rejected`, an expandable alert box displays the Admin Rejection Note detailing required fixes.
* Version Update Action: "Submit Update" button. App remains `Live` during review of new version code.

---

### 6.3 Admin Control Panel (Website Desktop Only)

#### Page A-01: Admin Login (`/admin/login.php`)
* **Access Control:** Standalone desktop view, black-slate canvas (`#070B14`).
* Centered card (`400px` width) with custom lock icon and "Apx Control Center".
* Form: Username, Password, CSRF token.
* Rate-limiting notice: Locks IP after 5 attempts for 15 minutes.

#### Page A-02: Admin Dashboard & Moderation Queue
* **Navigation Bar (Left Sidebar):**
  * Admin Profile ("Shiva Miri - Superadmin")
  * Moderation Queue (with badge counter of pending apps)
  * All Applications
  * User Directory
  * Force Update Controller
  * Direct App Upload
* **Metrics Header (4 Metric Cards):**
  1. *Total Apps:* Large numerical stat `#F8FAFC`.
  2. *Live Apps:* Green indicator `#10B981`.
  3. *Pending Queue:* Amber warning `#F59E0B`.
  4. *Banned Users/Apps:* Red alert `#EF4444`.
* **Moderation Table:**
  * Row per pending submission: App Icon, App Name, Developer Google Email, APK Link (direct click to download test binary), Reported Size, Submission Timestamp.
  * Action Buttons:
    * "Approve" (Green button: immediately sets status to `Live` and updates catalog).
    * "Reject" (Red button: opens modal requiring mandatory rejection feedback note).

#### Page A-03: Force Update Management
* Form to broadcast critical updates to all native Android app clients:
  * Minimum Required Version Code (`input` integer)
  * Latest Version Code (`input` integer)
  * Direct APK Download Link (`input` URL)
  * Update Changelog Message (`textarea`)
  * "Save & Broadcast" button with confirmation alert.

#### Page A-04: Admin Direct Publishing
* Form identical to developer submission form, with administrative bypasses:
  * Skips approval queue; status is immediately `Live`.
  * Assigns permanent `is_admin_app = 1` flag and renders exclusive `[Admin Verified]` purple badge (`#8B5CF6`).

---

## 7. Accessibility & Implementation Rules

### 7.1 Color Contrast Ratios (WCAG AA Standard)
* All primary body text (`#F8FAFC` on `#070B14` and `#0D1527`) exceeds a **14:1** contrast ratio (far surpassing the 4.5:1 WCAG AA minimum).
* Secondary metadata text (`#94A3B8` on `#0D1527`) maintains a **5.2:1** contrast ratio.
* Accent cyan (`#00E5FF`) is used as a border, icon, or headline accent; when used as button text, it sits on dark backgrounds with a minimum **7.1:1** ratio.
* In Light Mode, text `#0F172A` on `#FFFFFF` maintains a **16:1** contrast ratio.

### 7.2 Focus Rings & Keyboard Navigation
* Any interactive element (buttons, cards, links, inputs) navigated via keyboard (`Tab` key) must display an unequivocal focus ring:
  * `outline: 2px solid #00E5FF;`
  * `outline-offset: 2px;`
* `outline: none` is strictly prohibited unless replaced by the defined focus ring style.

### 7.3 Touch Targets & Ergonomics
* All touch targets on mobile (native Android and mobile web) must meet the minimum physical dimensions of **48px x 48px** (or minimum 44px with a 4px transparent touch padding boundary).
* Form inputs and primary CTA buttons have a minimum height of **44px**.

---
*End of Design System Specification. Document preserved in repository root as `DESIGN.md`.*
