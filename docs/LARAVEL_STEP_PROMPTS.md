# M-Fixpro POS → Laravel + MySQL: Step-by-Step Prompts

How to use this file:
1. Put `LARAVEL_REBUILD_PROMPT.md` (the full spec) in the new Laravel project at `docs/SPEC.md`, so your AI tool can read it at every step.
2. Paste the prompts below **one at a time, in order**. Don't start the next step until the current one works in the browser.
3. After each step, commit to git (`git add . && git commit -m "Step N"`), so you can roll back if a later step breaks something.
4. If the AI tool can't read files, paste the spec sections named in each step under the prompt.

---

## Step 1: Project setup

```
We are rebuilding an existing app called "M-Fixpro POS" in Laravel 11 + MySQL 8.
The full specification is in docs/SPEC.md. Read the whole file first. Every later
step refers to its sections (§1–§11). Follow it exactly. Don't simplify or rename anything.

In this step, only do the project setup (SPEC §1 and §2):
1. Configure MySQL in .env (database "mfixpro", utf8mb4).
2. Install and configure Vite + Tailwind CSS 3 + Alpine.js 3.
3. Install the blade lucide icons package (mallardduck/blade-lucide-icons).
4. Put the exact Tailwind theme from SPEC §2.1 in tailwind.config.js (content paths: resources/**/*.blade.php and resources/**/*.js).
5. Put the exact CSS from SPEC §2.2 in resources/css/app.css (fonts, .nexora-card, .nexora-input,
   .nexora-btn-*, .badge-*, scrollbars, print CSS).
6. Add .env keys: MAIL_*, TURNSTILE_SITE_KEY, TURNSTILE_SECRET_KEY, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD.
7. Create a test page at /style-test that shows every button, input, card and badge style, so I can check the design.

Done when: `npm run dev` + `php artisan serve` work and /style-test looks correct.
```

---

## Step 2: Database (all migrations, models, seeders)

```
Read docs/SPEC.md §5 (Database schema) and §6 (Document numbering).

1. Create a migration for EVERY table in §5 with exactly those columns, types, defaults,
   foreign keys and the indexes listed. Money = DECIMAL(12,2). JSON columns as JSON.
2. Create an Eloquent model for each table, with $fillable, $casts (json → array,
   decimals → 'decimal:2', dates → datetime, booleans) and relationships
   (Product hasMany ProductBatch/ProductUnit, Sale hasMany SaleItem, Job hasMany JobStatusHistory,
   Grn hasMany GrnItem, StockTransfer hasMany StockTransferItem, StockOut hasMany StockOutItem,
   SubCategory belongsTo MainCategory, etc.).
3. Create app/Services/Numbering.php with a method next(string $counterName, string $prefix): string
   that must be called inside DB::transaction, locks the counters row with lockForUpdate(),
   increments it and returns e.g. "INV-00001" (5-digit zero padding). Also add
   Numbering::previewJobNo() and support for custom job numbers exactly as in §6.
4. Seeder: Super Admin user from .env, shop_settings row (name "M-Fixpro"), all counters = 0.

Done when: `php artisan migrate:fresh --seed` runs with no errors.
```

---

## Step 3: Roles & permissions

```
Read docs/SPEC.md §4 (Users, roles & permissions).

1. Create app/Support/Permissions.php containing:
   - the full PERMISSION_CATALOG (key, label, module) for all keys in §4.2
   - EDITABLE_ROLES, role default maps exactly as §4.3 (salary.view NOT default)
   - defaults($role), can(User $u, $key) exactly as in §4.4
   - canManagePermissionsFor($viewerRole, $targetRole) (Super Admin → any editable role;
     Admin → only Manager/Cashier/Technician/Staff).
2. Register Gates in AppServiceProvider: Gate::before returns true for Super Admin;
   Gate::define for every permission key using Permissions::can().
3. Middleware "active": if the logged-in user's status is inactive, log them out and redirect
   to login with "Your account has been disabled".
4. A Blade component <x-access-restricted message="..."/> (lock icon, "Access Restricted" title)
   as described in §2.3.
5. Write PHPUnit tests for the default permissions of each role and for canManagePermissionsFor.

Done when: tests pass.
```

