# Prompt: Rebuild "M-Fixpro POS" in Laravel + MySQL (1:1 clone)

> Copy everything below this line and give it to your AI coding assistant (or developer).
> Also copy the `public/shop_logo/` folder (logos: `IMG_0112.PNG`, `logo-white.png`, `1_M.png`) and `public/loading/*.json` (Lottie loading animations) from the old project into the new Laravel `public/` folder.

---

## 0. Your task

Build a complete web application called **M-Fixpro POS** using **Laravel 11 (PHP 8.2+) and MySQL 8**. It is a point-of-sale + repair-shop management system for a computer/electronics repair shop in Sri Lanka (currency **"Rs."**, numbers formatted with thousands separators, e.g. `Rs. 12,500`).

It must be a **100% functional and visual copy** of an existing Next.js + Firebase app. Every page, button label, table column, modal, status, calculation, permission and business rule described below must be reproduced exactly. Do not simplify or skip features. Where the old app relied on Firebase-specific behaviour, use the Laravel/MySQL equivalent that I specify.

Build it module by module, run migrations, and make sure every page works before moving on.

---

## 1. Tech stack (use exactly this)

| Concern | Use |
|---|---|
| Backend | Laravel 11, PHP 8.2+ |
| Database | MySQL 8 (InnoDB, `utf8mb4`) — all money columns `DECIMAL(12,2)` |
| Views | Blade templates + **Tailwind CSS 3** (via Vite) + **Alpine.js 3** for interactivity (modals, POS cart, tabs, dropdowns) |
| Icons | Lucide icons (`mallardduck/blade-lucide-icons` package, e.g. `<x-lucide-shopping-cart class="w-4 h-4"/>`) |
| Auth | Laravel session auth (email + password), custom login page (no Breeze styling) |
| Permissions | Custom `permissions` JSON column on users + Laravel Gates (details in §4) |
| Email | Laravel Mail over SMTP (`MAIL_*` in `.env`), HTML + plain-text parts |
| Bot check | Cloudflare Turnstile on login / forgot / reset forms (verify server-side with `Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', …)`) |
| PDF / print | Browser print (`window.print()` with A4 print CSS) + client-side **html2canvas + jsPDF** "Download PDF" (same as old app), and the same PDF (base64) attached to the bill email |
| Loading screen | `lottie-web` playing `/loading/gaming.json` while pages load (optional nice-to-have) |
| CSV export | Server-side streamed CSV responses |

All state-changing operations that touch stock, money, counters or balances **must run inside `DB::transaction()` and use `lockForUpdate()`** on the rows they read (products, batches, units, shifts, suppliers, counters). This replaces the Firestore transactions of the old app and must prevent overselling and double-numbering.

---

## 2. Design system (copy exactly)

### 2.1 Tailwind config
```js
theme.extend = {
  fontFamily: {
    milonga: ['"Open Sans"', 'sans-serif'],
    prata:   ['Montserrat', 'sans-serif'],   // used for headings, always font-weight 700
    poppins: ['Poppins', 'sans-serif'],      // default body font
  },
  colors: {
    black: '#0a0a0a',
    brand: { DEFAULT: '#e30613', dark: '#b8050f', light: '#fff1f1', logo: '#ff0607' },
    ink: '#0a0a0a',
    white: '#ffffff',
    gray: { 50:'#fafafa',100:'#f4f4f5',200:'#e4e4e7',300:'#d4d4d8',400:'#a1a1aa',500:'#71717a',600:'#52525b',700:'#3f3f46',800:'#27272a',900:'#18181b' },
  },
  borderRadius: { none:'0', sm:'2px', DEFAULT:'4px', md:'6px', lg:'8px', xl:'12px' },
  keyframes: {
    fadeIn:  { from:{opacity:'0',transform:'translateY(6px)'}, to:{opacity:'1',transform:'translateY(0)'} },
    slideIn: { from:{transform:'translateX(-100%)'}, to:{transform:'translateX(0)'} },
  },
  animation: { fadeIn:'fadeIn 0.2s ease-out', slideIn:'slideIn 0.25s ease-out' },
}
```
Use Tailwind's `zinc-*` palette for greys throughout (as the original does).

### 2.2 Global CSS (`resources/css/app.css`)
```css
@import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&family=Montserrat:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600&display=swap');
@tailwind base; @tailwind components; @tailwind utilities;

html { font-family: 'Poppins', sans-serif; background:#fff; color:#0a0a0a; }
body { font-family:'Poppins',sans-serif; font-size:14px; line-height:1.6; color:#0a0a0a; background:#fafafa; -webkit-font-smoothing:antialiased; }
.font-prata { font-weight: 700; }

/* thin scrollbars */
::-webkit-scrollbar{width:4px;height:4px} ::-webkit-scrollbar-track{background:#f4f4f5}
::-webkit-scrollbar-thumb{background:#d4d4d8;border-radius:2px} ::-webkit-scrollbar-thumb:hover{background:#a1a1aa}
.sidebar-nav-scroll{scrollbar-color:#e4e4e7 transparent;scrollbar-width:thin}

/* hide number spinners */
input[type=number]{-moz-appearance:textfield}
input[type=number]::-webkit-outer-spin-button,input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}

/* iOS: avoid zoom on focus */
@media (hover:none) and (pointer:coarse){ input:not([type=checkbox]):not([type=radio]):not([type=range]),select,textarea{font-size:16px!important} }

@layer components {
  .nexora-card  { background:#fff; border:1px solid #e4e4e7; border-radius:8px; }
  .nexora-input { width:100%; border:1px solid #e4e4e7; border-radius:4px; padding:8px 12px; font-family:'Poppins'; font-size:14px; color:#0a0a0a; background:#fff; outline:none; transition:border-color .15s; }
  .nexora-input:focus { border-color:#e30613; } .nexora-input::placeholder { color:#a1a1aa; }
  .nexora-btn { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; font-family:'Poppins'; font-size:13px; font-weight:500; border-radius:4px; cursor:pointer; transition:all .15s; border:none; outline:none; }
  .nexora-btn-primary { background:#e30613; color:#fff; } .nexora-btn-primary:hover { background:#b8050f; }
  .nexora-btn-outline { background:transparent; color:#0a0a0a; border:1px solid #e4e4e7; } .nexora-btn-outline:hover { border-color:#e30613; color:#e30613; }
  .nexora-btn-ghost { background:transparent; color:#52525b; } .nexora-btn-ghost:hover { background:#f4f4f5; color:#e30613; }
  .nexora-btn-danger { background:#0a0a0a; color:#fff; } .nexora-btn-danger:hover { background:#e30613; }
  .badge { display:inline-flex; align-items:center; padding:2px 8px; border-radius:2px; font-size:11px; font-weight:500; letter-spacing:.03em; }
  .badge-default{background:#f4f4f5;color:#52525b} .badge-success{background:#f0fdf4;color:#15803d}
  .badge-warning{background:#fefce8;color:#a16207} .badge-danger{background:#fef2f2;color:#dc2626}
  .badge-info{background:#eff6ff;color:#1d4ed8}
}

/* A4 print: only the print container is visible */
@media print {
  @page { size:A4; margin:15mm; }
  body * { visibility:hidden; }
  #bill-print, #bill-print *, #quotation-print, #quotation-print *, #job-print, #job-print * { visibility:visible; }
  #bill-print, #quotation-print, #job-print { position:absolute; inset:0; width:180mm!important; min-height:267mm!important; padding:0!important; font-size:11pt; color:#000; background:#fff; }
  .no-print { display:none!important; }
  .bill-signature-block, .job-signature-block { margin-top:6mm!important; }
}
```

