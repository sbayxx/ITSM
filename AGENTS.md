# multi-customer ITSM project

## Goal
Multi-customer ITSM with customer-scoped tickets, users, CI items, Intune devices, CSI register, and Year Wheel for recurring annual tasks.

## Constraints & Preferences
- Roles: `admin` (system-wide), `customer_admin`/`manager`/`agent` (per-customer via pivot `customer_user`), `user` (single customer via `customer_id`).
- Agents can see multiple customers; users see only their own customer's data.
- Each customer has own Azure AD/Intune config stored in `customers.settings` JSON.
- Existing data migrated to GovernIT (`customer_id = 1`).
- DemoCorp and TestOrg as two demo customers with demo users (pw: `demo1234`), CI items, and tickets.
- `organizations` table stays separate from customers.
- Year Wheel is customer-scoped, with auto-rollover for recurring tasks, supports both single-date and multi-month durations, wheel view as default.
- `SESSION_SAME_SITE = 'Strict'`, `SESSION_SECURE = true`, `SESSION_HTTP_ONLY = true`.
- PHP 8.1.18 (upgraded from 8.0.28).

## Progress
### Done
- CSI customer scoping: `CsiItem` model updated with `Auth::customerScope()`, `customer_name` join, `customer_id` filter; `CsiController` passes `customer_id`; `CsiListView` has customer badge column; `CsiFormView` and `CsiDetailView` have customer dropdown for admin/customer_admin.
- `013_csi_customer.sql` deployed and migrated, existing CSI entries moved to GovernIT.
- Incident, Service Request, Problem, and Change creation forms now have separate **Reporter** (who logs it, defaults to logged-in user, overridable by agents) and **Requester** (who needs it) fields, plus customer dropdown for agents.
- All 4 ticket list views now have **Reporter** and **Requester** columns.
- All 4 ticket detail views show **Reporter** and **Requester** labels correctly.
- `requester_id` column added to `tickets` table via migration `014_requester.sql` (FK to users).
- `Ticket::find()` and `all()` join `requester_name`; `create()` defaults `requester_id` to `reported_by` if not set.
- `TicketController::store()` passes `requester_id` through.
- Created `IntuneService.php`, `IntuneDevice.php` model, `IntuneDevicesView.vue`.
- Microsoft SSO: `AuthController::microsoftUrl()` + `microsoftCallback()`, migration `009_microsoft_sso.sql`.
- Azure AD user sync: `IntuneService::fetchAzureUsers()`, `UserController::syncAzure()`.
- Multi-tenancy migrations: `010_customers.sql`, `011_customer_seed.sql` (GovernIT, DemoCorp, TestOrg + data migration).
- `Auth.php`: `accessibleCustomerIds()`, `customerScope()`, `isCustomerAdmin()`, `canAccessCustomer()`.
- `Ticket.php`, `CiItem.php`, `IntuneDevice.php`, `User.php`: customer scoping + joins.
- `CustomerController.php`: full CRUD + settings + user assignment + `myCustomers()`.
- `UserController.php`: `syncAzure()`, `userCustomers()`, `updateUserCustomers()`.
- `ReportController.php`, `TicketController.php`, `CiController.php`: `customer_id` filter.
- Frontend: `CustomerManagementView.vue`, `CustomerDetailView.vue`, auth store `isCustomerAdmin` getter, sidebar + router for customers.
- User management: customer column, primary customer dropdown, multi-customer pivot checkboxes.
- Ticket list views (Incidents, Requests, Problems, Changes): customer column.
- Demo data migration `012_demo_data.sql`: users + CI items + tickets per customer.
- `sb@governit.dk` added to GovernIT `customer_user` pivot.
- Year Wheel: migration `015_year_wheel.sql` (tables `yw_categories`, `yw_tasks` with 6 default categories).
- `YearWheelCategory.php` and `YearWheelTask.php` models (customer-scoped, soft-delete for tasks, null-safe `canAccessCustomer()`).
- `YearWheelController.php` with full CRUD + rollover; uses `Request $request`; includes `Logger::log()` on all mutations.
- Routes for year-wheel categories + tasks + rollover in `api.php`.
- 7 frontend Year Wheel Vue components + sidebar nav + router.
- Customer dropdown for admins in `YearWheelView.vue`; `customer_id` passed in task creation payload.
- Frontend built with Vite and deployed via FTP.
- 60 demo tasks seeded (20 per customer).
- `YearWheelController` signature fixed: `array $params` changed to `Request $request`.
- **Root cause of Year Wheel 404**: Server has TWO copies of `api.php` — `/routes/api.php` (root, unused) and `/backend/routes/api.php` (actually used by `backend/public/index.php` via `require_once __DIR__ . '/../routes/api.php'`). I was uploading to the wrong path (root `routes/`). Now uploaded to `/backend/routes/api.php` — Year Wheel API returns 60 tasks and 6 categories successfully. 🎉
- `sb@governit.dk` password reset to `Skyservice20!`, role set to `admin`.
- Migration `016_rate_limit_lockout.sql` applied manually (was marked as executed but columns never existed — opcache prevented the migration runner from reading the SQL file). Added `failed_attempts` and `locked_until` columns to `users`.
- Migration `017_email_settings.sql` created and applied: `email_log` table + `email_message_id`/`email_from` columns on `tickets` with index.
- `MailService.php`: outbound PHP `mail()` sender, inbound RFC822 MIME parser, 3 HTML notification templates (ticket_created, ticket_updated, comment_added), email logging via `email_log`.
- `EmailController.php`: `POST /api/email/receive` (no-auth webhook), `POST /api/email/test` (auth). Creates tickets from inbound email, matches replies via subject `[TKT-YY-NNNN]` or `In-Reply-To`.
- `TicketController`: email notifications on `store()`, `update()` (with change diff), `addComment()` (public only). Variable rename `$ticket`→`$oldTicket`.
- Email routes in `backend/routes/api.php:185-187`.
- `Customer::getSettings()`: email defaults (smtp_host, smtp_port 587, smtp_encryption tls, from_email, from_name, inbound_email).
- **Auth.php security version deployed**: password stripped from all responses, `is_active=1` login check, account lockout (`failed_attempts`/`locked_until`), `require_once __DIR__ . '/../config/session.php'`.
- **Env.php and RateLimiter.php uploaded**: these core files were missing from server (never deployed from security audit). API immediately restored from 500.
- **`.env` uploaded and working**: DB credentials moved to `.env`, `Env::load()` reads it in `index.php`.
- **`session.php` config deployed**: defines `SESSION_LIFETIME`, `SESSION_SECURE`, `SESSION_HTTP_ONLY`, `SESSION_SAME_SITE`, `SESSION_DOMAIN`.
- **Login 500 FIXED**: Root cause = `Auth.php` referenced `failed_attempts`/`locked_until` columns that didn't exist in DB (migration 016 was never actually applied on server due to opcache). Manually ran ALTER TABLE statements via diagnostic script. Login now returns 200 with no password in response.
- **PHP upgraded 8.0.28→8.1.18**: This restart cleared opcache, making all new file uploads immediately live. No need to contact Gigahost for opcache reset.
- All diagnostic/cleanup files deleted from server.
- **Internal notes vs public updates**: All 4 ticket detail views now have two separate comment fields — "Public update" (visible to requester + all agents) and "Internal note" (agents/admins only, field hidden from regular users). Internal comments shown with yellow border + "Internal" badge in comment list.
- `Ticket::find()` filters out `is_internal=1` comments for non-agent users (`role=user` only sees public updates).
- Fixed `IncidentDetailView` bugs: was sending `content` instead of `body` (causing validation failure), was pushing response array instead of re-fetching.
- Added comment sections to `ProblemDetailView` and `ChangeDetailView` (were completely missing).
- Frontend built and deployed with Vite.