---

## Step 4: Authentication (login, forgot/reset password with OTP, Turnstile)

```
Read docs/SPEC.md §8.1 (Login) and §9 (Emails, items 1 and 2).

Build /auth/login exactly as described: gradient background, centred rounded-3xl card,
red left brand panel (white logo /shop_logo/logo-white.png, "Welcome to {shop name}",
underline bar, tagline), right side form with 3 modes in one page using Alpine.js:
Sign in / Forgot password / Reset password.

- Session login with email + password + Cloudflare Turnstile widget (verify server-side).
  Refuse inactive users.
- Forgot password: 6-digit OTP, 10 min expiry, 60 s resend cooldown, stored in
  password_reset_otps, emailed with a branded template. Always return the same generic success.
- Reset password: code + new password (min 6) + confirm + Turnstile; max 5 wrong attempts;
  exact error messages from §8.1.
- Logout route. "/" redirects to /dashboard or /auth/login.
- Emails have HTML + plain-text versions and use the shop name from shop_settings.

Done when: I can log in as the seeded Super Admin, log out, and reset a password via email.
```

---

## Step 5: App layout (sidebar, header, shared components)

```
Read docs/SPEC.md §2.3 (UI conventions) and §3 (App layout).

1. Create layouts/app.blade.php: sidebar + header + main content area, full height (h-[100dvh]).
2. Sidebar exactly as §3: logo, nav with the exact items, icons, groups and permission keys,
   collapsible groups (Alpine, animated with grid-rows trick, rotating chevron, dashed left border),
   active-link styles, auto-expand the group of the current page, hide links the user can't access
   and hide empty groups, user card with initials avatar linking to /profile, Sign out,
   footer "© {year} {shop}" + "Design & Developed by plexCode".
   Mobile: off-canvas drawer with black overlay and slide animation.
3. Header: red on mobile with hamburger + shop name, white on desktop; live clock (updates every second).
4. Page title "{shop name} POS".
5. Reusable Blade components: <x-modal>, <x-confirm-dialog>, <x-pagination> (10 per page,
   "Showing x–y of z"), <x-search-input>, <x-date-range>, <x-searchable-select>, <x-badge>,
   <x-page-header title subtitle> with an actions slot.
6. Create empty placeholder pages for every route in the sidebar, each protected by
   auth + active + its can: permission (show <x-access-restricted> when missing).

Done when: I can click every menu item and see its placeholder page, on desktop and mobile.
```

---

## Step 6: Settings, Team & My Profile

```
Read docs/SPEC.md §8.22 (Settings) and §8.23 (My Profile).

Build /settings:
- Shop Info card (name*, phone*, email, address). Editable only by Admin/Super Admin.
- Low Stock Alerts card: add/remove recipient emails (stored in shop_settings.notify_emails).
- Team: list, Add User modal, Edit User modal with the permission toggles grouped by module
  (from Permissions catalog), Active/Inactive, "Reset to role defaults", Delete User.
  Apply exactly the visibility and manage rules in §4.1 and §8.22 on BOTH the UI and the server.
  New users get the full default permission map for their role.
- Database Usage card (row count per table).
- Data tools (Super Admin only): export table as CSV/JSON, clean table with typed confirmation.

Build /profile: Profile Info (name, phone), Change Email (current password + verification link
emailed to the new address, change applied only after clicking it), Change Password.

Done when: I can create a Cashier, remove one of their permissions, log in as them and see the
menu item disappear.
```

---

## Step 7: Catalogue (Brands, Categories, Services, Customers)