### 2.3 UI conventions used on every page
- Page wrapper: `p-4 sm:p-8`. Page title: `<h1 class="font-prata text-2xl text-ink">`, with a small grey subtitle under it and action buttons on the right (`flex justify-between`).
- Lists are `.nexora-card` tables: header row `text-xs text-zinc-500 font-medium uppercase tracking-wider`, rows `divide-y divide-zinc-100`, `hover:bg-zinc-50`. Row action buttons are small icon buttons (eye = view, pencil = edit, trash = delete).
- Search box: `.nexora-input pl-9` with a Search icon absolutely positioned at left.
- Date filters: two `<input type="date">` (From / To), default = **last 30 days** on every history list (GRN, Stock Transfer, Stock Out, Stock Movements, Bills, Quotations, Audit Log, Supplier payments, Expenses, Salary history).
- **Pagination**: 10 rows per page (component with Prev / page numbers / Next, "Showing x–y of z").
- **Modals**: `fixed inset-0 bg-black/50 flex items-center justify-center z-50`, panel `bg-white rounded-xl w-full max-w-{sm|md|lg|2xl} mx-4 max-h-[90vh] overflow-y-auto`, header row with `font-prata` title and an X close button.
- **Confirm dialog** component (title, message, Cancel + red Confirm button) used for every delete / reverse / close action.
- **Access Restricted** component: centered card with a lock icon, `<h1>Access Restricted</h1>` and a message like "You don't have permission to view the X page." — shown when a user opens a page they lack the `.view` permission for.
- **SearchableSelect** component: an input that filters a dropdown list (used to pick products, suppliers, jobs).
- Status badges use the `.badge-*` classes listed above.
- Every page is fully responsive (works on phone, tablet, desktop).

---

## 3. App layout (shell)

**Sidebar** (`w-56` on desktop, static; on mobile a `w-64` off-canvas drawer with black 50% overlay, slide transition):
- Top: logo `/shop_logo/IMG_0112.PNG` (`w-28 scale-[1.4]` inside an overflow-hidden box), X button on mobile.
- Nav (each link hidden if user lacks its permission; a group is hidden if all its children are hidden). Active link: `bg-brand text-white font-medium`; inactive: `text-zinc-600 hover:text-brand hover:bg-brand-light`. Groups are collapsible with a rotating ChevronDown and an animated height; children are indented with a dashed left border (`ml-[1.2rem] pl-2 border-l border-dashed border-zinc-300`). The group containing the current page auto-expands.

```
Dashboard                (layout-dashboard)   dashboard.view
POS / New Sale           (shopping-cart)      sales.view
Jobs                     (wrench)             jobs.view
Services                 (hammer)             services.view
Sales ▾                  (receipt)
   Bills                 (receipt)            bills.view
   Quotations            (file-text)          quotations.view
   Warranty              (shield)             warranty.view
Inventory ▾              (boxes)
   Products              (package)            products.view
   GRN                   (package-plus)       grn.view
   Stock Transfer        (arrow-left-right)   stockTransfer.view
   Stock Out             (package-minus)      stockOut.view
   Stock Movements       (history)            stockMovements.view
   Brands                (book-marked)        brands.view
   Categories            (layers)             categories.view
Contacts ▾               (users)
   Customers             (users)              customers.view
   Suppliers             (truck)              suppliers.view
Finance ▾                (wallet)
   Overview              (wallet)             finance.view
   Salary                (banknote)           salary.view
System ▾                 (settings)
   Audit Log             (shield-check)       auditLog.view
   Settings              (settings)           (always visible)
```
- Bottom: user card linking to `/profile` — black circle avatar with initials (first + last initial, white Montserrat text, turns brand-red on hover), name (or email), and `"{Role} · View profile"`. Then a **Sign out** button (log-out icon). Then tiny footer text: `© {year} {Shop name}` and `Design & Developed by plexCode`.

**Header bar**: on mobile it is brand red with a hamburger (Menu icon) and the shop name in white; on desktop it is white with a bottom border. Right side shows a **live clock** updating every second: `Tue, Sep 29, 2026 · 10:42 AM`.

The browser tab title is `"{Shop name} POS"`. The shop name comes from shop settings (default "M-Fixpro").

---

## 4. Users, roles & permissions

### 4.1 Roles
`Super Admin`, `Admin`, `Manager`, `Cashier`, `Technician`, `Staff`.
- **Super Admin** is always allowed everything and is never configurable, never shown to non-Super-Admins in the Team list, and is never a salary payee.
- **Admin** has everything by default, but a Super Admin can restrict an Admin's permissions.
- Admins can manage only Manager/Cashier/Technician/Staff (never a peer Admin or Super Admin). Super Admin can manage all editable roles including Admin.
- Users have `status`: `active` | `inactive`. Inactive users are refused at login with "Your account has been disabled".

### 4.2 Permission keys (store per user in a `permissions` JSON column)
```
dashboard.view
sales.view
grn.view, grn.create, grn.edit
stockTransfer.view, stockTransfer.create, stockTransfer.edit
stockOut.view, stockOut.create, stockOut.edit
stockMovements.view
products.view, products.edit, products.delete, products.batch.edit, products.unit.delete
suppliers.view, suppliers.editContact, suppliers.recordPayment, suppliers.editPayment, suppliers.sendStatement
bills.view, bills.cancel, bills.edit
finance.view, finance.reviewShift, finance.addExpense, finance.deleteExpense
salary.view, salary.manageConfig, salary.issue, salary.delete
auditLog.view
jobs.view, jobs.edit
quotations.view, quotations.delete
brands.view, brands.delete
categories.view, categories.delete
services.view, services.edit, services.delete
customers.view
warranty.view
```
Each key has a human label and a module (for grouping in the Settings UI), e.g. `grn.create` → "Create GRN" (module GRN), `products.batch.edit` → "Edit batch cost/price/qty", `products.unit.delete` → "Delete serial/unit", `suppliers.editContact` → "Edit supplier contact info", `suppliers.recordPayment` → "Record supplier payment", `suppliers.editPayment` → "Edit a recorded payment", `suppliers.sendStatement` → "Send supplier statement email", `bills.cancel` → "Reverse / cancel a sale", `bills.edit` → "Edit bill details", `finance.reviewShift` → "Review a closed shift", `finance.addExpense` → "Add an expense", `finance.deleteExpense` → "Delete an expense", `salary.manageConfig` → "Edit employee salary setup", `salary.issue` → "Issue a salary payment", `salary.delete` → "Delete a salary payment", `jobs.edit` → "Edit job details", `quotations.delete` → "Delete quotation", `brands.delete` → "Delete brand", `categories.delete` → "Delete category (main + sub)", `services.edit` → "Create / edit service", `services.delete` → "Delete service", every `.view` key → "View {Module}".

### 4.3 Defaults
- `DEFAULT_TRUE` = every `*.view` key **except `salary.view`**, plus `products.edit` and `suppliers.editContact`.
- Admin: all keys true.
- Manager: DEFAULT_TRUE + `grn.create`, `stockTransfer.create`, `stockOut.create`, `finance.view`, `finance.reviewShift`, `finance.addExpense`.
- Cashier, Technician, Staff: DEFAULT_TRUE.
- Unknown / empty role: everything false (fail closed).
- When a user is created, store the full default map for their role in `permissions`.

### 4.4 Check function
```php
function can(User $u, string $key): bool {
  if ($u->role === 'Super Admin') return true;
  if (is_array($u->permissions) && array_key_exists($key, $u->permissions)) return (bool)$u->permissions[$key];
  return defaults($u->role)[$key] ?? false;
}
```
Register this as a Gate (`Gate::before` for Super Admin + `Gate::define` per key) and enforce it **on the server in every controller action**, not only by hiding UI. Permission changes must take effect on the user's next request (read from DB each request).

Special role-only rules: force-closing a shift = Admin/Super Admin only; editing shop info, low-stock emails, team management and data tools in Settings = Admin/Super Admin; sending a supplier account statement additionally requires Admin/Super Admin on the server.

---

## 5. Database schema (MySQL)