### ITIL Approval Fixes (K1-K4) — implemented, migrated, deployed, tested
- **K1 service-request catalog mapping**: `RequestFormView.vue` sent `catalog_item_id` while `TicketController` read `service_catalog_item_id`, so approval chains were never generated and requests were silently auto-approved. Now normalized via `$catalogItemId = $data['service_catalog_item_id'] ?? $data['catalog_item_id'] ?? $typeData['catalog_item_id'] ?? null`, written to `tickets.service_catalog_item_id`, `service_requests.catalog_item_id`, and passed to `Approval::generateForServiceRequest()`. Frontend now sends `service_catalog_item_id`.
- **K2 sequential gate**: `Approval::isCurrentStep()` — a step is only decidable when it is the lowest `status='pending'` step. Out-of-order decisions return **HTTP 409**.
- **K3 separation of duties**: requester (`requester_id`) and reporter (`reported_by`) can never decide on their own request — no admin override. Returns **HTTP 403**.
- **K4 tenant scoping**: migration `018_approval_customer_scope.sql` added `approvals.customer_id` + `idx_approvals_customer` + FK, backfilled from the parent ticket (8 rows, 0 orphans). `Approval::find()` / `findByTicket()` use `Auth::customerScope('a')`; `ServiceRequest::find()` uses `Auth::customerScope('t')`.
- **Rejected chains close out**: `Approval::reject()` marks all later pending steps `skipped` and the ticket `rejected`, so no step is left permanently open.
- **Guard centralized**: `Approval::guardDecision()` returns `[message, status]` or `null`, and is the single gate for all decision paths — `/api/approvals/{id}/approve|reject` AND the legacy `/api/service-requests/{id}/approve|reject`. `Approval::hasAuthority()` replaces the three duplicated `canDecide()` copies.
- **Verified live**: Hardware Request (catalog item 4) → `pending_approval` + chain; step-2-first → 409 (both admin and authorized manager); self-approve/reject → 403 with no state change; cross-tenant (DemoCorp user vs GovernIT ticket) → empty list + 404; manager happy path → `in_fulfillment`; reject step 1 → step 2 `skipped` + ticket `rejected`; skipped step → 422. All test data removed afterwards.
- **Password-hash leak found and fixed**: server `User.php` was an older copy that did not strip `password`, so `GET /api/users`, `GET /api/users/{id}` and `POST /api/users` returned bcrypt hashes. Deployed the local version which unsets it in `find()`, `all()` and `getCustomerUsers()`. Verified closed.
- **Pre-existing local parse errors fixed**: `User.php` had an orphaned duplicate SQL block in `getCustomerUsers()` and `core/Logger.php` had a duplicated `'summary' =>` key. Server copies were fine; all 75 backend PHP files now pass `php -l`.
- **Server cleanup**: deleted leftover `backend/diag.php`, `backend/diag2.php`, `backend/test.php`, `backend/public/seed-cmdb.php`, and a `public_html/` decoy tree containing a stray copy of the migration script.
- Frontend rebuilt (Node 26, vite 5.4.21, 4m12s) and deployed; `ApprovalFlow.vue` mirrors the server guards (disabled non-active steps, "Blocked — waiting for step N" hint, self-approval hidden).

### Blocked
- Intune data sync (GovernIT) blocked until `DeviceManagementManagedDevices.Read.All` application permission is granted admin consent in Azure Portal.
- Microsoft SSO redirect URI (`https://itsm.governit.dk/auth/microsoft/callback`) needs to be registered in Azure App Registration.
- Azure AD user sync needs `User.Read.All` application permission with admin consent.

## Round 2 Fixes — implemented, deployed, live-tested (2026-09-28)
- **Cross-tenant `Ticket::find()` closed.** `Ticket::find()` and `Ticket::findByNumber()` now apply `Auth::customerScope('t')`. Verified: DemoCorp user → GovernIT ticket 51 = **404**; own ticket = 200; admin = 200.
- **Duplicate audit rows fixed.** Removed the `Logger::log()` from `TicketController::destroy()`; `Ticket::delete()` holds the single entry. Verified ticket 58 produced **exactly 1** `audit_log` row. (Historical duplicate pairs still visible as ids 394/395, 396/397, 398/399, 406/407.)
- **Dynamic fields no longer dropped.** `TicketController::store()` reads `type_data.dynamic_fields` (legacy top-level `dynamic_fields` still honoured) and merges into `service_requests.fulfillment_data`. Verified round-trip both ways; merge with `type_data.fulfillment_data` also works.
- **Internal notes restricted to `admin`/`agent`.** New `Ticket::INTERNAL_NOTE_ROLES` + `Ticket::canSeeInternalNotes()`; `TicketController::addComment()` returns **403** for other roles. `Ticket::find()` filters internal comments on read. New frontend getter `auth.canSeeInternalNotes`. Verified: user → 403 write + hidden on read; manager → 403 write + hidden on read; agent/admin → 200 + visible.
- **`test_sba@governit.dk` (id 50) deleted.** Probe manager (id 58) and all probe tickets/comments removed afterwards; counts back to baseline (50 tickets, 27 users).
- **8 local↔server drifted files reconciled** and deployed: `config/database.php`, `controllers/AdminController.php`, `controllers/AuthController.php`, `controllers/UserController.php`, `core/Database.php`, `core/Logger.php`, `core/Request.php`, `core/Validator.php`. All verified byte-identical on the server.
- **Stale `/assets` pruned.** Real count was **1.719 stale / 1.761 total**, not 248. Down to **42 files** (exactly the current build) + `assets/.htaccess` + `assets/knowledge/`.
- **Cache headers live.** `index.html` → `no-cache, must-revalidate`; `/assets/*` → `public, max-age=31536000, immutable`. This is what makes aggressive asset pruning safe — no client can pin a stale `index.html`.
- Frontend rebuilt (13 min) and deployed; live entry bundle is `index-CWmZVcSD.js`.

## Bugs found and fixed during round 2
- **`POST /api/users` returned 500.** `UserController::store()` passed the raw request body (including `password_confirmation`) into the INSERT → `Unknown column 'password_confirmation'`. Now whitelists real columns, which also blocks mass-assignment of `id`/`is_active`/`deleted_at`. Added `Validator::matches()` and made `password_confirmation` required here (it was only validated in `AuthController`).
- **Any malformed id caused an unhandled 500.** `Ticket::find(int $id)` + a non-numeric `{id}` throws `TypeError`, which is an `Error` and was **not** caught by `catch (Exception)` in `index.php`. Two fixes: `catch (Throwable $e)` in `index.php`, and `(int)` casts in the 6 controllers that lacked them. `/api/tickets/incidents`, `/api/users/abc`, `/api/csi/xyz` etc. now return **404**.
- **All 3 customer-scoped report endpoints returned 500 for admins.** `ReportController::customerCondition()` returned `'1=1'` for admins, but callers interpolate it after an existing `WHERE` term → `WHERE ... 1=1 GROUP BY`. The unrestricted case must be an empty fragment. Fixed; verified admin sees 50 tickets, DemoCorp sees 3 (tenant isolation holds).
- `Validator::regex()` had a password-specific error message hardcoded in a generic method → added a `$message` parameter.
- `Request::all()` for header-less JSON payloads: the strict content-type check was a regression risk, so `Request.php` now sniffs a leading `{`/`[`.