```
Read docs/SPEC.md §8.16 (Brands & Categories), §8.8 (Services), §8.17 (Customers).

Build these CRUD pages with the exact UI, labels, placeholders and permissions described:
- /brands (add/edit modal, delete needs brands.delete)
- /categories (main categories with their sub-categories, add/edit/delete, categories.delete)
- /services (table, search, add/edit modal with the custom-fields builder:
  label, type text/number/long text/checkbox/dropdown/date, required, placeholder, options;
  services.edit / services.delete)
- /customers (prefix search on name/phone, paginated table, add/edit modal, loyalty points column)
- JSON endpoint GET /api/customers/search?q= (min 2 chars, max 8 results), used later by POS and Jobs.

Done when: all four pages work end to end.
```

---

## Step 8: Products, batches & serial numbers

```
Read docs/SPEC.md §7 (Inventory model) and §8.11 (Products).

1. Create app/Services/StockService.php with:
   - allocateFifo(productId, location, qty, preferredBatchId = null) exactly as §7
     (locks batches with lockForUpdate, oldest received_at first, throws the exact error message)
   - helpers to change stock counters (total_stock + stores_stock/showroom_stock always in sync)
   - recordMovement(...) that writes stock_movements rows
2. Build /products: search (name/SKU/barcode), filters, table with the exact columns
   (stock shows total + "Stores X · Showroom Y"), Add/Edit Product modal (Product Details +
   Pricing & Stock sections; initial stock creates the first batch in Stores; serial products
   ask for one serial per unit), Stock Batches modal (list, edit batch with adjustment movement,
   add batch), Serial Numbers modal (list, edit serial, add more serials, delete in-stock unit only),
   delete product. Enforce products.edit / products.delete / products.batch.edit / products.unit.delete.
3. Restocking always sets low_stock_alerted = false.

Done when: I can create a normal product and a serial product with stock, and the batches/serials
modals show correct numbers.
```

---

## Step 9: Suppliers & GRN

```
Read docs/SPEC.md §8.18 (Suppliers) and §8.12 (GRN).

1. /suppliers: outstanding summary, main table, Add Supplier, Edit contact, supplier view modal
   with Record Payment (validation: > 0 and ≤ balance), Payment History, Edit payment
   (recalculates balances, rejects overpaying, writes audit log), Send account statement email.
   Payment status rule: balance ≤ 0 → paid; balance ≥ total_payable → outstanding; else partial.
2. /suppliers/payments: Supplier Payment Report (payments list + Outstanding Balances) + CSV export.
3. /grn list + /grn/new page + view modal + admin edit, all in one DB transaction as §8.12
   (GRN-number, batches, units, counters, movements, supplier payable update).
4. Create app/Services/AuditLogger.php: diff(before, patch, allowedFields) and
   write(collection, docId, label, changes, user). Only writes when something changed.
   Use it for every "admin edit" from now on.

Done when: a GRN for a supplier adds stock to Stores and increases what we owe that supplier,
and paying the supplier reduces it.
```

---

## Step 10: Stock Transfer, Stock Out, Stock Movements

```
Read docs/SPEC.md §8.13, §8.14, §8.15.

1. /stock-transfer list + /stock-transfer/new + view + admin edit (note only). Stores → Showroom,
   FIFO from Stores, one new Showroom batch per consumed Stores batch (same cost/price,
   source_batch_id), serial units change location. TRF- numbers. One transaction.
2. /stock-out list + /stock-out/new + view + admin edit (recipient, reason, detail, note, job only).
   Issue From Showroom/Stores, Issued To, Reason (Job/Repair with job picker, Sale, Other).
   FIFO or serial units → status issued. SO- numbers. (The job picker can use a simple job search
   endpoint; the Jobs module comes in Step 12.)
3. /stock-movements read-only list with filters, type badges, +/− coloured qty, location or
   "Stores → Showroom", clickable reference numbers, CSV export.

Done when: I can transfer stock to Showroom, issue stock out, and see all of it in Stock Movements
with correct product counters.
```