Create migrations for these tables (add `created_at`/`updated_at` timestamps to all unless noted). Use `BIGINT` auto-increment ids. Keep "snapshot" name columns (e.g. `customer_name`, `cashier_name`) exactly as listed because printed documents and reports must show the name as it was at the time.

```
users: id, name(display_name), email UNIQUE, phone NULL, password, role, status ENUM(active,inactive) default active,
       permissions JSON NULL, salary_type ENUM(monthly,commission,hybrid) NULL, salary_monthly_amount DECIMAL NULL,
       salary_commission_percent DECIMAL(5,2) NULL, remember_token

shop_settings (single row): name, phone, email NULL, address NULL, notify_emails JSON (array of emails)

password_reset_otps: email PK, user_id, otp CHAR(6), expires_at, used BOOL, attempts INT, created_at
counters: name PK (invoice, quotation, job, grn, transfer, stockOut, supplierPayment, shift, expense, salary), value INT

brands: name, description NULL
main_categories: name, description NULL
sub_categories: name, main_category_id FK, description NULL

services: name, default_price, description NULL, custom_fields JSON, active BOOL
   custom_fields = [{id,label,type(text|number|textarea|checkbox|select|date),required,placeholder,options[]}]

products: name, brand_id, main_category_id, sub_category_id, sku, barcode NULL, selling_price,
          total_stock INT, stores_stock INT, showroom_stock INT, low_stock_alert INT default 5,
          description NULL, warranty_months INT default 0, track_serial BOOL, active BOOL default 1,
          low_stock_alerted BOOL default 0
product_batches: product_id, cost_price, selling_price NULL, total_qty, remaining_qty, status ENUM(active,depleted),
          location ENUM(stores,showroom), source_batch_id NULL, supplier_id NULL, note, received_at
product_units: product_id, batch_id, serial_number, cost_price, selling_price NULL,
          status ENUM(in_stock,sold,issued), location ENUM(stores,showroom), sale_id NULL, sold_at NULL,
          stock_out_id NULL, issued_at NULL

customers: name, phone, phone2 NULL, email NULL, address NULL, loyalty_points INT default 0

suppliers: name, phone, email NULL, address NULL, total_payable, amount_paid, balance,
           payment_status ENUM(paid,partial,outstanding) default paid, last_payment_at NULL, last_statement_sent_at NULL
supplier_payments: payment_no UNIQUE, supplier_id, supplier_name, amount, method ENUM(cash,bank_transfer,cheque,other),
           reference, note, balance_before, balance_after, paid_by_id, paid_by_name

sales: invoice_no UNIQUE, customer_id NULL, customer_name, customer_phone NULL, customer_email NULL,
       cashier_id, cashier_name, job_id NULL, job_no NULL, services JSON NULL,
       subtotal, discount_amount, tax_amount, total_amount,
       payment_method ENUM(cash,card,transfer,kokopay), payments JSON NULL ([{method,amount}] only for split payments),
       kokopay_charge_percent NULL, kokopay_charge_amount NULL, card_charge_percent NULL, card_charge_amount NULL,
       payment_status ENUM(paid,partial,pending), amount_tendered NULL, change_amount NULL, points_redeemed INT default 0,
       note NULL, shift_id NULL, shift_no NULL, commission_payment_id NULL,
       status ENUM(cancelled) NULL, cancelled_at NULL, cancelled_by_id NULL, cancelled_by_name NULL, cancel_reason NULL
sale_items: sale_id, product_id, product_name, sku, batch_id NULL, qty, unit_price, cost_price, discount, line_total,
       warranty_months, units JSON NULL ([{unitId,serialNumber,batchId}]), batch_allocations JSON NULL ([{batchId,qty}])

warranties: customer_id NULL, customer_name, product_id, product_name, sale_id, serial_number NULL,
       warranty_months, start_date, end_date, status ENUM(active,expired,claimed), claim_note NULL, claimed_at NULL

quotations: quotation_no UNIQUE, customer_id NULL, customer_name, customer_phone, customer_address,
       prepared_by_id, prepared_by_name, subtotal, discount_amount, total_amount, valid_until DATE,
       status ENUM(sent,accepted,rejected,expired,converted) default sent, note
quotation_items: quotation_id, product_id NULL, product_name, sku NULL, qty, unit_price, discount, line_total

jobs: job_no UNIQUE, customer_id NULL, customer_name, customer_company, customer_address, customer_city,
      customer_phone, customer_phone2, customer_email, device_type, device_type_other, brand, model, serial_no, color,
      parts JSON ([{id,name,spec,serialNo}]), fault_description, accessories JSON, accessories_other,
      physical_condition JSON, special_notes, received_by_id, received_by_name,
      assigned_technician_id NULL, assigned_technician_name, services JSON ([{id,name,price,chargeType(paid|free),freeReason}]),
      estimated_cost, advance_paid, expected_delivery_date NULL,
      status ENUM(pending,ongoing,done,delivered,unrepairable) default pending, repair_cost NULL, date_returned NULL,
      commission_payment_id NULL
job_status_history: job_id, status, note, repair_cost NULL, updated_by_id, updated_by_name, created_at

stock_movements: product_id, type ENUM(in,out,adjustment,transfer), qty (signed), reference_id, reference_type
      (grn|transfer|stock_out|sale|sale_cancel|batch_edit), note, performed_by, performed_by_name, location NULL,
      from_location NULL, to_location NULL, recipient NULL, reason NULL, reason_detail NULL, job_id NULL, job_no NULL,
      supplier_name NULL, created_at

grns: grn_no UNIQUE, supplier_id NULL, supplier_name, total_cost, received_by_id, received_by_name, note, location
grn_items: grn_id, product_id, product_name, sku, qty, cost_price, selling_price NULL, serials JSON, batch_id

stock_transfers: transfer_no UNIQUE, transferred_by_id, transferred_by_name, note
stock_transfer_items: stock_transfer_id, product_id, product_name, sku, qty, serial_numbers JSON, source_batch_ids JSON, new_batch_ids JSON

stock_outs: stock_out_no UNIQUE, location, issued_by_id, issued_by_name, recipient, reason ENUM(job,sale,other),
      reason_detail, job_id NULL, job_no NULL, note
stock_out_items: stock_out_id, product_id, product_name, sku, qty, serial_numbers JSON, cost_price

shifts: shift_no UNIQUE, cashier_id, cashier_name, status ENUM(open,closed), opening_float, opened_at, open_note,
      cash_sales_total, card_sales_total, transfer_sales_total, kokopay_sales_total, sales_count, cash_expenses_total,
      closed_at NULL, expected_cash NULL, counted_cash NULL, variance NULL, close_note,
      force_closed BOOL default 0, closed_by_id NULL, closed_by_name NULL,
      review_status ENUM(pending,approved,flagged) NULL, reviewed_at NULL, reviewed_by_id NULL, reviewed_by_name NULL, review_note

expenses: expense_no UNIQUE, category ENUM(rent,utilities,salaries,maintenance,marketing,other), amount, note,
      paid_by_id, paid_by_name, linked_salary_payment_id NULL, shift_id NULL, shift_no NULL

salary_payments: payment_no UNIQUE, user_id, user_name, user_role, type ENUM(monthly,commission,hybrid), amount,
      commission_base NULL, commission_percent NULL, commission_items JSON ([{kind:sale|job,id,number,amount}]),
      period_label, note, issued_by_id, issued_by_name, linked_expense_id, shift_id NULL, shift_no NULL, email_sent_at NULL

audit_logs: collection_name, doc_id, label, changes JSON ([{field,before,after}]), performed_by_id, performed_by_name, created_at
```

Indexes: `sales(created_at)`, `sales(shift_id)`, `sales(customer_id)`, `jobs(status, created_at)`, `jobs(customer_phone)`, `customers(phone)`, `customers(name)`, `stock_movements(product_id, created_at)`, `warranties(end_date)`, `product_batches(product_id, location, status, received_at)`, `product_units(product_id, status, location)`, `product_units(serial_number)`.