## Tasks module (2026-09-28) — designed, implemented, migrated, deployed, live-tested
- **Requirement clarified with the user**: tasks are NOT only year-wheel items. A task must be able to be *marked* for the wheel, and tasks that are not marked must stay off the wheel. Every user needs one place holding all of their tasks.
- **Decision: reuse `yw_tasks`, do not add a `tasks` table.** A second table would have needed a sync path into the wheel and could hold two divergent copies of the same work. `yw_tasks` is now a general worklist gated by an opt-in flag.
- **Migration `019_tasks_module.sql`** (applied): `yw_tasks.show_in_wheel TINYINT(1) NOT NULL DEFAULT 1` + `idx_yw_tasks_wheel(show_in_wheel,status,start_date)` + `idx_yw_tasks_assignee(assigned_to,status)`; `start_date` relaxed to `DATE DEFAULT NULL`; new global category **"Tasks" (id 7, `#64748B`, ✅, sort_order 100)**.
- `DEFAULT 1` is deliberate: all 60 pre-existing tasks were created for the wheel and must stay visible. The Tasks UI sends `show_in_wheel = 0` explicitly, so wheel inclusion is opt-in for new tasks, never opt-out.
- **`YearWheelTask::find()` was NOT customer-scoped** (it was on the known-issues list) and `YearWheelController::show()` returned it → any tenant could read/update/soft-delete another tenant's task by id. Now applies `Auth::customerScope('yt')`. Verified: TestOrg user → DemoCorp task = 404 on GET, PUT and DELETE.
- `YearWheelTask::all()`: new `show_in_wheel` and `priority` filters. `show_in_wheel` uses `array_key_exists`/`!== ''` rather than `!empty()` because **`!empty('0')` is false in PHP** and would have silently turned "only tasks off the wheel" into "no filter".
- `ORDER BY (yt.start_date IS NULL) ASC, start_date ASC` so undated tasks sort last instead of first.
- `update()`: added `show_in_wheel`, and `completed_at` is now **cleared** when status moves off `completed` (previously it was only ever set, so a re-opened task kept a completion stamp).
- `rollover()` now filters `show_in_wheel = 1` so off-wheel tasks are never carried into next year.
- **`Response::success()` signature is `($data, $message, $status)`** — the existing `Response::success($x, 201)` / `success(null, 204)` calls in `YearWheelController` put the int into *message* and leave HTTP 200. Pre-existing app-wide, left alone deliberately; do not assume a 201/204 here.
- **Wheel-date invariant enforced server-side** (the UI is not the only caller): a task that is or becomes a wheel task must keep a `start_date`, else **400**. Covers `POST`, `PUT` nulling the date, and promoting an off-wheel task.
- `YearWheelView.vue` now sends `wheel_only: 1`, so off-wheel tasks can never leak into the wheel/table/list/calendar views.
- **New `frontend/src/views/tasks/TaskListView.vue`**: My Tasks / All Tasks toggle, status + category + assignee + on/off-wheel filters, 4 counters (planned / in progress / overdue / on wheel) computed from the loaded rows so they always describe what is on screen, inline `<select>` status and assignee that PATCH immediately, delete, and create/edit through the **reused** `YearWheelTaskForm.vue` modal with `default-show-in-wheel=false`.
- `YearWheelTaskForm.vue`: added the "Show in Year Wheel" toggle, made start date conditionally required, surfaced backend validation errors, and hid the recurring control when the task is off the wheel (recurrence/rollover is wheel machinery).
- Router `/tasks`; sidebar item `✅ Tasks` placed after Year Wheel.
- **Tests: 25/25 API + 19/19 integration green** (cross-tenant, opt-in/opt-out both ways, `completed_at` lifecycle, filters, non-admin create path, wheel exclusion). All probe data removed; active tasks back to **60**.
- Frontend built in 1.6s (not 13 min — the earlier timings were a cold environment). Deployed 45 files, pruned 41 stale; live entry `index-M4Eqrske.js` + `index-PbDNoHFZ.css`, 44 assets.
- **`backend/storage/rate-limiter/login_127.0.0.1.lock` was a stale artifact** from an older RateLimiter that sanitised keys instead of md5-hashing them. Current `RateLimiter.php` is byte-identical local↔server; the live lock file is `md5('login:'.REMOTE_ADDR)`. Throttle is 5 logins / 15 min **per IP**, so tests must clear `backend/storage/rate-limiter/*.lock` over FTP.
- Server is MySQL **5.7.19** — no `ADD COLUMN IF NOT EXISTS` / `ADD INDEX IF NOT EXISTS`, so ALTER migrations are strictly one-shot (split a migration and re-run it, don't re-run the whole file).

## Known Issues (still open)
- ~~Password DB credentials hardcoded as `database.php` fallbacks / `.env` missing~~ — **resolved 2026-09-28**: `.env` did exist all along (an earlier `is_file()` probe was wrong/misleading); `database.php` rewritten to `require_once core/Env.php` with **zero fallbacks** (`DB_HOST/DB_NAME/DB_USER/DB_PASS` are `Env::get(...)` only). Verified live: login 200 with `.env` as sole source. Secrets live only in `backend/.env`.
- ~~No catalog item defines `fields`~~ — **resolved 2026-09-28**: migration `020_service_catalog_fields.sql` applied (column + seed), items 4 (Hardware Request) and 6 (VPN Access) each have 3 fields; end-to-end verified (form renders + detail round-trips).
- Intune/SSO/Azure permissions still blocked (see Blocked). SSO *button* removed from login page (dead flow — see "Remaining-items round").
- ~~Outbound SMTP deliverability still unverified~~ — **resolved 2026-10-21**: PHP `mail()` via Gigahost sendmail verified end-to-end. Test email sent to `bay@flyingfish.dk` (same domain as MX) → delivered and received. SPF record `v=spf1 mx include:spf.gigahost.dk -all` is correctly configured. Note: `sb@governit.dk` did not receive the same test (likely filtered by the recipient provider — different domain, external MX). For production, emails to external domains should still be spot-checked; if deliverability becomes an issue, consider SendGrid/Mailgun. Inbound email IS verified end-to-end.
- ~~CSP blocks Google Fonts~~ / ~~breadcrumb `unknown`~~ / ~~no catch-all route~~ — all three fixed 2026-09-28, see "Polish round" below.

## Security & status-code fixes (2026-09-28) — deployed and live-tested
- **`Response::success()` now honours integer status codes.** The signature is `($data, $message, $status)`, but four call sites in `YearWheelController` passed the status as the 2nd argument (`success($x, 201)`, `success(null, 204)`), so those endpoints silently returned HTTP 200 with the int as the message. `success()` now treats a lone int 2nd argument as the status (`func_num_args() === 2 && is_int($message)`), so create → **201**, delete → **204**, normal string-message calls stay 200. Parameter widened to `string|int $message`. Live-verified: task/category create 201, delete 204, list/logout 200.
- **`CiItem::find()` is now customer-scoped** (`Auth::customerScope('ci')`). It had `customer_id` and a scoped `all()`, but `find()` was raw → any tenant could read another tenant's CI by id. The controllers that gate via `find()` (show/update) are now closed; `CiController::destroy`, `relate`, `unrelate`, `setCiTags`, `auditLog` were additionally gated (destroy/relate/unrelate return 404 on inaccessible ids; the raw `Database::fetch("SELECT id ...")` existence checks in `setCiTags`/`auditLog` now go through scoped `find()`). Live-verified: DemoCorp user → GovernIT CI = 404 on GET, PUT and DELETE; own CI still 200.
- **`CsiItem::find()` is now customer-scoped** (`Auth::customerScope('c')`). Same leak; all `CsiController` operations already gate through `find()`, so read/update/delete are closed in one shot. Live-verified: DemoCorp GET/DELETE on a GovernIT CSI item = 404.
- **`SlaContract::getComplianceReport()` is now customer-scoped** — the two ticket counts queried `tickets` with no tenant filter, leaking every tenant's ticket volume/breach stats to any caller. Now applies `Auth::customerScope('t')`. Verified: a fresh GovernIT ticket appears in the admin report (medium=1) but DemoCorp still sees all 0. NOTE: `sla_contracts` and `knowledge_articles` still have **no `customer_id` column** — those entities are global by design now (SLA targets are per-priority templates, knowledge base is shared), so their `find()` methods were left un-scoped.
- Still worth auditing later: `CiItem::relate`/`unrelate` relationships and `ci_audit_log`/`ci_item_tags` are gated at the controller on the CI ids, but the sub-rows themselves are not additionally filtered (same-tenant relationships are assumed).
- The git repo at the workspace root belongs to a **different project** ("horse manager web app"); this ITSM tree is untracked, so there is no ITSM history to reconcile. Do not commit without an explicit request.

## Icon system (2026-09-28) — Lucide, deployed and live-verified
- **User chose (via prompt):** Lucide (`lucide-vue-next`), whole-system scope, **stroke-width 1.5** (Apple-inspired elegance), replacing all emoji/text glyphs.
- `frontend/src/utils/icons.js`: central registry (kebab-case name → component) + emoji→icon map for `yw_categories.icon` seed data (🔍→file-search, 👥→users, 🔒→lock, 🔧→wrench, 🎓→graduation-cap, 💰→coins, ✅→check-circle) + `resolveIconName()` fallback.
- `frontend/src/components/common/AppIcon.vue`: wrapper, props `name` / `size` (default 18) / `strokeWidth` (default 1.5), unknown name → `circle`, `class="shrink-0"`, `aria-hidden="true"`. **Lucide detail: its `Icon.js` overwrites `width`/`height` attrs with the `size` prop — AppIcon MUST pass `:size`, not width/height.** `strokeWidth` and `absoluteStrokeWidth` (default false) are supported props.
- 20 Vue files converted, **0 emoji/glyph left in `src/`** (only the intended emoji keys inside `utils/icons.js`): AppSidebar (19 nav icons; sidebar icon names are stored in `nav`/`admin` arrays), AppHeader (Menu/Power), LoginView (mail/key-round/circle-alert/loader spinner), TaskListView, YearWheelView, YearWheelList/Table (category emoji → AppIcon tinted with `:style="{color: cat.color}"`), YearWheelTaskDetail/TaskForm, ApprovalFlow (check/x markers, size 14), 9 back-link files (`&larr;`→`arrow-left` + `inline-flex items-center gap-1`), ChangeFormView, GdprDashboardView.
- `frontend/public/favicon.svg` added (OC monogram, was 404); `index.html` already referenced `/favicon.svg`.
- Sidebar mapping: Dashboard=gauge, Incidents=siren, Service Requests=clipboard-list, Problems=search, Changes=arrow-left-right, CSI=trending-up, CMDB=boxes, Intune=monitor-smartphone, Knowledge=book-open, SLA=timer, Reports=bar-chart, Year Wheel=circle-dashed, Tasks=check-circle, User Management=users, Impersonate=user-cog, Audit Log=scroll-text, Customers=building, GDPR=shield, Settings=settings.
- **Live-verified with headless Chrome (screenshots + DOM assertions)**: 19–21 `svg.lucide` per page, all `width=14|18|20` / `stroke-width=1.5`, **0 emoji in body text**, 0 Vue warnings, favicon 200, approval markers green `lucide-check w=14` / red `lucide-x w=14`, back-arrows render, chart.js reports page intact. Pages checked: login, dashboard, tasks, year-wheel (table/list/modal), incidents (list/create), reports, cmdb, settings, admin/users, admin/audit-log, change create, approval flow pending/approved/rejected.
- Verification scripts (`verify-icons*.mjs`, `mini-test.mjs`) and `puppeteer-core` were installed with `npm i --no-save` and **have been deleted/removed** — do not expect them to exist; reinstall the same way if re-verification is needed. Login tests hit the 5/15min rate limit quickly: clear `backend/storage/rate-limiter/*.lock` over FTP between runs.
- `ci_types.icon` names are NOT rendered anywhere yet (out of scope for this round — offered as a possible next step).

### Polish round (2026-09-28, same day) — fonts, breadcrumbs, catch-all, ci_types icons — deployed and live-verified
- **Fonts self-hosted; the CSP violation is gone.** `frontend/index.html` no longer links Google Fonts (all 3 `<link>`s removed). 4 variable-font woff2 files live in `frontend/public/fonts/` (inter-latin, inter-latin-ext, jetbrains-mono-latin, jetbrains-mono-latin-ext — Google serves **one variable file per subset for ALL weights**, so per-weight copies were deduped) with 4 `@font-face` blocks (`font-weight: 100 900` / `100 800`, `font-display: swap`, original `unicode-range`s) prepended to `frontend/src/assets/main.css`. Root `.htaccess` CSP `font-src 'self'` already allowed this; `style-src` needed no change since the CSS is local. Verified live: `document.fonts.check('16px Inter')` + JetBrains Mono = true, **0 CSP console errors** (was 1-2 per page load before).
- **Breadcrumb map completed** in `AppHeader.vue`: added `IntuneDevices`, `YearWheel`, `Tasks`, `Customers`, `CustomerDetail`, `AuditLog`, `Impersonate`. All header-rendered routes now resolve (only `Login`/`MicrosoftCallback` remain unmapped — those pages have no header). Verified: `/tasks` → `tasks`, `/admin/audit-log` → `admin/audit-log`.
- **Catch-all route added** in `frontend/src/router/index.js` (last entry): `{ path: '/:pathMatch(.*)*', redirect: '/' }`. Unmatched URLs used to render a fully blank page. Verified: `/audit-log` → `/`.
- **`ci_types.icon` rendered in CMDB**: `CiListView.vue` Type-cell badge and `CiDetailView.vue` header badge now show an `<AppIcon :name="item.ci_type_icon" :size="14">` inside the badge (inherits badge color). Backend already returned `ci_type_icon` on list+detail — no API change. 9 new registry entries: `server, wifi, code, database, monitor, smartphone, printer, cloud, cube`. **`Cube` does not exist in lucide-vue-next (it is one of the mangled names) — the registry maps `cube: Box` instead**; `Box` renders the same cube shape. Verified live: `lucide-server/lucide-code/lucide-smartphone/lucide-wifi/lucide-database`, all `width=14`, 0 emoji on the page.
- Static registry check (reusable pattern before any build): import `icons` from `utils/icons.js`, regex-scan all `src/**` for `<AppIcon name="...">` + `icon: '...'`, report names missing from the registry — 46 registered, 31 used, 0 unknown. **Do this before builds**: a bad lucide import costs an 11-minute failed build to discover (one run took 11m11s; typical builds are seconds).
- Build+deploy cycle: `npx vite build`, then a python3/ftplib pass that md5-compares `dist/` against the server, uploads changed files, and deletes stale `assets/*` (keeping `assets/.htaccess` and `assets/knowledge/`). This round: 48 uploaded, 43 stale removed. **`assets/.htaccess` (immutable cache header) lives in `frontend/public/assets/`** so every build includes it — it had vanished from the server (only a workspace-root helper copy existed, which deploys never uploaded); restored 2026-09-28 and verified `Cache-Control: public, max-age=31536000, immutable` back on `/assets/*`.
- Verification (`puppeteer-core` via `npm i --no-save`, deleted again after): fonts loaded, breadcrumbs, catch-all redirect, CMDB icons, 0 console errors. Remember to clear `backend/storage/rate-limiter/*.lock` over FTP first.

### Remaining-items round (2026-09-28, same day) — .env, catalog fields, email E2E, SSO — deployed, tested, cleaned up
- **`.env`**: file already existed on the server with correct credentials (the earlier `is_file()` probe in Known Issues was wrong). `backend/config/database.php` rewritten: `require_once core/Env.php` + **no hardcoded fallbacks** (DB creds are `Env::get(...)` only; non-secret defaults like charset kept). Verified live: login 200 sourced solely from `.env`.
- **Catalog `fields`**: migration `backend/migrations/020_service_catalog_fields.sql` applied manually on MySQL 5.7 (one-shot: guarded ALTER + JSON seed) — items **4 (Hardware Request)** and **6 (VPN Access)** now have 3 fields each. `TicketController::show()` attaches `catalog_item` (including `fields`) to the detail payload; `RequestFormView.vue` got a `selectedFields` computed so the create form renders the dynamic inputs. Live UI-verified with headless Chrome: form shows "Device type / Business justification / Needed by", detail round-trips the submitted values, API returns `catalog_item.fields`=3 + `fulfillment_data` intact.
- **Email end-to-end**: `tests/email_e2e_test.py` → **13/13 PASS** (bracketed `[SR-26-…]` reply matched onto existing ticket with comment append; fresh inbound email → new ticket `source=email`, correct customer; `email_log` 10→15 = 3 outbound creates + 2 inbound logs). `tests/fields_api_test.py` → **12/12 PASS**.
- **Bug found by the email test**: `MailService::extractTicketNumber()` returned only the type group (`INC`) for bracketed subjects instead of the full token — subject-based reply matching had *never* worked (only `In-Reply-To` had). Fixed; verified by the E2E test.
- **Bug found by UI check**: create routes are `/requests/create` etc. (static segment beats `:id`), but `AppHeader.vue` breadcrumb values used `/new` → landed in the detail view → 404. All 7 breadcrumb values changed to `/create` (`/requests/new` is *not* a valid create URL). Rebuilt + redeployed.
- **SSO removed**: dead `loginMicrosoft()` fn + `client` import + commented-out button deleted from `LoginView.vue` (backend `microsoftUrl`/`microsoftCallback` routes left in place — they're harmless until Azure is configured).
- **Cleanup**: test ticket id 63 (UI-fields check) created and deleted (404 verified); one-off server scripts `probe.php`, `apply020.php`, `elog.php` deleted from FTP root; `uicheck*.mjs`/`shot.mjs` deleted; `puppeteer-core` pruned (`npm ls` empty, 0 refs in package.json/lock).
- Server was briefly slow/disconnected mid-round (login page 7.6s, one `RemoteDisconnected`, one 90s hang) — transient; retried and all operations completed. Expect cold-start latency on first request after idle.

### Compliance reports (2026-10-21) — GDPR + ISO 27001, research-based, deployed and live-verified
- **Research (task-agent, web sources)**: GDPR Art. 5/7/12/17 reporting KPIs (DSR timeliness vs 1-month deadline, consent coverage, retention coverage, accountability/attribution) + ISO 27001:2022 Annex A monitoring KPIs (A.5.15-18 access, A.5.24-28 incidents, A.8.32 changes, A.8.15-16 logging, A.8.9 CMDB), plus dashboard best practice: 4 RAG states only (compliant/partial/non-compliant/not assessed), score bars not gauges, point-in-time snapshot timestamp, N/A excluded from rollups.
- **New endpoint `GET /api/reports/compliance?period=30[&customer_id=N]`** (`ReportController::compliance()` + route with `['auth']`). Returns `frameworks[]` (gdpr, iso27001) with rollup `score`/`status`/`assessed/total` + `cards[]` (id, title, reference, icon, status, score, metrics[{label,value,tone?}], optional detail rows). 9 cards: GDPR = Data Subject Rights (erasure timeliness, overdue >1 month), Consent Management (users-table coverage), Retention & Storage Limitation (per-policy past-retention counts + coverage of 6 record types + last `retention_cleanup` run), Accountability & Audit Trail (events/attribution/sources/actors/top actions); ISO = Access Rights (dormant >90 d, privileged dormant), Incident Management (MTTR, SLA met, recurrence via problem_incidents), Change Enablement (approval coverage, **unauthorized changes** = implemented with cab_required but no decision → forces non_compliant), Logging & Monitoring (attribution + email delivery rate), Configuration Management (owner/location completeness, stale >60 d, orphans, ticket-CI linkage). MySQL 5.7-safe (no window functions). Score formulas + RAG thresholds are in the card methods.
- **Access gate**: `Auth::isAdmin() || Auth::isCustomerAdmin()` in-controller (403 for agents/users — verified live with alice). **`customer_admin` is NOT storable today**: `users.role` ENUM from 001 has no `customer_admin`, `UserController::store()` rejects it, `roles` seed has only 4 roles, UserManagement UI hardcodes 4 — the role exists only in `Auth::isCustomerAdmin()`/auth-store getters and a ChangeFormView approval option (latent: an approval chain step with `role=customer_admin` can never match anyone). Pre-existing gap, left alone; the compliance gate is forward-compatible. Optional `customer_id` query param (admin only, cross-tenant id ignored via intersect) exercises exactly the same scope-SQL path customer_admin will use.
- **Tenant scoping verified**: `scopeIds()` (null = unrestricted admin, array = allowed ids) + `tenantCol()` (customer_id columns) + `userCol()` (user-id columns via users+pivot subquery) + `usersCol()` (users-table rows). Admin global 24 active users vs GovernIT 18 + DemoCorp 6 + TestOrg 6 (sum > global because pivot agents counted per tenant — expected); audit events global 203 vs tenants 166+32+1 (system events excluded when scoped).
- **Frontend `ReportDashboardView.vue`**: added Overview/Compliance tabs (Compliance rendered only if `auth.isAdmin || auth.isCustomerAdmin`), lazy fetch on first open, refetch on period change, framework summary cards (icon + score bar + rollup badge), per-framework control-card grids (icon, title, `chip` reference, RAG badge, score bar, 2-col metrics with tone colors, detail rows), snapshot timestamp. Status maps: badge `badge-low/medium/critical/info`, colors `#10b981/#f59e0b/#ef4444/#94a3b8`. **Customer dropdown** (admin/customer_admin only, appears only on Compliance tab): selects `customer_id`, refetches compliance on change, "All Customers" = no param (global scope).
- **Live findings surfaced by the report** (real state, not bugs): GDPR consent coverage **0%** (24 active users, none recorded consent), 19 dormant users (6 privileged) → access card non-compliant, retention coverage **83%** (`email_log` has no policy) + last retention run = never, CMDB stale 100%/orphan 22%, incidents+changes cards `not_assessed` for 30 d (demo tickets are older — switch period to 90).
- **Verified**: `php -l` clean; API admin 200 + full payload sanity; agent 403; `?customer_id=` scoping (3 tenants); icon registry check before build (46/31/0 unknown; dynamic names timer/check/database/search/users/siren/settings/info/boxes/shield/gauge all registered); build 1856 modules; deployed 44 up / 43 stale pruned; headless-Chrome check **14/15** (the one ✗ = test-selector flakiness; period-switch re-verified separately: "last 30 days" → "last 90 days" PASS, screenshot `/tmp/compliance-loaded.png`), **0 console errors**; puppeteer + all test scripts removed afterwards (`npm ls` empty, 0 refs in package.json/lock).
- **Build performance changed**: `npx vite build` took 1m6s this round and two earlier invocations appeared to hang >5-10 min with no output (killed; cause unknown — possibly cold esbuild/npm state after `npm prune`). Keep ≥10-min timeouts on builds; a completed build logs `✓ 1856 modules transformed`.

### Compliance checklists (2026-10-21) — GDPR + ISO 27001 + D-mærket, deployed and live-verified
- **New endpoint `GET /api/reports/checklists?customer_id=N`** (`ReportController::checklists()` + route with `['auth']`). Same access gate as compliance (admin/customer_admin, 403 for agents/users). Returns `frameworks[]` each with `items[]` (id, title, reference, description, category, status, evidence, hint), `stats` (covered/partial/outstanding/not_assessed/total), `score`, `status`.
- **`backend/services/ComplianceChecklist.php`** (new): code-defined checklist items with DB-backed evaluation. 3 frameworks, 30 items total:
  - **GDPR (10 items)**: Art. 30 CSI records, Art. 7 consent (gdpr_consent_log), Art. 5(1)(e) retention (data_retention_policies), Art. 17 erasure (erasure_requests), Art. 32 audit trail (audit_log), Art. 33 breach (incident tickets), Art. 35 DPIA (knowledge_articles), Art. 28 processors (static), Art. 29 training (knowledge_articles), Art. 37 DPO (not_assessed).
  - **ISO 27001:2022 (12 items)**: A.5.1 policies (knowledge_articles), A.5.9 asset inventory (ci_items), A.5.15 access control (roles), A.5.16 identity management (users), A.5.18 access rights review (dormant >90 d), A.8.1 endpoint devices (intune_devices), A.8.9 configuration management (ci_items owner coverage), A.8.13 backup (not_assessed), A.8.15 logging (audit_log), A.8.16 monitoring (audit_log 30 d), A.8.32 change management (change tickets), A.6.5 termination responsibilities (erasure/anonymization).
  - **D-mærket (8 items)**: ISM (knowledge_articles), risikovurdering (knowledge_articles), incident-håndtering (incident tickets), adgangsstyring (dormant users), logging (audit_log 30 d), backup (not_assessed), medarbejdertræning (knowledge_articles), leverandørstyring (not_assessed).
- **Status values**: `covered` (auto-detected), `partial` (exists but incomplete), `outstanding` (gap with actionable hint), `not_assessed` (requires external evidence). Each item has `evidence` (what the system found) and `hint` (how to close the gap, null when covered).
- **Rollup scoring**: score = (covered + partial×0.5) / assessed × 100. Rollup status: compliant (0 outstanding, 0 partial), partial (0 outstanding), non_compliant (>0 outstanding), not_assessed (0 assessed).
- **Frontend `ReportDashboardView.vue`**: new **Checklists** tab (admin/customer_admin only, next to Compliance). Framework selector buttons (GDPR / ISO 27001:2022 / D-mærket), framework header card (icon, score bar, stats legend with colored dots), per-item cards (status icon, title, `chip` reference, RAG badge, description, evidence line, blue "Sådan løses det" hint box for non-covered items). Customer dropdown shared with Compliance tab. Period selector hidden on Checklists tab (point-in-time, not period-based).
- **Schema notes discovered during build**: `tickets` has NO `deleted_at` (hard-delete); `ci_items` has NO `deleted_at` (uses `is_active`); `knowledge_articles` uses `status='published'` (NOT `is_published`); `knowledge_articles.body` (NOT `content`); `data_retention_policies.entity_type` (NOT `table_name`). All queries in ComplianceChecklist.php are written against the actual schema.
- **Live-verified**: backend 3 frameworks / 30 items returning correct evidence (e.g. 9 CSI entries → covered, 0/24 consent → partial with hint, 27 CI items → covered, 4/24 dormant → covered ≤20%). Frontend headless-Chrome check **14/14**, 0 console errors, 45 lucide icons rendered, customer_id filter verified. Icons added: `award`, `clock`, `help-circle` (registry now 49).

## Manual Devices (2026-10-21) — implemented, migrated, deployed, live-tested
- **Migration `021_manual_devices.sql`**: new table `manual_devices` (customer_id FK, name, device_type ENUM pc/mobile/printer/network/other, manufacturer, model, serial_number, operating_system, os_version, assigned_to FK users, location, ip_address, mac_address, notes, status, intune_device_id FK intune_devices nullable, created_at/updated_at). Customer-scoped, separate from Intune auto-sync.
- **`ManualDevice` model**: `find()`, `all()` (customer scope + filters), `create()`, `update()`, `delete()`, `linkToIntune()` for normalization. Whitelisted columns prevent mass-assignment.
- **`ManualDeviceController`**: full CRUD + filters endpoint. Tenant-gated via `Auth::canAccessCustomer()`.
- **Routes**: 6 endpoints under `/api/manual-devices` (index, filters, show, store, update, destroy) with `['auth']`.
- **Frontend `ManualDevicesView.vue`**: list with filters (customer, type, status, search), add/edit modal form with all fields, delete with confirm. Device type icons (monitor/smartphone/printer/wifi/box). Sidebar item `Manual Devices` with monitor icon. Route `/manual-devices`.
- **Design principle**: manual data is immutable — sync/normalization only links via `intune_device_id` but never overwrites manual records. Intune sync upserts into `intune_devices` only.
- **Live-verified**: API create/list/delete (1 created, 1 listed, deleted), filters returns 5 device types, frontend 9/9 tests passed, 0 console errors. Icons added: `pencil`, `trash-2` (registry now 51).
- Build was 2.3s this round (fast path after cache clear).

### Vendors / external hand-off (2026-11-03) — deployed, tested (38/38 vendor API, e2e 13/13 green)
- **Migration `022_vendors.sql`** (applied): `vendors` (customer_id FK, name, email, phone, api_base_url, integration_mode ENUM email/api/both, notes, is_active), `vendor_api_keys` (key_prefix, key_hash SHA-256 CHAR(64), last_used_at, revoked_at; one active key per vendor — new key revokes old), `tickets.vendor_id` (FK, SET NULL) + `external_ref` + `vendor_requested_at`, `ticket_comments.source` (`internal`/`system`/`vendor`).
- **Send to vendor**: `POST /api/tickets/{id}/vendor-request` (`VendorRequestController`), body `{vendor_id}` or `{email}` + `message`. Roles admin/agent/manager/customer_admin. Refused on resolved/closed/cancelled/rejected. Routing: `integration_mode` api → push JSON to `api_base_url/tickets` (502 on failure, ticket untouched); email → `MailService::sendVendorRequest` (subject `[<ticket_number>] title`, so replies match); both → API first, mail fallback. Success: status `awaiting_vendor`, internal system note in comments.
- **Vendor reply rules** (`services/VendorReply.php`, shared by API and mail): only `awaiting_vendor`/`in_progress` accept replies; any reply on awaiting_vendor → `in_progress`; vendor may set `in_progress` or `resolved` (resolved only from in_progress; sets resolution_date); comments stored `source='vendor'`, not internal.
- **Vendor API** `GET /api/v1/vendor/tickets`, `POST /api/v1/vendor/tickets/{ticket_number}/update` (`VendorApiController`): `Authorization: Bearer vk_…`, no session. A key only sees tickets with its vendor_id (other tenants → 404). Key management: `POST /api/vendors/{id}/api-key` (plain key shown once, 201), `DELETE /api/vendors/{id}/api-key`. Vendor CRUD: `/api/vendors` (admin/customer_admin write; deactivate = soft, also revokes key).
- **Inbound mail hardened**: `POST /api/email/receive` now **fails closed** — requires header `X-Inbound-Token` = `INBOUND_EMAIL_TOKEN` from `backend/.env` (set 2026-11-03). Any external forwarder must send this header. A status change happens only when the sender is the vendor's email or an address we mailed this ticket to (`email_log` outbound); other senders become ordinary comments.
- **SSRF guard**: `VendorController::checkApiUrl()` — https only, no localhost/.internal/private or reserved IP literals. DNS-rebinding not covered.
- **Frontend**: `components/VendorPanel.vue` on Incident/Request/Problem/Change detail (External ID field, Send-to-vendor modal, "Awaiting vendor" badge); `views/vendors/VendorsView.vue` at `/vendors` (sidebar, admin section; one-time key dialog). `awaiting_vendor` added to status badges and filter dropdowns of Change/Problem lists. `truck` icon added (registry now 52).
- **`.htaccess` change (root)**: `RewriteCond %{HTTP:Authorization} .+` → `E=HTTP_AUTHORIZATION` — Apache strips Authorization otherwise, which broke Bearer auth.
- **Tests**: `tests/vendor_api_test.py` (needs `INBOUND_EMAIL_TOKEN` env + the double `tests/vendor_mock.php` uploaded as `/vendor_mock.php`, deleted afterwards). `tests/email_e2e_test.py` now sends the token. Test mail to `vendor-test@flyingfish.dk` (not a real mailbox) bounces by design.
- **Open / not built**: outbound API calls carry no credentials (vendor side must accept our payload by policy or we add a per-vendor outbound token — needs a column); SLA keeps running while awaiting vendor (per decision); vendor-API has no webhook back to vendors on customer replies.
- **Build hang lesson**: `vite build` hung at "transforming…" repeatedly with 0% CPU; the cause was stale background vite/esbuild processes from earlier killed runs. Check `pgrep -fl "vite.js|esbuild"` before a build, kill leftovers, and use `nohup sh -c '…; echo EXIT $?' &` + polling. A clean build is ~2 s.

### Ticket categories (2026-11-04)
- **Migration `023_ticket_categories.sql`** (applied): `ticket_categories` (id, parent_id, name, applies_to, sort_order, is_active) with FK self-reference. 21 top-level categories, 85 subcategories, all customer-global. applies_to lists the ticket types a root serves (incident, service_request, problem, change). Problem uses the incident tree. No 'Other' bucket, per SERVIEW guidance.
- **`TicketCategory` model** (`backend/models/TicketCategory.php`): `tree(type)` returns nested roots→children; `validate(type, category, subcategory)` rejects unknown categories and mismatched pairs.
- **`TicketCategoryController`**: `GET /api/ticket-categories?type=...` (auth). `TicketController::store()` and `update()` validate the category pair (422 on invalid). Legacy free-text values are kept and do not block unrelated edits.
- **Frontend**: new `components/CategorySelect.vue` (two cascading dropdowns, preserves existing values as "(existing)"). Used on incident/request/problem/change create forms and edit modals; detail views show `Category › Subcategory`. Filter dropdowns still use free-text for backward compat.
- **Tests**: `tests/ticket_categories_test.py` — 15/15 PASS (tree shape, validation, legacy handling).
- **Build note**: `vite build` hangs in `~/Documents/OpenCode/OC ITSM/frontend` but succeeds in ~2 s from `/tmp` with `node_modules` symlinked. Build in `/tmp`, deploy `dist/` from there.

### Assignee display fix (2026-11-03)
- **Bug**: incident/request detail and list views read `assigned_to?.name`, but the API returns `assigned_to` as an id. Assignment saved (PUT 200) yet the field still showed "Unassigned". `Ticket::find()` now joins `assigned_to_name`; the four views use `assigned_to_name`. Verified live (assign → Carol Davis; restored to Unassigned).
- **Build workaround**: `vite build` inside `~/Documents/OpenCode/OC ITSM/frontend` hung at "transforming…" (no CPU). The same source builds in 2 s from `/tmp/<copy>` with `node_modules` symlinked to the project. Build there, then deploy `dist/` from the copy.

### Inventory menu item (2026-11-05)
- **Sidebar**: new `Inventory` entry under Administration, rendered as an external link (`<a target="_blank">`) to `/inventory/` (the standalone PC Inventory Agent app). Visible to admin and customer_admin. Icon: `boxes`.
- **No new route or view**: `/inventory/` is a separate application served from the web root; the ITSM SPA only links to it.

### Security scan (2026-11-23) — safe-anything, deployed and live-verified
- **Tool**: `safe-anything` (Rust, 72 rules, CWE + OWASP). Installed via cargo, ran on the full project (193 files, 187 packages checked against OSV.dev).
- **Findings fixed**:
  - `.gitignore`: added `.env` (was only `/backend/.env`, so root `.env` was not ignored). Risk: secrets could be committed.
  - `backend/core/Validator.php`: `unique()` interpolated `$table`/`$column` into SQL. Added whitelist of allowed tables and columns. These come from method arguments (not user input), but the scanner flagged them.
  - `frontend/src/views/knowledge/KnowledgeDetailView.vue`: `v-html="renderedBody"` rendered unsanitized HTML from article body. Added DOMPurify (`dompurify` package) to sanitize the rendered Markdown/HTML output.
- **Dependency CVEs**: 13 found (10 high, 3 moderate) in axios, vite, source-map-js, follow-redirects, postcss-selector-parser. `npm audit fix` reduced to 2 (esbuild/vite, dev-only). Remaining 2 require vite 8.x (breaking change) — left as-is since they're dev dependencies.
- **False positives**: ~50 SQL injection warnings in models/controllers. These interpolate hardcoded table/column names or parameterized scope fragments (from `Auth::customerScope()`), never user input. All queries use PDO prepared statements for user data.
- **Build**: `vite build` hangs in `~/Documents/OpenCode/OC ITSM/frontend` but works from `/tmp` with symlinked node_modules. Built in `/tmp/fe_build`, deployed via SFTP.

## Key Decisions
- **Migrated to one.com** (2026-11-06): entire system moved from `itsm.flyingfish.dk` (gigahost) to `itsm.governit.dk` (one.com). New DB credentials, SFTP access, updated all hardcoded URLs. Inventory app migrated with shared database. ITSM login session now persists user_id (session_write_close fix in AuthController::login and Auth::regenerateSession). Inventory app's itsm-auth.php reads session directly from /tmp/sess_* files.
- **Inventory app expanded**: shared navigation header (invNav) with customer selector for multi-customer users. All pages use consistent nav. Settings, reports, filters accessible from nav.
- **External suppliers** are a separate `vendors` table (tenant-scoped), not `organizations`. Vendor replies never change status except awaiting_vendor → in_progress (or → resolved from in_progress).
- **Display labels for enum values**: never render a raw DB value (`in_progress`, `customer_admin`, `email_reply`, table names) in a template. Use `formatLabel()` from `frontend/src/utils/format.js` (→ "In progress"). Backend stores keys; display text is the frontend's job. Done 2026-10-27 across all views, filter dropdowns, badges, charts and compliance cards. Compliance/checklist texts and retention labels in PHP use plain words, not table names.
- Tenant scoping centralized in `Auth::accessibleCustomerIds()` — injected as `AND customer_id IN (...)` into model queries; admins get `1=1`.
- `customer_user` pivot table handles agent/customer_admin multi-customer assignments; users get single `customer_id` column as primary customer.
- CSI entries are customer-scoped (`customer_id` on `csi_register`).
- **Reporter** = person logging the ticket (`reported_by`), **Requester** = person who needs it resolved (`requester_id`). Both can be overridden by agents/admin; defaults to logged-in user.
- Year Wheel: auto-rollover for recurring tasks (new instance created automatically in target year), supports single-date and multi-month durations, SVG-based circular wheel as default view, customer-scoped with admin dropdown.
- YearWheelController methods use `Request $request` (matching all other controllers) not `array $params`.
- Categories are global (`customer_id = NULL`) shared across all customers; tasks are per-customer.
- `Response::success()` returns `{success: true, message: "Success", data: [...]}`. Axios interceptor unwraps `response.data`.
- **Always `php -l` a file locally before FTP-upload.** Run `for f in $(find backend -name "*.php"); do php -l "$f"; done` after any edit round.
- **When a model method is type-hinted, cast/validate route params.** `Router::pathToRegex()` maps `{id}` to `[^/]+`, so any string reaches the controller.
- **Never interpolate a "no restriction" scope as a bare `1=1` into SQL that already has a `WHERE`.** Return an empty fragment.
- Creating a user via `POST /api/users` requires `password_confirmation`; deleting a user anonymises the email to `deleted_<id>@redacted.local` and sets `deleted_at` (login correctly rejects it). Row count does not decrease.

## Next Steps
1. ~~Disable the SSO sign-in button/flow~~ — done 2026-09-28 (dead code removed).
2. ~~Remove hardcoded DB fallbacks from `database.php`~~ — done 2026-09-28 (`.env` was already present; fallbacks gone).
3. ~~Email-to-ticket end to end~~ — done 2026-09-28 (`tests/email_e2e_test.py`, 13/13; reply-match + new-ticket-from-email + `email_log` growth 10→15).
4. ~~Verify SMTP on the hosting, or move to SendGrid/Mailgun for deliverability~~ — done 2026-10-21 (PHP `mail()` verified: test delivered to `bay@flyingfish.dk`; SPF correct; `sb@governit.dk` likely filtered by external provider — spot-check external deliveries in production).
5. ~~Populate `fields` on service-catalog items~~ — done 2026-09-28 (migration `020`, items 4 + 6).
6. ~~Add a `fields`-aware request/response test~~ — done 2026-09-28 (`tests/fields_api_test.py`, 12/12).
7. ~~Polish from the icon round~~ — done 2026-09-28 (self-hosted fonts, breadcrumb map, catch-all route, `ci_types.icon` in CMDB); see "Polish round".

## Critical Context
- **Production URL**: `https://itsm.governit.dk` (migrated from `https://itsm.governit.dk` on 2026-11-06).
- **SFTP**: `sftp.governit.dk`, user `ckozkl4kj_ssh`, pass `SendFilerSecure!110626`. Webroot: `/customers/d/b/9/ckozkl4kj/webroots/a66b3e17` (symlinked via `webroots/by-route/itsm.governit.dk_`). Document root is `public/`.
- **Database**: `ckozkl4kj_itsm` on `ckozkl4kj.mysql.service.one.com:3306`, user `ckozkl4kj_itsm`, pass `SolenSkinner26!`. MariaDB 10.11. Inventory app shares the same database (inv_* tables).
- **PHP**: 8.5.12 on one.com. `exec()`/`shell_exec()` disabled. Use PHP scripts for DB operations.
- **one.com structure**: root `.htaccess` rewrites to `public/`. Backend code (config/, core/, etc.) is at the webroot root, outside `public/`. `public/.htaccess` handles API routing and SPA fallback. `public/index.php` is the entry point.
- **Build note**: `vite build` hangs in `~/Documents/OpenCode/OC ITSM/frontend` but succeeds in ~2 s from `/tmp` with `node_modules` symlinked. Build in `/tmp`, deploy `dist/` from there.
- **`backend/.env`** holds `INBOUND_EMAIL_TOKEN` (inbound mail gate). Rotate by editing that file over FTP; never commit it.
- `backend/public/index.php` uses `spl_autoload_register` only for `controllers/`, `models/` and `services/`; other `services/` files must be `require_once`-d explicitly.
- **The FTP login root IS the web root.** A `public_html/` directory also exists but is a decoy and is not served — do not upload into it.
- **To run a one-off script** on one.com, upload it to `public/` (or `public/inventory/`) and request `https://itsm.governit.dk/<file>.php`. PHP files in `public/backend/inventory/` are blocked by .htaccess (Require all denied). Any URL that is not a real file falls through to the SPA `index.html` (HTTP 200, **text/html**) — check `Content-Type` before trusting a status code.
- **FTP `DELE` must be issued from the login root with a full relative path** (`DELE assets/foo.js`). After `CWD` into a directory the server mis-resolves the path and returns `550 Could not delete ... No such file or directory` even though `LIST` shows the file. For bulk work use `python3` + `ftplib` on a single connection — `curl` with many `-Q DELE` is awkward (and macOS bash 3.2 has no `mapfile`).
- `Database` has `query()` — there is **no** `Database::execute()`. The pivot table is `customer_user`, not `user_customer`.
- `audit-log` routes are `/api/audit-logs` (plural) and filter on `table_name` / `action` / `user_id` — not `entity_type` / `entity_id`.
- **Old server (gigahost) — decommissioned**: `web12.gigahost.dk` / `itsm.flyingfish.dk` / `Upfw4KSO7vt6MM6HRNSm`. DB `sbay_itsm` / `sbay` / `Skyservice17!`. Files can be deleted once migration is confirmed.
- Node.js is installed locally (Node 26, npm 11). Vite builds now take ~13 min.
- `Pagination.php`, `SlaCalculator.php`, `PriorityMatrix.php` live in `backend/helpers/`. `RateLimiter.php`, `Env.php`, `Logger.php`, `Validator.php` are in `backend/core/`.
- `Pagination::params()` returns `[$page, $perPage, $offset]` and reads `PAGINATION_LIMIT`, so standalone scripts must define it.
- Soft-delete: all models filter `deleted_at IS NULL` **except `tickets`, which has no `deleted_at` column and is hard-deleted.**
- Root `.htaccess`: `RewriteRule ^api/ index.php [L]`, then root `index.php` requires `backend/public/index.php` for `/api` URIs.
- **IMPORTANT**: Server has TWO `api.php` files: `/routes/api.php` (root, unused) and `/backend/routes/api.php` (actually used). Always upload routes to `/backend/routes/api.php`.
- Year Wheel API now returns data correctly.
- `sb@governit.dk` password: `Skyservice20!` (updated 2026-11-06 on one.com), role: admin.
- Demo users (alice/bob/carol@democorp.com, dave/eve/frank@testorg.com): password `demo1234`.
- Catalog items with `requires_approval=1`: id 4 (Hardware Request) and id 6 (VPN Access). Both have `approval_chain = NULL`, so they fall back to a single `role=manager` step.
- Managers available in GovernIT (customer 1) for approval testing: ids 3, 46, 48, 49. Passwords unknown — create a throwaway manager via `POST /api/users` and delete it afterwards rather than resetting real accounts.

## Relevant Files
- **New server**: one.com, SFTP `sftp.governit.dk` / `ckozkl4kj_ssh` / `SendFilerSecure!110626`. DB `ckozkl4kj_itsm` / `ckozkl4kj_itsm` / `SolenSkinner26!` on `ckozkl4kj.mysql.service.one.com:3306`.
- **Webroot**: `/customers/d/b/9/ckozkl4kj/webroots/a66b3e17` (symlinked via `webroots/by-route/itsm.governit.dk_`). Document root is `public/`.
- `backend/migrations/023_ticket_categories.sql` (new): `ticket_categories` table + seed (21 roots, 85 subcategories).
- `backend/models/TicketCategory.php`, `backend/controllers/TicketCategoryController.php` (new): tree + validation.
- `backend/controllers/TicketController.php`: validates category/subcategory on store + update.
- `frontend/src/components/CategorySelect.vue` (new): cascading dropdowns, preserves legacy values.
- `tests/ticket_categories_test.py` (new): 15/15 PASS.
- `backend/migrations/022_vendors.sql`: vendors, vendor_api_keys, tickets.vendor_id/external_ref/vendor_requested_at, ticket_comments.source (applied).
- `backend/models/Vendor.php`, `backend/controllers/VendorController.php`: CRUD, API keys (hash only), SSRF URL check.
- `backend/controllers/VendorRequestController.php`: send-to-vendor (API/mail routing, awaiting_vendor).
- `backend/controllers/VendorApiController.php`, `backend/services/VendorReply.php`: vendor-side API and shared reply rules.
- `backend/services/MailService.php`: `sendVendorRequest()`, template `vendor_request`, `sendWithSettings()` returns bool.
- `backend/controllers/EmailController.php`: token gate + vendor-reply branch + `isVendorSender()`.
- `frontend/src/components/VendorPanel.vue`, `frontend/src/views/vendors/VendorsView.vue`.
- `tests/vendor_api_test.py`, `tests/vendor_mock.php`.
- `backend/models/Ticket.php`: customer-scoped `find`/`findByNumber`, `INTERNAL_NOTE_ROLES`, `canSeeInternalNotes()`, single audit row in `delete()`.
- `backend/controllers/TicketController.php`: `(int)` id casts, dynamic-field merge, internal-note 403 gate, no duplicate `Logger::log()`.
- `backend/controllers/ReportController.php`: `customerCondition()` returns an **empty** fragment for admins (was `'1=1'` → SQL syntax error).
- `backend/core/Validator.php`: added `regex()` (with optional `$message`) and `matches()`.
- `backend/controllers/UserController.php`: `store()` column whitelist + `password_confirmation` required; `update()` session regeneration.
- `backend/core/Request.php`: content-type aware JSON decode with a `{`/`[` sniff fallback.
- `backend/public/index.php`: `catch (Throwable $e)` so `TypeError` from bad route params returns 500 JSON, not a fatal.
- `.htaccess` + `assets/.htaccess`: cache-control headers (`no-cache` shell, `immutable` assets).
- `frontend/src/stores/auth.js`: `canSeeInternalNotes` getter.
- `frontend/src/views/requests/RequestFormView.vue`: sends `type_data.dynamic_fields`.
- `frontend/src/views/requests/RequestDetailView.vue`: `dynamicAnswers` + `catalogFields()` (handles JSON-string `fields`).
- `backend/models/Approval.php`: customer-scoped `find`/`findByTicket`, `isCurrentStep()` ordering gate, `guardDecision()` (status → self-approval → order → authority), `hasAuthority()`, reject-skips-later-steps, `create()` auto-fills `customer_id`.
- `backend/controllers/ApprovalController.php`: thin controller; `resolveDecidable()` delegates to `Approval::guardDecision()`.
- `backend/controllers/ServiceRequestController.php`: legacy approve/reject now funnels through the same `resolvePendingStep()` + `guardDecision()` (previously a second, weaker `canDecide()` copy).
- `backend/controllers/TicketController.php`: K1 catalog-item normalization feeding `tickets`, `service_requests` and `Approval::generateForServiceRequest()`.
- `backend/models/ServiceRequest.php`: now customer-scoped via `Auth::customerScope('t')` (was unscoped — the legacy route leaked existence cross-tenant).
- `backend/models/User.php`: `password` unset in `find()`, `all()` and `getCustomerUsers()`; parse error in `getCustomerUsers()` removed.
- `backend/migrations/018_approval_customer_scope.sql`: `approvals.customer_id` + index + FK + backfill; applied to the server.
- `frontend/src/components/ApprovalFlow.vue`: mirrors the server guard — disabled non-active steps, "Blocked — waiting for step N" hint, self-approval hidden.
- `frontend/src/views/requests/RequestFormView.vue`: sends `service_catalog_item_id` (K1).
- `frontend/src/views/changes/ChangeFormView.vue`: `customer_admin` added to the approval-role dropdown; sends `type_data.approval_chain`.
- `backend/controllers/YearWheelController.php`: fixed to use `Request $request`; has audit logging; all 11 CRUD + rollover methods.
- `backend/models/YearWheelTask.php`: customer-scoped, soft-delete, null-safe `canAccessCustomer()`, rollover logic.
- `backend/models/YearWheelCategory.php`: customer-scoped, null-safe `canAccessCustomer()`, hard delete.
- `backend/migrations/015_year_wheel.sql`: created `yw_categories` and `yw_tasks` tables with default category seed data (migrated).
- `backend/migrations/015_year_wheel.sql`: created `yw_categories` and `yw_tasks` tables with default category seed data (migrated).
- `backend/migrations/019_tasks_module.sql`: `yw_tasks.show_in_wheel` + 2 indexes, nullable `start_date`, "Tasks" category (id 7); applied to the server.
- `backend/models/YearWheelTask.php`: now customer-scoped `find()` (was a cross-tenant read/update/delete hole), `show_in_wheel` in `create()`/`update()`, `show_in_wheel`/`priority` filters, nulls sort last, `completed_at` cleared on re-open, rollover limited to wheel tasks.
- `backend/controllers/YearWheelController.php`: `wheel_only`/`priority` query filters, `start_date` optional for non-wheel tasks, wheel-date invariant enforced on POST and PUT.
- `frontend/src/views/tasks/TaskListView.vue`: the new **Tasks** page (My/All toggle, filters, counters, inline status + assignee, create/edit modal).
- `frontend/src/views/yearwheel/YearWheelTaskForm.vue`: "Show in Year Wheel" toggle, conditional required start date, error banner; reused by the Tasks page with `default-show-in-wheel=false`.
- `frontend/src/views/yearwheel/YearWheelView.vue`: sends `wheel_only: 1` so off-wheel tasks never reach the wheel views.
- `frontend/src/router/index.js` + `frontend/src/components/layout/AppSidebar.vue`: `/tasks` route and the `✅ Tasks` nav item.
- `backend/routes/api.php`: 11 year-wheel routes (categories + tasks + rollover), all with `['auth']` middleware. Located at `/backend/routes/api.php` on server.
- `backend/core/Response.php`: `success()` returns `{success: true, message: "Success", data: [...]}`.
- `backend/core/Response.php`: `success()` now honours a lone int 2nd argument as the HTTP status (`func_num_args() === 2 && is_int($message)`); `string|int $message`.
- `backend/models/CiItem.php` + `backend/controllers/CiController.php`: `find()` scoped via `Auth::customerScope('ci')`; destroy/relate/unrelate/auditLog/setCiTags gated on scoped `find()`.
- `backend/models/CsiItem.php`: `find()` scoped via `Auth::customerScope('c')` (all `CsiController` operations gate through it).
- `backend/models/SlaContract.php`: `getComplianceReport()` ticket counts scoped via `Auth::customerScope('t')`; `sla_contracts` itself has no `customer_id` (global per-priority templates).
- `frontend/src/views/yearwheel/YearWheelView.vue`: main view with customer dropdown for admins, year selector, 4 view modes (wheel/table/list/calendar), create/edit/detail modals.
- `frontend/src/views/yearwheel/YearWheelCircular.vue`: SVG-based circular wheel with category arcs and month rings.
- `frontend/src/views/yearwheel/YearWheelTable.vue`: Gantt-like table with duration bars.
- `frontend/src/views/yearwheel/YearWheelList.vue`: grouped by category.
- `frontend/src/views/yearwheel/YearWheelCalendar.vue`: 12-column month grid.
- `frontend/src/views/yearwheel/YearWheelTaskForm.vue`: create/edit form with category, assignee, dates, recurring toggle; passes `customer_id` from parent.
- `frontend/src/views/yearwheel/YearWheelTaskDetail.vue`: detail modal with subtasks.
- `frontend/src/router/index.js`: route `/year-wheel`.
- `frontend/src/components/layout/AppSidebar.vue`: 🔄 Year Wheel link.
- `backend/core/Router.php`: `dispatch()` matches routes by method + regex; auth middleware returns 401 on failure; 404 if no route matches.
- `backend/core/Request.php`: `query()` reads `$_GET`, `param()` reads route params, `all()` reads POST body.
- `backend/config/session.php`: `SESSION_SAME_SITE = 'Strict'`, `SESSION_SECURE = true`, `SESSION_DOMAIN = ''`.
- `backend/core/Auth.php`: new security version deployed — password stripped from responses, `is_active=1` required, account lockout via `failed_attempts`/`locked_until`, `session.php` config included.
- `backend/core/Env.php`: `.env` loader — was missing from server, uploaded and working.
- `backend/core/RateLimiter.php`: file-based rate limiter — was missing from server, uploaded and working.
- OPCACHE ISSUE RESOLVED: PHP upgrade from 8.0.28 to 8.1.18 caused PHP-FPM restart, clearing the stale opcache. All future uploads take effect immediately.
- `.env` file is on the server at `/home/www/itsm.governit.dk/.env` — stores DB credentials.
- `frontend/src/utils/icons.js`: icon registry + `yw_categories.icon` emoji→icon map (new).
- `frontend/src/components/common/AppIcon.vue`: icon wrapper (`:size`, stroke 1.5, fallback `circle`) (new).
- `frontend/public/favicon.svg`: OC-monogram favicon (new, was 404).
- `frontend/package.json`: added `lucide-vue-next` (puppeteer-core NOT a dependency — only used `--no-save` for verification, since removed).
- `frontend/index.html`: Google Fonts `<link>`s removed (polish round).
- `frontend/src/assets/main.css`: prepended 4 self-hosted `@font-face` blocks pointing at `/fonts/*.woff2`.
- `frontend/public/fonts/`: 4 variable woff2 files (Inter + JetBrains Mono, latin + latin-ext).
- `frontend/src/router/index.js`: catch-all `{ path: '/:pathMatch(.*)*', redirect: '/' }` (last route).
- `frontend/src/components/layout/AppHeader.vue`: `pageTitle` map now covers every header-rendered route.
- `frontend/src/views/cmdb/CiListView.vue` + `CiDetailView.vue`: `ci_type_icon` rendered as `<AppIcon :size="14">` inside the type badge.
- `frontend/src/utils/icons.js`: +9 ci-type icons; `cube` maps to Lucide `Box` (there is no `Cube` export).
- `backend/config/database.php`: Env-driven only — `require_once core/Env.php`, no DB credential fallbacks (remaining-items round).
- `backend/migrations/020_service_catalog_fields.sql`: `service_catalog.fields` JSON column + 3-field seeds for items 4/6; applied to the server (one-shot, MySQL 5.7).
- `backend/controllers/TicketController.php`: `show()` attaches `catalog_item` (with `fields`) to the detail payload.
- `backend/services/MailService.php`: `extractTicketNumber()` fixed — returns full bracketed token (`SR-26-0012`), not just the type group (was breaking subject-based reply matching).
- `frontend/src/components/layout/AppHeader.vue`: breadcrumb values use `/create` (static segment wins over `:id`; `/new` 404s).
- `frontend/src/views/auth/LoginView.vue`: SSO button/flow removed (dead code).
- `tests/fields_api_test.py` + `tests/email_e2e_test.py`: stdlib-python API tests (12/12 and 13/13); login via JSON, no CSRF middleware active.
- `backend/controllers/ReportController.php`: **compliance section** — `compliance()` entry + `scopeIds`/`tenantCol`/`userCol`/`usersCol` scope helpers + 9 card builders (dsr, consent, retention, accountability, access, incident, change, logging, cmdb) + `framework()` rollup + `card()`/`pctStr()` formatting; **checklists section** — `checklists()` entry delegating to ComplianceChecklist; earlier `customerCondition()` returns an **empty** fragment for admins (was `'1=1'` → SQL syntax error).
- `backend/services/ComplianceChecklist.php` (new): framework checklist definitions (GDPR 10, ISO 12, D-mærket 8 items) with DB-backed evaluation closures, `scalar()` helper, `item()`/`check()`/`framework()` builders; score formula `(covered + partial×0.5) / assessed × 100`.
- `backend/migrations/021_manual_devices.sql` (new): `manual_devices` table; applied to server.
- `backend/models/ManualDevice.php` (new): CRUD + customer scope + filters.
- `backend/controllers/ManualDeviceController.php` (new): full CRUD + filters.
- `frontend/src/views/intune/ManualDevicesView.vue` (new): list + add/edit modal form.
- `frontend/src/utils/icons.js`: +2 icons (pencil, trash-2), registry now 51.
- `backend/routes/api.php`: `GET /reports/compliance` + `GET /reports/checklists` (auth-only; controller gates admin/customer_admin).
- `frontend/src/views/reports/ReportDashboardView.vue`: Overview/Compliance/Checklists tabs + compliance framework/control cards (status maps STATUS_LABELS/BADGES/COLORS, lazy `fetchCompliance`/`fetchChecklists`, period refetch, customer dropdown shared between Compliance and Checklists).