---

## Step 11: Shifts + POS / New Sale (the most important step)

```
Read docs/SPEC.md §8.4 (POS) very carefully, plus §8.6 (A4 Bill print) and §9 item 3 (bill email).
Leave out the "Find Job to Bill" part for now (next step), but design the code so it can be added.

1. app/Services/ShiftService.php: open (one open shift per cashier, float ≥ 0, SHIFT- number),
   close (only the owner; expected = float + cash sales − cash expenses; variance; review pending).
2. app/Services/SaleService.php: create() doing EVERYTHING in the checkout transaction list of
   §8.4 (steps 1–12): shift check, unit checks, showroom capacity message, FIFO, INV- number,
   sale + items with real cost and batch_allocations, shift totals per payment leg,
   stock/batches/units, movements, warranties, low-stock flag, loyalty points.
   After commit, send the low-stock email if needed.
3. /sales page: exact two-column layout, shift pill + Open/Close/Shift Closed modals,
   Products/Services tabs, search with barcode Enter, category filters, product grid (active +
   showroom stock > 0 only), batch picker (Auto FIFO + batches + "Selling at a loss"),
   serial picker, service modal with custom fields, cart lines (qty, discount, remove),
   customer picker + New Customer, bill discount, redeem points, payment buttons,
   split payments with Balanced/Remaining/Over, cash tendered/change,
   Card/KokoPay % surcharge EXACTLY as the formulas in §8.4, points preview,
   Checkout button rules, Sale Complete modal.
   Build the cart as one Alpine.js component that talks to JSON endpoints.
4. Bill print Blade partial with id="bill-print", matching §8.6 exactly (including the
   warranty terms text). Buttons: Print (window.print), Download (html2canvas + jsPDF A4,
   multi-page), Email (send PDF base64 + sale id; server reloads the sale and emails the customer).

Test these cases and show me the results:
- cash sale, split Cash+Card, Card 3% alone, Card 3% in a split, KokoPay 5%
- selling more than showroom stock (error message)
- serial product sale creates one warranty per serial
- loyalty points earned and redeemed
- closing the shift gives the correct expected cash and variance
```

---

## Step 12: Repair Jobs + billing jobs at the POS

```
Read docs/SPEC.md §8.7 (Jobs), the "Find Job to Bill" part of §8.4, and §9 item 4.

1. /jobs page: status filter/chips, date filters, server search (job no incl. "123" → JOB-00123,
   name prefix, phone prefix), paginated table with the exact status labels, badges and dots.
2. New Job Note modal with ALL fields in §8.7: job no preview/custom, customer search or new
   (auto-create customer), device type buttons + Other, device parts editor with the quick-add
   chips per device type, fault, accessories, physical condition, special notes, technician,
   services & charges (paid/free + reason), estimated cost, advance, expected delivery date.
   Save creates the job (pending) + first history row "Job received".
3. Job view modal: details, Print A4 (id="job-print") / Download, Job History timeline,
   Update Job Status (repair cost + "Use services total" for Job Done), email prompts,
   Edit Job (jobs.edit, audit logged), Export Report CSV with full history.
4. POS: add "Find Job to Bill" (only status done), attach the job, show its billable lines using
   the jobBillableServices rules in §8.4 (other repair charges / adjustment / less advance paid),
   apply surcharges to paid job lines, store services on the sale, and after checkout set the job
   to delivered with the note "Delivered & billed via POS — Invoice INV-xxxxx".

Done when: I can take in a job, move it to Job Done with a repair cost, bill it at the POS with
an advance deducted, and it becomes Delivered.
```

---

## Step 13: Bills, Quotations, Warranty