Seeder: a Super Admin user (email/password from `.env`), a `shop_settings` row (name "M-Fixpro"), and all counters at 0.

---

## 6. Document numbering

Every number is generated inside the same DB transaction as the record, by locking the `counters` row (`SELECT … FOR UPDATE`), incrementing, and zero-padding to 5 digits:

| Record | Format |
|---|---|
| Sale / bill | `INV-00001` |
| Quotation | `QUO-00001` |
| Job | `JOB-00001` |
| GRN | `GRN-00001` |
| Stock transfer | `TRF-00001` |
| Stock out | `SO-00001` |
| Supplier payment | `PAY-00001` |
| Shift | `SHIFT-00001` |
| Expense | `EXP-00001` |
| Salary payment | `SAL-00001` |

Jobs also allow a **custom job number** typed by staff: it is upper-cased, must not already exist ("Job number JOB-00123 is already in use."), and if it matches `JOB-(\d+)` with a number higher than the counter, the counter is moved up to it. The New Job form shows a preview of the next number.

---

## 7. Inventory model (the core rule set)

- Two stock locations: **Stores** (back room — all new stock arrives here by default; POS can't sell from it) and **Showroom** (POS sells only from here).
- `products.total_stock = stores_stock + showroom_stock` — always keep all three in sync on every movement.
- Stock is held in **batches** (one per receipt, each with its own cost price and optional selling price that overrides the product price). Batches are consumed **FIFO (oldest `received_at` first)** within one location. When `remaining_qty` hits 0 → status `depleted`, otherwise `active`.
- **Serial-tracked products** (`track_serial = true`): each physical unit is a row in `product_units` with its own serial number; units are picked explicitly (not FIFO) and consume from their own batch.
- Every stock change writes a `stock_movements` row.
- Cost of goods (COGS) for a sale line = the weighted cost of the batches actually consumed.

### FIFO allocation (reuse everywhere)
```
allocateFifo(batches ordered by received_at asc, filtered by location + status=active, locked FOR UPDATE, qty):
  preferred batch (if the cashier picked one) goes first
  for each batch: take = min(need, remaining); record {batchId, qty:take, remainingAfter}; cost += take*batch.cost
  if need > 0 after loop → throw "Not enough stock for "{name}": requested X, only Y available."
  return consumed[], costPrice = cost/qty
```

---

## 8. Modules & pages — build all of these

### 8.1 Login (`/auth/login`) — public
Layout: gradient background `from-zinc-100 via-zinc-50 to-zinc-200`; a centred white card (`rounded-3xl`, big soft shadow, max-w 440px mobile / 900px desktop, 2 columns on desktop).
- **Left brand panel** (bg `#e30613`, white text, decorative translucent white circles): white logo `/shop_logo/logo-white.png`, heading "Welcome to {Shop name}", a short white underline bar, text "Manage repairs, sales and billing for your shop from one simple dashboard."
- **Right form** with three modes in one page:
  1. **Sign in** — "Enter your credentials to continue"; Email, Password (show/hide eye toggle), "Forgot password?" link next to the Password label, Turnstile widget, **Sign in** button. Errors: wrong credentials, disabled account, failed verification.
  2. **Forgot password** — email + Turnstile → **Send code**. Server: generate a 6-digit OTP, valid 10 minutes, 60-second resend cooldown, store in `password_reset_otps`, email it. Always return the same generic success (no account enumeration).
  3. **Reset password** — code + new password + confirm (min 6 chars) + Turnstile → **Reset password**. Max 5 wrong attempts then the code is burned ("Too many attempts. Please request a new code."). Expired → "This code has expired. Request a new one." On success go back to Sign in with a success message.
- `/` redirects to `/dashboard` if logged in, else `/auth/login`.

### 8.2 Public User Manual (`/manual`) — public, `noindex`
A long, nicely styled help page: sticky top bar (logo `1_M.png`, "M-Fixpro POS / User Manual", red "Open the system" button), hero ("Staff guide" / "How to use M-Fixpro POS"), a sticky table of contents on the left (collapsible `<details>` on mobile) and sections: Welcome, Who can do what (roles table), Signing in, Finding your way, Shifts, Making a sale (POS), Repair jobs (with a coloured status flow: Job Pending → Ongoing Job → Job Done → Delivered), Billing a finished job, Bills, Quotations, Warranty, Stock & products (Stores vs Showroom cards + GRN → Transfer → Sale flow), Customers & suppliers, Finance, Salary, Audit log, Settings & team, A normal day (Morning / During the day / Closing cards), Questions & problems (FAQ accordion). Use numbered-step lists (black circle numbers), red-dot bullet lists, green "Tip" boxes and amber "Warning" boxes. Write the content to describe exactly the behaviour in this prompt.

### 8.3 Dashboard (`/dashboard`) — `dashboard.view`
Header: "Good morning/afternoon/evening 👋", title **Business Overview**, today's full date. Right: period toggle **Today / 7 Days / 30 Days** (segmented control, `bg-zinc-100` pill, active = white with shadow; comparisons "vs yesterday", "vs previous 7 days", "vs previous 30 days"), red **+ New Sale** button, outlined **New Job** button (if jobs.view).

Sections (exclude cancelled sales everywhere):
1. **Attention strip** (rounded-full pills, only if non-zero): "N repairs past promised date" (red, jobs with status pending/ongoing and expected delivery date < today), "N devices ready for pickup" (emerald, status done), "N products low on stock" (amber, total_stock ≤ low_stock_alert), "N unsettled bills" (grey, payment_status partial/pending; finance.view only).
2. **Revenue hero card** (8/12 cols, finance.view only): "Revenue · {period}", big amount, delta pill (▲/▼ % vs previous period, green/red), mini stats Orders / Avg ticket / Items sold with deltas, and an **SVG area trend chart** (current period = red line `#e30613` 2.5px with red gradient fill; previous period = dashed grey line; hover crosshair, red dot and a dark tooltip showing date, amount and "prev" amount). Legend "This period / Previous period" and "Repairs: Rs. X" (revenue from sales that billed a job). For "Today" the series is hourly; otherwise daily.
3. **Profit & Loss card** (4/12, finance.view): Net profit (red if negative) + delta; bars for Revenue (#0a0a0a), Cost of goods (#a1a1aa), Gross profit (#16a34a), Expenses (#e30613) scaled to revenue; Gross margin % and Expense ratio %.
4. **KPI tiles** (4 across): Active repairs (with "N overdue"), Jobs received (+delta), New customers (+delta), Unsettled bills (count + "Rs. X billed") — or, without jobs.view / finance.view: Orders, Products ("In inventory"), Customers ("Registered").
5. **Repair Workshop** card (jobs.view): stacked pipeline bar with stage counts — Pending `#d4d4d8`, In repair `#0a0a0a`, Ready `#e30613`, Delivered `#16a34a`, Unrepairable `#a1a1aa`; Avg turnaround (days from received to returned), Delivered count in period, Fix rate %; "Waiting for pickup" list (customer, job no, device, badge with days waiting — red if > 7); "Technician workload" bars (open jobs per technician); device-type chips.
6. **Busiest Hours** heatmap (last 30 days): rows Mon–Sun, columns hours (only the range of hours that had sales), cell colour `rgba(227,6,19, 0.15 + t*0.85)`, grey when 0; "Peak slot" text and a Less→More legend.
7. **Top Products** table (finance.view): rank badge (1st is red), Product, Qty, Margin % (red < 10%, else green), Revenue.
8. **Sales Mix** by main category (stacked bar + list with %; amounts if finance.view) and **Payment methods** mini-cards (Cash/Card/Transfer/KokoPay with % and amount, using split payments correctly).
9. **Team**: staff ranked by sales revenue (avatar initials, sale count, amount).
10. **Top Customers** (finance.view): name, visits, spend; footer "+ N walk-in sales".
11. **Recent Activity**: latest sales and jobs merged, icon, title, subtitle, amount or status badge, "just now / 5m ago / 3h ago / 2d ago".
12. **Low Stock**: up to 6 products with "N left" badge (red if 0) and a thin progress bar; if none — green check "All stocked up · N products tracked".

Compute all of this with aggregate SQL queries (no need for the Firebase "daily stats" cache docs).

### 8.4 POS / New Sale (`/sales`) — `sales.view`
Two-column layout filling the screen height on desktop (left flexible catalogue, right `w-96` cart), stacked on mobile.

**Left header**: title "New Sale"; outlined button **Find Job to Bill** (wrench icon); shift button:
- No open shift → amber pill "🔒 No open shift — tap to open" → **Open Shift** modal (Opening cash float, optional note e.g. "Morning shift"). A cashier can only have one open shift ("You already have an open shift — close it before opening a new one."). Float can't be negative.
- Open shift → grey pill: `SHIFT-00012  Cash Rs. X · Card Rs. Y  Close Shift` → **Close Shift — SHIFT-xxxxx** modal: counted cash + note (e.g. "Rs. 200 short — coin drawer miscount"). Only the cashier who opened it can close it. `expected = opening_float + cash_sales_total − cash_expenses_total`, `variance = counted − expected`, `review_status = pending`. Then show a **Shift Closed** result modal with Expected / Counted / Variance (0 = exact, minus = short, plus = over).

**Tabs**: **Products (count)** and **Services (count)** with underline style (active = red border + red text + red count pill).
- Products tab: search "Search product by name, SKU or barcode…" (pressing **Enter** with an exact barcode/SKU match adds it immediately — barcode scanner support), Main category select ("All categories"), Sub category select ("All subcategories", disabled until a main is chosen), Clear button. Grid of product cards (2/3/4 columns): name, SKU, price, a badge with **showroom stock** (warning style if ≤ 5). Only **active** products with `showroom_stock > 0` are listed.
- Services tab: search "Search service by name…"; grid of active services (name, description or its custom-field labels, default price, "N in bill" badge). Clicking opens a modal to enter the price (pre-filled with default) and the service's **custom fields** (text/number/textarea/checkbox/select/date, validating required ones: "\"X\" is required." / "\"X\" must be ticked."). Service lines can be edited or removed in the cart.

**Adding products**
- Non-serial product with showroom batches → **batch picker modal**: first option **Auto (FIFO)**, then each showroom batch (cost, selling price, remaining qty) with a red "Selling at a loss" label if its price < cost. If no batches just add it.
- Serial product → **serial picker modal** listing available showroom units (checkboxes, excluding units already in the cart) → **Add to Cart**. Units are grouped into cart lines by their batch selling price.
- Unit price = batch selling price ?? product selling price.
- Cart line: name, "Serial: …" / "Batch · Rs. X/unit cost" / "Auto (FIFO)", − qty + (can't exceed showroom stock; for serial lines + opens the serial picker, − removes the last unit), per-unit **Discount** input, line total, X remove.

**Right cart panel**
1. Customer button "Walk-in customer (tap to select)" → **Select Customer** modal (debounced search by name or phone, min 2 chars, results list, **New Customer** button → form: Full name*, Phone number*, Email). Under it: "{N} pts available · Earns 1 pt per Rs. 100 spent" or "Select a customer to earn 1% loyalty reward".
2. If a job is attached: grey box with job no, customer · device, and its billable lines (free lines show "Free" in green).
3. Service lines, then product lines ("Cart is empty / Tap a product or service to add").
4. Totals: Subtotal; **Bill Discount** input (0…subtotal); **Redeem Points** input (only if the customer has points; max = min(points, subtotal − discount)); **Total** (Montserrat, large).
5. Payment method buttons **Cash / Card / Transfer / KokoPay** (selected = black). Multiple can be selected to **split** the payment (KokoPay can never be combined — it disappears in split mode).
6. Cash only → "Amount tendered" input and green "Change: Rs. X".
7. Split mode → one amount input per method and a status line: green **Balanced**, red "Remaining: Rs. X" or "Over by Rs. X". Checkout disabled until balanced and each leg > 0.
8. "Points after this sale: X pts (+earned)".
9. Amber warning "Open a shift above before checking out." when no shift.
10. Big red button **Checkout — Rs. {total}** ("Processing…" while saving).

**Card / KokoPay surcharge** (exact behaviour):
- Clicking **KokoPay**, or **Card** when it's the only method, opens a "{Method} Charge" modal asking for a surcharge %. The % is **not** a separate fee line: every product, quick service and paid job line price is multiplied by `(1 + %/100)` and rounded, so the customer's bill just shows higher item prices. A small staff-only note shows "Item prices above include a X% {Method} surcharge (Rs. Y) — staff view only, not shown on the customer's bill" with an **Edit %** link.
- Card inside a split (e.g. Cash + Card): the % applies only to the card portion. Staff type the other legs; card base = `max(0, baseSubtotal − discount − points − otherLegs)`, fee = `round(cardBase × %/100)`, multiplier = `1 + fee/baseSubtotal`, the card leg is auto-filled as `total − otherLegs` ("(Rs. base + X% — swipe this)"). "+ Add card charge %" link if none.
- Store `kokopay_charge_percent/amount` or `card_charge_percent/amount` (amount = surcharged subtotal − base subtotal) on the sale as reporting metadata only.

**Find Job to Bill** modal: search by job no (e.g. "123" → JOB-00123), customer name or mobile; only jobs with status **done** are listed. Selecting attaches it. The job's billable lines are:
```
services = job.services; servicesTotal = sum(paid services)
final = job.repair_cost ?? (services non-empty ? servicesTotal : job.estimated_cost)
lines = services
diff = final − servicesTotal
 diff > 0 → add "Other repair charges" (or "Repair charge" if no services) = diff
 diff < 0 → add "Repair cost adjustment" = diff
advance = min(job.advance_paid, max(0, final)); if advance > 0 → add "Less: advance paid" = −advance
```
Products can be sold on the same bill. The job's customer is used as the bill customer if none selected.

**Checkout = one DB transaction** (`SaleService::create`):
1. Lock the shift; it must be open and belong to this cashier ("Your shift is not open — reopen a shift before selling.").
2. For serial lines: lock units; each must be `in_stock` and in `showroom` ("\"SN123\" is no longer available for sale").
3. For other lines: lock showroom batches; if total showroom qty < requested → "Not enough Showroom stock for "X": requested 3, only 1 in Showroom (2 more in Stores — transfer to Showroom first.)"; then FIFO allocate.
4. Next `INV-` number. Insert sale + sale_items (with real FIFO `cost_price` and `batch_allocations`).
5. Shift: `sales_count += 1`, and add each payment leg to its `{cash|card|transfer|kokopay}_sales_total`.
6. Products: `total_stock −= qty`, `showroom_stock −= qty`; batches `remaining_qty −= qty` (depleted at 0); units → `sold`, `sale_id`, `sold_at`.
7. Stock movement per line: type `out`, qty −N, reference_type `sale`, note "Sale INV-xxxxx", location showroom.
8. Warranty rows for lines with `warranty_months > 0` — one per serial unit (with serial) or one per line: customer (or "Walk-in Customer"), start = now, end = now + N months, status active.
9. Low-stock: if new total_stock ≤ low_stock_alert and `low_stock_alerted` is false → set it true and collect the product; after commit send the **Low stock alert** email to `shop_settings.notify_emails` (skip if empty). Any restock resets `low_stock_alerted` to false.
10. Loyalty (only with a customer): `earned = floor((subtotal − discount)/100)`; `loyalty_points += earned − points_redeemed`. 1 point = Rs. 1.
11. If a job was billed, set it to **delivered** (history note "Delivered & billed via POS — Invoice INV-xxxxx", `date_returned = now`).
12. `payment_method` = the largest leg for splits; `payments` JSON only when split; `amount_tendered`/`change_amount` for cash.

After checkout show the **Sale Complete** modal with the A4 bill preview and buttons **Print**, **Download** (PDF), **Email** (only if the customer has an email; the PDF is attached), and **New Sale**. Remove sold-out products from the grid.

### 8.5 Bills (`/bills`) — `bills.view`
List (date range, default last 30 days; search "Search invoice no. or customer…"; paginated): Invoice, Customer, Date, Payment (label, or "Cash + Card" style for splits), Total, Status (Paid / Cancelled badge), actions. View modal: invoice details, items table (Item, Qty, Price, Total), services, totals, payments, cashier, shift, job no, cancellation info; buttons Print / Download / Email.
- **Edit Bill** (`bills.edit`): only customer name, phone, email, note, payment method. Not allowed on cancelled bills. Writes an audit log entry.
- **Reverse Bill** (`bills.cancel`): reason required ("Reason for reversing this bill (required)…"). In one transaction: mark sale `cancelled` with who/when/reason; if its shift is still open subtract its legs from the shift totals and `sales_count −1`; restore stock to **Showroom** (product counters, the exact batches from `batch_allocations`/units, units back to `in_stock` with sale cleared) with `sale_cancel` stock movements ("Cancelled INV-xxxxx"); reverse loyalty points (`+redeemed − earned`); delete the sale's warranties; reset `low_stock_alerted`. The bill stays visible marked Cancelled. Can't cancel twice.

### 8.6 A4 Bill print layout (`#bill-print`, 210×297mm, padding 15mm, Poppins)
- Header (bottom border 2px black): logo (20mm high) + shop name (18pt bold), address, "Tel: … | email"; right side "INVOICE" label, invoice no (14pt), date, time.
- Two columns: **Bill To** (customer name or "Walk-in Customer", phone, email, "Job: JOB-xxxxx") | **Served By** (cashier) + "Payment: Cash" or split legs "Cash: Rs. X · Card: Rs. Y".
- Table: # / Description (name + "(SKU)", "Serial: …", "Warranty: N months") / Qty / Unit Price / Disc. (red or "—") / Total. Service rows: "Name (Service · JOB-xxxxx)", custom field values, "Free — reason".
- Totals box (right, 55mm): Subtotal, Discount (red, "- Rs."), Points Redeemed (purple), Tax, thick rule, **Total**, and for cash: Tendered, Change (green).
- Bottom block pinned to page bottom: grey "WARRANTY TERMS & CONDITIONS" box with this exact text: *"Warranty replacement period: 14 days, warranty covers manufacturing defects only, no warranty for physical, liquid, electrical, or accidental damage, no warranty for software issues, OS installation, formatting, virus removal, or service/labor charges, warranty is void if the warranty sticker or serial number is removed, damaged, altered, or unreadable, all warranty claims are subject to inspection by our technicians."*; Note; two dotted signature lines "(Authority Signature)" and "(Customer Signature) / Goods received in Good Condition"; footer: shop name, "Quality Repairs. Genuine Parts. Trusted Service.", "Thank you for your purchase!", right "Support: email", "Copyright © YEAR {shop}. All Rights Reserved."

Build the Job note print (`#job-print`) and Quotation print (`#quotation-print`) in the same visual style (header, customer block, details/items table, totals, signature lines, footer). The job note shows job no, dates, customer details, device (type, brand, model, serial, colour), device parts table, fault, accessories, physical condition, special notes, technician, services & charges, estimated cost, advance paid, balance, expected delivery date, and terms + signatures.

### 8.7 Jobs (`/jobs`) — `jobs.view`
- Status summary chips/filters; filters: status select, date From/To; search "Search job no., customer or phone…" (server search: exact job no incl. "123" → JOB-00123, name prefix, phone/phone2 prefix). Paginated table: Job No, Customer, Device, Received, Est. Cost, Status (badge + coloured dot), actions (eye).
- Status labels / badges: pending "Job Pending" (warning, amber dot), ongoing "Ongoing Job" (default, zinc dot), done "Job Done" (success, green), delivered "Delivered" (info, blue), unrepairable "Can't Repair" (danger, red).
- **New Job** button → large modal "New Job Note":
  - Job No (pre-filled preview, editable for a custom number).
  - Customer: search existing customers (fills fields) or type new — Name*, Company, Address, City, Mobile*, Mobile 2, Email. A new customer is created automatically if not selected.
  - Device type buttons: Desktop, Laptop (default), Printer, Monitor, CCTV, Other (+ "Specify device type"); Brand, Model, Serial No, Colour.
  - **Device Parts** editor: quick-add chips per device type — Laptop: RAM, SSD, HDD, Battery, WiFi Card, Keyboard, Display; Desktop: RAM, SSD, HDD, Processor, Motherboard, GPU, Power Supply, WiFi Card; Printer: Cartridge, Toner, Drum Unit, Power Cable; Monitor: Power Adapter, Stand, Cable; CCTV: HDD, DVR/NVR, Camera, Power Adapter — plus "Add Part"; each row: Part ("e.g. RAM"), Spec ("e.g. 8GB DDR4 Kingston"), Serial No. Empty rows are dropped on save.
  - Fault description* (textarea).
  - Accessories checkboxes: Charger, Power Cable, Battery, Adapter, Bag, Mouse, Keyboard, HDD/SSD + "Other accessories". Physical condition checkboxes: Good, Scratches, Cracked, Broken Hinges, Liquid Damage, Missing Parts. Special notes.
  - Assign technician (active users with role Technician, sorted by name).
  - **Services & Charges** rows: name ("Service, e.g. Replace SSD"), Paid/Free toggle, price, and for Free a reason ("Reason (Warranty, Loyalty, Goodwill…)"); running total.
  - Estimated cost, Advance paid, Expected delivery date. **Save Job Note**.
  - Creates the job with status `pending` + first history row "Job received". Then opens the job view; if the customer has an email, ask "Send this job confirmation by email?" → **Send Email**.
- **Job view** modal: all details, services, Print A4 / Download buttons, **Job History** timeline (status, note, repair cost, who, when — newest first).
  - **Update Job Status** section: status select; for **Job Done** a "Repair cost / price (Rs.)" input with a **Use services total** button; note ("Note about this update (what was done / changed)…"); **Save Update** → updates job + appends history (delivered sets `date_returned`). Then offer to email the customer the update.
  - **Edit Job** (`jobs.edit`): same form as New (customer, device, parts, fault, accessories, condition, notes, technician, services, estimated cost, advance, expected date). Job no, status, repair cost, received-by and dates are NOT editable. Write an audit log with before/after of changed fields.
- **Export Report** → CSV of the filtered jobs with each job's full status history.
- Emails: "We've received your job JOB-x - {shop}" (new) / "Update on your job JOB-x - {shop}" (status, device, note, repair cost). Server loads the job itself; never trust client-sent emails/amounts.

### 8.8 Services (`/services`) — `services.view`
Table: Service (name + description), Default Price, Custom Fields (chips of labels), Status (Active/Inactive), actions. Search "Search by name, description or field…". Add/Edit modal (`services.edit`): Name* ("e.g. Laptop Screen Replacement"), Default price, Description, Active toggle, **Custom fields** builder (add/remove/reorder rows: Label "e.g. Model Number", Type select Text/Number/Long text/Checkbox/Dropdown/Date, Required checkbox, Placeholder "Optional hint text", Options for Dropdown as comma-separated "e.g. 13 inch, 14 inch, 15.6 inch"). Delete (`services.delete`) with confirm. Only active services appear in POS.

### 8.9 Quotations (`/quotations`) — `quotations.view`
List: Quotation, Customer, Issued, Valid Until, Total, Status (sent/accepted/rejected/expired/converted badges), actions; status filter; date range; search. **New Quotation** modal: customer name (placeholder "Walk-in Customer"), phone, address; **Add Item** with product search ("Search products by name or SKU…") or free-text item name; table Item / Qty / Unit Price / Discount / Total; overall discount; Note / Terms; Valid until date. Quotations never touch stock. View modal: Print A4 / Download, **Mark Accepted** / **Mark Rejected**; Delete (`quotations.delete`).

### 8.10 Warranty (`/warranty`) — `warranty.view`
Tabs/filter by status; search "Search customer or product…". Table: Customer, Product, Serial No., Start, End, Status, action. Computed status: claimed → "Claimed" (default); end < today → "Expired" (danger); ≤ 30 days left → "Expiring Soon · N days" (warning); else "Active" (success). Summary counts at top. **Claim Warranty** modal with a note ("e.g. Screen replaced under warranty") → status claimed, `claimed_at`.

### 8.11 Products (`/products`) — `products.view`
Search "Search by name, SKU or barcode…", filters (brand / category / status). Table: Product (name + SKU/barcode), Brand, Category (main › sub), Selling Price, Warranty, Stock (total with "Stores X · Showroom Y", low-stock highlight), Batches count, Status (Active/Inactive), actions.
- **Add/Edit Product** modal (`products.edit`) with sections "Product Details" (Name*, Brand*, SKU*, Barcode "Scan or type the product barcode", Main Category*, Sub Category* filtered by main, Description) and "Pricing & Stock" (Selling price*, Initial stock + Cost price "Per unit" (add only — creates the first batch in Stores), Low stock alert (default 5), Warranty (months, 0 = none), **Track Serial Numbers** checkbox (then initial serials are entered one per unit), Active toggle).
- **Stock Batches** modal: list of all batches (received date, location badge, cost, selling price, total/remaining, status, note); **Edit batch** (`products.batch.edit`: cost, selling price, total qty, remaining qty, note — if remaining changes, adjust product + location counters and write an `adjustment` / `batch_edit` movement "Batch quantity corrected"); **Add Batch** quick form (cost*, selling price "Use product price", qty* or serials, note → lands in Stores).
- **Serial Numbers** modal per batch: list units with status (In stock / Sold / Issued) and location; edit a serial; **Add More Serials**; delete an in-stock unit (`products.unit.delete`; sold units can't be deleted: "Cannot remove a unit that has already been sold").
- Delete product (`products.delete`) with confirm.

### 8.12 GRN — Goods Received (`/grn`, `/grn/new`) — `grn.view` / `grn.create`
List: GRN No., Supplier, Location, Received By, Total Cost, Date, view. **New GRN** page: Supplier (optional, SearchableSelect "No supplier"), Location (Stores default / Showroom), note (e.g. supplier invoice no); **Add Item**: product ("Select a product"), cost price* (> 0), selling price (optional, "Use product price"), qty or — for serial products — one serial per unit. Items table with totals. Save (one transaction): create `GRN-` record + items; per item a new batch (note "Received via GRN-x") + units; increment product total + location stock and reset `low_stock_alerted`; `in` movement with supplier name; if supplier: `total_payable += totalCost`, recompute `balance` and `payment_status` ( balance ≤ 0 → paid; balance ≥ total_payable → outstanding; else partial ).
View modal; **Admin edit** (`grn.edit`): supplier, note, and per-item cost/selling price only (qty/serials locked); cost changes are also written to the linked batch; supplier balance and GRN total are NOT recomputed; audit log.

### 8.13 Stock Transfer (`/stock-transfer`, `/stock-transfer/new`) — Stores → Showroom
List: Transfer No., Transferred By, Date, view. New page: add items (only products with Stores stock), qty, or pick serials for serial products; note. Save (transaction): validate serial units are in Stores; FIFO-consume Stores batches; for each consumed batch create a **new Showroom batch** with the same cost/selling price (`source_batch_id`, note "Transferred via TRF-x"); move serial units' location to showroom; `stores_stock −= qty`, `showroom_stock += qty`; `transfer` movement (from stores to showroom). Admin edit (`stockTransfer.edit`): note only, audit logged.

### 8.14 Stock Out (`/stock-out`, `/stock-out/new`) — stock leaving without a POS sale
List: No., Location, Issued By, To, Reason, Date, view; search "Search no., recipient, or staff…". New page: **Issue From** (Showroom / Stores), **Issued To** ("Customer, technician, or department"), **Reason**: Job / Repair (then pick a job — SearchableSelect "Select a job"), Sale, Other + detail; note; items (qty or serials). Save (transaction): FIFO consume from that location (or mark units `issued` with stock_out_id), decrement counters, store item cost, `out` movement with recipient/reason/job. Admin edit (`stockOut.edit`): recipient, reason, detail, note, job link only; audit logged.

### 8.15 Stock Movements (`/stock-movements`) — read-only
Filters: date range, type (GRN, Transfer, Stock Out, Sale, Sale Cancel, Batch Edit), search "Search product, SKU, or reference…". Table: Date, Type (badge), Product, Qty (+green / −red), Location (or "Stores → Showroom"), By, Recipient / Reason, Reference (the document number, clickable). CSV export.

### 8.16 Brands & Categories
- **Brands**: cards/table with name + description; Add/Edit modal; delete (`brands.delete`).
- **Categories**: main categories each listing their sub-categories; "Add Main Category", "Add Subcategory" (choose main), edit, delete (`categories.delete`; deleting a main should warn about its subs).

### 8.17 Customers (`/customers`) — `customers.view`
Search "Search by name or phone (start of it)" (prefix search). Paginated table: Name, Phone(s), Email, Address, Loyalty points, Joined, edit. Add/Edit modal: Full name*, Phone number*, Phone number 2 (optional), Email (optional), Address (optional). Total count shown.

### 8.18 Suppliers (`/suppliers`, `/suppliers/payments`) — `suppliers.view`
- Top: **outstanding summary** table (Supplier, Balance, Last Payment, Unpaid For — e.g. "45 days").
- Main table: Name, Phone, Total Payable, Amount Paid, Balance, Status (Paid success / Partial warning / Outstanding danger), view. Search "Search by name or phone…". **Add Supplier** (name*, phone*, email, address). Edit contact (`suppliers.editContact`) — never touches balances.
- Supplier view modal: contact info, totals, **Record Payment** (`suppliers.recordPayment`): Amount (> 0 and ≤ balance: "Payment of Rs. X exceeds the outstanding balance of Rs. Y"), Method (Cash / Bank Transfer / Cheque / Other), Reference (e.g. cheque no), Note → `PAY-` record with balance before/after, update supplier amount_paid, balance, status, last_payment_at. **Payment History** table (Payment No., Date, Amount, Method, Balance After, edit). **Edit payment** (`suppliers.editPayment`): amount/method/reference/note; an amount change recalculates the supplier's amount_paid/balance/status (reject overpaying); audit logged. **Send account statement** email (`suppliers.sendStatement` + Admin/Super Admin on server) → updates `last_statement_sent_at`.
- **Supplier Payment Report** page (`/suppliers/payments`): all payments (Payment No., Supplier, Amount, Method, Paid By, Date) with date range + search, and an **Outstanding Balances** table (Supplier, Total Payable, Amount Paid, Balance, Status). CSV export.

### 8.19 Finance (`/finance`) — `finance.view`
Title "Finance" with segmented tabs **Overview / Daily Balance / Shifts / Expenses**.
- **Overview** (default range: 1st of this month → today): KPI cards Revenue, COGS, Gross Profit, Expenses, Net Profit, Margin %. (Revenue = sum of non-cancelled sale totals; COGS = Σ item cost × qty; Gross = Revenue − COGS; Net = Gross − Expenses; Margin = Gross/Revenue.) Payment method breakdown (bars with %, split-aware), **Cashier performance** table (Cashier, Sales, Revenue), **Supplier Payables** table (Supplier, Balance, total). Export CSV.
- **Daily Balance** (cash book): editable Opening balance (store in `shop_settings` or per-browser localStorage as the original did), then per day: Date, Income (sales), Expenses, Net, Closing Balance (running). Export CSV.
- **Shifts**: filters (cashier, open/closed, review status, date), search "Search cashier or shift no…". Table: Shift No., Cashier, Opened, Closed, Float, Cash, Card, KokoPay, Expected, Counted, Variance (red if negative, green if positive), Review badge (Pending / Approved / Flagged). Open shifts older than a day show "open N days" warning. Shift view modal: all figures, cash paid out, force-closed info, **Sales this shift (N)** list. **Force Close (Admin Override)** (Admin/Super Admin, open shifts only): counted cash + note → same calculation, `force_closed = true`, closed_by. **Review** (`finance.reviewShift`, closed shifts): Approve / Flag + note.
- **Expenses**: filters (category, date). Table: Expense No., Category, Amount, Note, Paid By, Drawer (shift no or "—"), Date, delete (`finance.deleteExpense`). **Add Expense** (`finance.addExpense`) modal: Category (Rent, Utilities, Maintenance, Marketing, Other — *Salaries is not selectable here*, it's only created by the Salary module), Amount*, Note ("e.g. July shop rent"), optional **"Paid from cash drawer"** → choose an **open** shift → `shift.cash_expenses_total += amount`. Deleting an expense linked to a still-open shift subtracts it back.

### 8.20 Salary (`/salary`) — `salary.view` (NOT granted by default to non-admins)
Tabs **Issue Payment / Payment History / Employee Setup**.
- **Employee Setup**: table Employee, Role, Salary Setup ("Monthly Rs. X", "Commission Y%", "Hybrid Rs. X + Y%" or "Not configured"), Edit (`salary.manageConfig`) → modal "Salary Setup — {name}": type Monthly / Commission / Hybrid, monthly amount, commission %; also "Clear". This is only a pre-fill default.
- **Issue Payment** (`salary.issue`): Employee select (all users except Super Admin) → type/amount/% pre-filled. For commission/hybrid: a search picker "Search sale invoice no., job no. or customer…" to link specific sales/jobs (only ones with no `commission_payment_id` and not cancelled); commission base auto-totals from linked items (sale total / job repair cost or estimate) but can be typed manually ("e.g. sales total for period"); Commission %; **Amount to Pay** = monthly + base × % (editable); Period ("e.g. August 2026"); Note; optional "paid from cash drawer" open-shift picker. Save (transaction): `SAL-` payment + an automatic **Expense** (category salaries, next `EXP-` number, note "Salary payment SAL-x — Name (Period)", linked both ways); mark linked sales/jobs with `commission_payment_id` (fail if any already claimed); debit the shift's cash expenses if chosen.
- **Payment History**: table Payment No., Employee, Type, Amount, Period, Issued By, Date, actions; view modal with "How this amount was calculated", "Linked sales / jobs", "Note"; **Email payslip** to the employee (server loads the record; sets `email_sent_at`); **Delete** (`salary.delete`): removes the payment + its expense, frees linked sales/jobs, and reverses the shift debit if that shift is still open.

### 8.21 Audit Log (`/audit-log`) — `auditLog.view`
Every Admin edit (bills, jobs, GRNs, transfers, stock outs, supplier payments) writes a row in the **same transaction** with only the changed fields (allow-listed per record type; no row if nothing changed). Page: filter by record type + date range; search "Search record no. or staff…". Table: When, Who, Record (type label + number, e.g. "Bill · INV-00012"), Fields Changed (chips), view → modal listing each field with **before → after**. Read-only.

### 8.22 Settings (`/settings`) — everyone can open; only Admin/Super Admin can change
- **Shop Info**: Shop name*, Phone*, Email, Address → Save (used on all prints, emails and the header).
- **Low Stock Alerts**: list of recipient emails with add ("name@example.com") / remove.
- **Team**: list users (Super Admin hidden from non-Super-Admins) with name, email, role badge, status (Active/Inactive). **Add User** modal: Full Name ("e.g. Kasun Perera"), Email, Role (Manager / Cashier / Technician / Staff; Admin and Super Admin only offered to Super Admin), Password + Confirm (min 6). **Edit User** modal (only for users the viewer may manage, never self): name, role, Active/Inactive, and the **permission toggles grouped by module** (read-only all-checked view for Admin/Super Admin targets when the viewer can't edit them), with "Reset to role defaults". **Delete User** (with the same manage rules; can't delete yourself).
- **Database Usage** (replaces "Firebase Usage"): row counts per table.
- **Data tools** (Super Admin only, replaces "Firebase Collections / Clean Collection"): export any table as CSV/JSON, and "Clean table" (delete all rows, with a typed confirmation) for the transactional tables.

### 8.23 My Profile (`/profile`)
Cards: **Profile Info** (display name, phone → Save), **Change Email** (new email + current password; send a verification link to the new address and change it only after the link is clicked; reject if the email is already used), **Change Password** (current password, new ≥ 6, confirm).

---

## 9. Emails (HTML + plain-text alternative, from `MAIL_FROM_NAME` "M-Fixpro POS")
Create clean branded HTML templates (red `#e30613` header bar with shop name, white body, grey footer with shop phone/email):
1. Password reset OTP — "Your password reset code - {shop}" (big 6-digit code, valid 10 minutes).
2. Change-email confirmation link — "Confirm your new email - {shop}".
3. Bill / receipt — "Your receipt INV-x - {shop}" (items, subtotal, discount, total, payment method) with the PDF attached.
4. Job received / job update — see §8.7.
5. Low stock alert — "Low stock alert - {shop}" (table of product, SKU, stock left, threshold) to all notify emails.
6. Supplier account statement — "Account Statement - {shop}" (total payable, paid, balance).
7. Salary payslip — "Salary Payment SAL-x - {shop}" (type, period, calculation, linked items, amount).
Validate every recipient address with a strict regex (no commas/semicolons/whitespace). All email endpoints load the real record from the DB — never trust amounts or addresses sent by the browser.

---

## 10. Routes / controllers (suggested)
Use resource-style web routes protected by `auth` + an `active` middleware (logs out inactive users) + `can:` middleware per permission. Return JSON for the Alpine-driven POS and pickers (`/api/products/search`, `/api/customers/search`, `/api/jobs/billable`, `/api/products/{id}/batches?location=showroom`, `/api/products/{id}/units?location=showroom`, `POST /sales`, etc.). Put all business logic in service classes (`SaleService`, `StockService` (FIFO, transfer, stock out, GRN), `ShiftService`, `SupplierService`, `SalaryService`, `JobService`, `AuditLogger`, `Numbering`), never in Blade.

## 11. Acceptance checklist
- [ ] Every page/label/column/modal above exists and looks like the spec (colours, fonts, badges, cards, sidebar).
- [ ] Permissions enforced server-side; Access Restricted screen for missing `.view`.
- [ ] POS: shift required, FIFO + serials, split payment balancing, Card/KokoPay %, loyalty, job billing, warranty creation, low-stock email, printable/downloadable/emailable A4 bill.
- [ ] Reverse bill fully restores stock, points, shift totals and removes warranties.
- [ ] GRN → Stores, Transfer → Showroom, Stock Out, with correct counters and movements; supplier balances update.
- [ ] Shifts: expected/variance maths, force close, review; expenses/salary paid from drawer adjust expected cash.
- [ ] Salary creates and deletes its linked expense; commission items can't be paid twice.
- [ ] Audit log written for every admin edit.
- [ ] All document numbers sequential and unique under concurrent use.
- [ ] Mobile responsive; A4 print CSS works.
