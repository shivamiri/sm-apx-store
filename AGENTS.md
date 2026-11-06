AGENTS.md - AI Coding Assistant Operating Rulebook
1. Project Summary & Canonical Hierarchy
SM Apx Store (short: Apx, developer: Shiva Miri, tagline: "Discover. Download. Explore.") is an open, verified APK discovery and distribution platform. It consists of a native Android client (Kotlin, built in Google AI Studio) and a mobile-first companion website with a desktop-only admin panel (Core PHP + MySQL on free shared hosting). All software is constructed incrementally, one file at a time, strictly adhering to the repository specifications.
Source-of-truth files in strict descending priority order:
 * PRD.md (Product Scope, Requirements & Business Logic)
 * DESIGN.md (Design Tokens, Palette, Typography, Component States & UI Layouts)
 * ARCHITECTURE.md (Folder Structure, Tech Stack, Data Flows & Constraints)
 * DATABASE.md (Database Schemas, Normalization, Indexes & Seed Data)
 * API.md (REST JSON Endpoints, Unified Response Envelope & HTTP Codes)
2. General Engineering DOs & DO NOTs
DO
 * Follow Established Structures: Strictly place code inside paths defined in ARCHITECTURE.md.
 * Reuse Components: Use existing partials (common/header.php, common/bottom.php), CSS variables, and Kotlin helper classes.
 * Write Focused Code: Keep functions small, modular, and single-purpose; document non-obvious logic with concise inline comments.
 * Handle All States: Every feature must handle default, loading, error, empty, and disabled states gracefully.
 * Plan & Summarize: State what you plan to do, write the file, then summarize changes concisely.
DO NOT
 * Do Not Change the Tech Stack: No npm, Node.js, Composer dependencies, third-party frameworks, or build steps.
 * Do Not Add External Libraries: Never introduce external packages without explicit user approval.
 * Do Not Modify Schemas Silently: Never alter database columns, tables, or queries without updating DATABASE.md first.
 * Do Not Rewrite Working Code: Modify only what is requested; never refactor or touch stable modules without instruction.
 * Do Not Hardcode Secrets: Use constants in common/config.php or environment definitions; never commit credentials.
3. Mandatory Project-Specific DO NOT Rules
 * No APK Hosting or Proxying: The server NEVER hosts, proxies, streams, or buffers APK binaries. Only external download URLs (GitHub Releases or Google Drive) are stored. Never write a download-proxy PHP script or endpoint.
 * No Silent / Background Installs: The native app downloads directly from external links and dispatches a system PackageInstaller intent (FileProvider). Silent installs do not exist. The mobile website only downloads the file; the user opens it manually.
 * Prefer GitHub Releases: Google Drive direct links are unverified and prone to interstitials. Never rely on automated Drive tokens; prioritize GitHub Releases across all upload instructions.
 * Resilient API & Bot Protection: Free shared hosting (InfinityFree) may return HTML/bot interstitials (aes.js) instead of JSON. The native app must handle non-JSON responses safely and use api/app_config.php as a health probe.
 * No Custom JWT / Token Tables: Web and admin authentication use native PHP sessions ($_SESSION). The native app supplies Google ID tokens only where required by API.md (submit_review.php). Never invent JWT infrastructure.
 * No Daemons or In-Memory Queues: No Redis, Memcached, RabbitMQ, or background workers. Background actions rely solely on standard web triggers, cron, or database state.
 * Public Feeds Must Be Live-Only: Public API endpoints and catalog pages must strictly filter for status = 'Live' and deleted_at IS NULL.
 * Plain URLs Only: Never output Markdown link syntax ([text](url)) in code, SQL, seed data, or JSON payloads. Use plain URLs only.
4. Conflict Resolution & Known Gaps
 * Source-of-Truth Conflict Policy: If any instruction or prompt conflicts with PRD.md, DESIGN.md, ARCHITECTURE.md, DATABASE.md, or API.md, stop and ask the user for clarification before generating code.
 * Known Gap (Rate Limiting Table): API.md references a rate_limits table, but DATABASE.md does not yet define it. AI assistants are strictly forbidden from writing rate-limiting implementation code or inventing table schemas until DATABASE.md is officially updated with the table definition.