```
Read docs/SPEC.md §8.5 (Bills), §8.9 (Quotations), §8.10 (Warranty).

1. /bills: list with date range/search/pagination, view modal (reprint/download/email),
   Edit Bill (only customer name/phone/email, note, payment method; audit logged; not on
   cancelled bills), Reverse Bill with required reason, doing the full reversal transaction in §8.5
   (shift totals if still open, stock back to Showroom exact batches/units, sale_cancel movements,
   loyalty reversal, delete warranties, low_stock_alerted reset).
2. /quotations: list + status filter, New Quotation modal (product search or free-text items,
   discounts, note/terms, valid until), view with Print A4 (id="quotation-print") / Download,
   Mark Accepted / Mark Rejected, delete (quotations.delete). Quotations never touch stock.
3. /warranty: list with computed status (Claimed / Expired / Expiring Soon · N days / Active),
   summary counts, search, Claim Warranty modal.

Done when: reversing a bill puts every stock number, point balance and shift total back exactly
as it was before the sale.
```

---

## Step 14: Finance, Expenses, Salary

```
Read docs/SPEC.md §8.19 (Finance) and §8.20 (Salary), and §9 items 6–7.

1. /finance with segmented tabs Overview / Daily Balance / Shifts / Expenses exactly as described:
   KPI cards and formulas, split-aware payment breakdown, cashier performance, supplier payables,
   cash book with opening balance and running closing balance, shifts table + view modal +
   Force Close (Admin/Super Admin only) + Review (Approve/Flag), expenses list + Add Expense
   (Salaries not selectable; optional "paid from cash drawer" → open shift) + delete with shift
   reversal. CSV exports.
2. app/Services/SalaryService.php + /salary with tabs Issue Payment / Payment History /
   Employee Setup: setup modal, issue (commission picker of unclaimed sales/jobs, base, %,
   amount, period, note, optional drawer shift) creating SAL- payment + linked salaries Expense
   (EXP- number) in one transaction, history with calculation details, email payslip,
   delete (removes expense, frees sales/jobs, reverses open-shift debit).

Done when: paying a cash salary from an open shift lowers that shift's expected cash, and deleting
the payment restores it.
```

---

## Step 15: Dashboard & Audit Log

```
Read docs/SPEC.md §8.3 (Dashboard) and §8.21 (Audit Log).

1. /dashboard: build every section in §8.3 in order, with the exact colours and
   permission-based visibility: header + Today/7 Days/30 Days toggle, attention strip,
   revenue hero card with the SVG area trend chart (current vs previous period, hover tooltip),
   Profit & Loss card, KPI tiles, Repair Workshop, Busiest Hours heatmap, Top Products,
   Sales Mix + payment methods, Team, Top Customers, Recent Activity, Low Stock.
   Use efficient aggregate SQL queries (exclude cancelled sales). Split payments must be counted
   per leg.
2. /audit-log: filters (record type, date range), search, table (When, Who, Record, Fields Changed
   chips), view modal with before → after for each field. Read-only.

Done when: the dashboard numbers match the Finance Overview for the same period.
```

---

## Step 16: User Manual page, emails polish, final check

```
Read docs/SPEC.md §8.2 (Manual), §9 (Emails) and §11 (Acceptance checklist).

1. Build the public /manual page (noindex) with the layout, components (numbered steps,
   bullets, Tip and Warning boxes, flow diagrams, FAQ accordion) and every section listed in §8.2.
2. Review all 7 email templates for consistent branding, HTML + plain text, and strict recipient
   validation. Every email endpoint must load its data from the database.
3. Go through EVERY item in the §11 acceptance checklist. For each one, tell me: done / not done,
   and fix anything not done.
4. Check every page at 375px (phone), 768px (tablet) and 1440px (desktop) widths and fix any
   horizontal scrolling or broken layout.
5. Write a README with install steps (composer install, npm install, .env, migrate --seed,
   npm run build) and deployment notes.
```

---

## Tip: prompt to use when something is wrong

```
This doesn't match the spec. In docs/SPEC.md §X it says: "<paste the exact line>".
Right now the app does: "<what you see>". Fix it so it matches the spec exactly.
Don't change anything else.
```
