<x-layouts.base>
    <x-slot:head>
        <meta name="robots" content="noindex, nofollow">
        <meta name="description" content="Staff guide for {{ $shop->name }} POS.">
    </x-slot:head>

    @php
        $systemName = $shop->name.' POS';
        $sectionIcons = [
            'welcome' => 'book-open', 'roles' => 'users', 'signing-in' => 'log-in', 'finding-your-way' => 'compass',
            'shifts' => 'clock', 'pos' => 'shopping-cart', 'jobs' => 'wrench', 'billing-jobs' => 'receipt',
            'bills' => 'receipt', 'quotations' => 'file-text', 'warranty' => 'shield', 'stock' => 'boxes',
            'contacts' => 'truck', 'finance' => 'wallet', 'salary' => 'banknote', 'audit-log' => 'shield-check',
            'settings' => 'settings', 'normal-day' => 'sun', 'faq' => 'circle-help',
        ];
    @endphp

    {{-- Sticky top bar --}}
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
            <a href="#welcome" class="flex min-w-0 items-center gap-2.5">
                <img src="{{ asset('shop_logo/1_M.png') }}" alt="" class="h-9 w-9 shrink-0 object-contain">
                <span class="min-w-0 leading-tight">
                    <span class="block truncate font-prata text-sm text-ink sm:text-base">{{ $systemName }}</span>
                    <span class="block text-xs text-zinc-500">User Manual</span>
                </span>
            </a>
            <a href="{{ url('/') }}" class="nexora-btn nexora-btn-primary shrink-0 px-3 sm:px-[18px]">
                <x-lucide-log-in class="h-4 w-4" />
                <span>Open<span class="hidden sm:inline"> the system</span></span>
            </a>
        </div>
    </header>

    {{-- Hero --}}
    <div class="border-b border-zinc-200 bg-gradient-to-br from-zinc-100 via-white to-zinc-100">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-light px-3 py-1 text-xs font-medium uppercase tracking-wider text-brand">
                <x-lucide-book-open class="h-3.5 w-3.5" /> Staff guide
            </span>
            <h1 class="mt-4 max-w-3xl font-prata text-3xl leading-tight text-ink sm:text-4xl">How to use {{ $systemName }}</h1>
            <p class="mt-3 max-w-2xl text-base text-zinc-600">
                Everything you need for a working day at the counter and the workbench: shifts, sales, repair jobs, stock,
                customers, suppliers and the money side. Read it once from top to bottom, then come back to the section you need.
            </p>
        </div>
    </div>

    <div x-data="manualToc(@js(array_keys($sections)))"
         class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:grid lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-10 lg:py-12">

        {{-- Table of contents: drawer on phones/tablets, sticky column on desktop --}}
        <details x-ref="mobile" class="nexora-card group mb-8 lg:hidden">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-medium text-ink [&::-webkit-details-marker]:hidden">
                <span class="flex items-center gap-2"><x-lucide-list class="h-4 w-4 text-brand" /> Contents</span>
                <x-lucide-chevron-down class="h-4 w-4 text-zinc-400 transition-transform group-open:rotate-180" />
            </summary>
            <nav aria-label="Contents" class="border-t border-zinc-100 px-2 py-2">
                <ol class="grid gap-0.5 sm:grid-cols-2">
                    @foreach ($sections as $id => $title)
                        <li>
                            <a href="#{{ $id }}" x-on:click="closeMobile()" class="block rounded px-2 py-1.5 text-sm text-zinc-600 hover:bg-brand-light hover:text-brand">
                                <span class="mr-1 text-xs text-zinc-400">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span> {{ $title }}
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>
        </details>

        <aside class="hidden lg:block">
            <nav aria-label="Contents" class="sidebar-nav-scroll sticky top-24 max-h-[calc(100vh-7rem)] overflow-y-auto pr-2">
                <p class="mb-2 px-3 text-xs font-medium uppercase tracking-wider text-zinc-500">Contents</p>
                <ol class="space-y-0.5">
                    @foreach ($sections as $id => $title)
                        <li>
                            <a href="#{{ $id }}"
                               class="block rounded px-3 py-1.5 text-sm transition-colors"
                               x-bind:class="active === @js($id) ? 'bg-brand text-white font-medium' : 'text-zinc-600 hover:text-brand hover:bg-brand-light'">
                                {{ $title }}
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>
        </aside>

        <article class="min-w-0 space-y-10">

            {{-- Welcome --}}
            <x-manual.section id="welcome" :title="$sections['welcome']" :icon="$sectionIcons['welcome']">
                <p>
                    {{ $systemName }} runs the whole shop from one place: the point of sale, repair job notes, bills and quotations,
                    warranties, stock in the back room and on the shop floor, customers, suppliers, cash drawer shifts, expenses and salaries.
                    It works in any browser on a computer, tablet or phone. All amounts are in rupees, shown like <strong>Rs. 12,500</strong>.
                </p>
                <x-manual.bullets>
                    <li>Every change is saved straight away. There is no "save at the end of the day".</li>
                    <li>Several people can use the system at the same time. Bill numbers, job numbers and stock counts never clash.</li>
                    <li>You only see the pages your account is allowed to use. If a page says <strong>Access Restricted</strong>, ask an Admin.</li>
                </x-manual.bullets>
                <x-manual.tip>Pages show the last 30 days by default. Change the <strong>From</strong> / <strong>To</strong> dates at the top of a list to look further back.</x-manual.tip>
            </x-manual.section>

            {{-- Roles --}}
            <x-manual.section id="roles" :title="$sections['roles']" :icon="$sectionIcons['roles']">
                <p>Each account has a role. The role gives a starting set of permissions, and an Admin can switch individual permissions on or off for each person in <strong>Settings → Team</strong>.</p>
                <div class="nexora-card overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200 text-xs font-medium uppercase tracking-wider text-zinc-500">
                                <th class="px-4 py-3">Role</th>
                                <th class="px-4 py-3">What they can do by default</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 align-top">
                            <tr>
                                <td class="w-1/3 px-4 py-3 sm:w-auto"><span class="badge whitespace-normal badge-danger">Super Admin</span></td>
                                <td class="px-4 py-3">Everything, always. Cannot be restricted, is hidden from other users' team list and is never paid through Salary. Manages every role including Admins, and is the only one who can use the Data tools.</td>
                            </tr>
                            <tr>
                                <td class="w-1/3 px-4 py-3 sm:w-auto"><span class="badge whitespace-normal badge-info">Admin</span></td>
                                <td class="px-4 py-3">Everything by default (a Super Admin can switch parts off). Changes shop info and low-stock emails, manages Managers, Cashiers, Technicians and Staff, force-closes shifts and sends supplier statements.</td>
                            </tr>
                            <tr>
                                <td class="w-1/3 px-4 py-3 sm:w-auto"><span class="badge whitespace-normal badge-warning">Manager</span></td>
                                <td class="px-4 py-3">Every page except Salary, plus: create GRNs, stock transfers and stock outs, review closed shifts and add expenses.</td>
                            </tr>
                            <tr>
                                <td class="w-1/3 px-4 py-3 sm:w-auto"><span class="badge whitespace-normal badge-default">Cashier · Technician · Staff</span></td>
                                <td class="px-4 py-3">Every page except Salary, plus adding / editing products and editing supplier contact details. Everything else (reversing bills, editing documents, deleting, recording payments…) must be switched on by an Admin.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <x-manual.warning>Permissions are checked by the server on every click, not just hidden in the menu. A change made by an Admin applies on the person's very next click; they don't need to sign out.</x-manual.warning>
            </x-manual.section>

            {{-- Signing in --}}
            <x-manual.section id="signing-in" :title="$sections['signing-in']" :icon="$sectionIcons['signing-in']">
                <x-manual.steps>
                    <li>Open the system and enter your <strong>Email</strong> and <strong>Password</strong>. Use the eye icon to check what you typed.</li>
                    <li>Wait for the security check box to show a tick, then press <strong>Sign in</strong>.</li>
                    <li>You land on the Dashboard (or the first page you are allowed to see).</li>
                </x-manual.steps>
                <p class="font-medium text-ink">Forgot your password?</p>
                <x-manual.steps>
                    <li>Press <strong>Forgot password?</strong>, enter your email and press <strong>Send code</strong>.</li>
                    <li>Check your inbox for a 6-digit code. It is valid for <strong>10 minutes</strong>. You can ask for a new one after 60 seconds.</li>
                    <li>Enter the code, your new password (at least 6 characters) twice, and press <strong>Reset password</strong>. Then sign in with the new password.</li>
                </x-manual.steps>
                <x-manual.warning>After <strong>5 wrong codes</strong> the code stops working and you must request a new one. If your account has been switched to Inactive you will see "Your account has been disabled": speak to an Admin.</x-manual.warning>
            </x-manual.section>

            {{-- Finding your way --}}
            <x-manual.section id="finding-your-way" :title="$sections['finding-your-way']" :icon="$sectionIcons['finding-your-way']">
                <x-manual.bullets>
                    <li><strong>Sidebar</strong> (left on a computer; tap the ☰ menu on a phone): Dashboard, POS / New Sale, Jobs, Services, then the groups <strong>Sales</strong>, <strong>Inventory</strong>, <strong>Contacts</strong>, <strong>Finance</strong> and <strong>System</strong>. Tap a group to open it.</li>
                    <li><strong>Your name</strong> at the bottom of the sidebar opens <strong>My Profile</strong>: change your display name, phone, email (confirmed by a link sent to the new address) or password.</li>
                    <li><strong>Sign out</strong> is just below your name. Always sign out on a shared computer.</li>
                    <li>The live clock at the top right shows the date and time the system uses for bills and shifts.</li>
                    <li>Lists show 10 rows per page with <strong>Prev / Next</strong> at the bottom. The eye icon opens a record, the pencil edits it and the bin deletes it.</li>
                </x-manual.bullets>
            </x-manual.section>

            {{-- Shifts --}}
            <x-manual.section id="shifts" :title="$sections['shifts']" :icon="$sectionIcons['shifts']">
                <p>A shift is your cash drawer session. <strong>You cannot check out a sale without an open shift.</strong> Each cashier has their own shift, and only one at a time.</p>
                <x-manual.steps>
                    <li>On <strong>POS / New Sale</strong> tap the amber pill <strong>"No open shift — tap to open"</strong>.</li>
                    <li>Count the cash in the drawer, type it as the <strong>Opening cash float</strong>, add an optional note (e.g. "Morning shift") and open it.</li>
                    <li>While it is open, the grey pill shows the shift number and your Cash and Card totals so far.</li>
                    <li>At the end, tap <strong>Close Shift</strong>, count the drawer again, type the <strong>counted cash</strong> and a note if it doesn't match.</li>
                    <li>The <strong>Shift Closed</strong> window shows Expected, Counted and Variance.</li>
                </x-manual.steps>
                <div class="nexora-card px-4 py-3 text-sm">
                    <p><strong>Expected cash</strong> = opening float + cash sales − cash paid out of the drawer (expenses and salaries).</p>
                    <p><strong>Variance</strong> = counted − expected. <span class="text-green-700">0 is exact</span>, <span class="text-brand">minus means short</span>, plus means over.</p>
                </div>
                <x-manual.warning>Only the cashier who opened a shift can close it. If someone left without closing, an Admin can <strong>Force Close</strong> it from Finance → Shifts. A Manager or Admin then reviews every closed shift (Approve or Flag).</x-manual.warning>
            </x-manual.section>

            {{-- POS --}}
            <x-manual.section id="pos" :title="$sections['pos']" :icon="$sectionIcons['pos']">
                <p>The POS sells only from the <strong>Showroom</strong>. Products that have stock only in Stores don't appear until they are transferred.</p>
                <x-manual.steps>
                    <li><strong>Add products.</strong> Search by name, SKU or barcode, or filter by category. With a barcode scanner, scan and the product is added at once. If the product has more than one batch, choose <strong>Auto (FIFO)</strong> (oldest stock first) or a specific batch. A red "Selling at a loss" label means that batch's price is below its cost.</li>
                    <li><strong>Serial-numbered items</strong> (laptops, phones…) open a list of the units in the Showroom. Tick the exact serial numbers you are handing over.</li>
                    <li><strong>Add services</strong> on the Services tab. Check the price and fill in the service's fields; required fields must be filled.</li>
                    <li><strong>Pick the customer</strong> (search by name or phone, or <strong>New Customer</strong>). Leave it as "Walk-in customer" if they don't want to be registered.</li>
                    <li>Adjust quantities, per-item <strong>Discount</strong>, the <strong>Bill Discount</strong> and any <strong>Redeem Points</strong>.</li>
                    <li>Choose the payment method: <strong>Cash, Card, Transfer or KokoPay</strong>. For cash, type the amount tendered to see the change.</li>
                    <li>Press <strong>Checkout — Rs. …</strong>. Then <strong>Print</strong>, <strong>Download</strong> or <strong>Email</strong> the A4 bill, and press <strong>New Sale</strong>.</li>
                </x-manual.steps>
                <p class="font-medium text-ink">Split payments</p>
                <p>Tap more than one method (e.g. Cash + Card) and type an amount for each. The line under them must say <strong class="text-green-700">Balanced</strong> before you can check out. KokoPay can't be combined with another method.</p>
                <p class="font-medium text-ink">Card and KokoPay charges</p>
                <p>
                    Choosing <strong>KokoPay</strong>, or <strong>Card</strong> on its own, asks for a surcharge %. The system raises every item price on the bill by that %, so the customer sees
                    higher item prices instead of a separate fee line. A small staff-only note shows the surcharge. In a split, the % only applies to the card part, and the card amount is filled in for you: swipe that amount.
                </p>
                <p class="font-medium text-ink">Loyalty points</p>
                <x-manual.bullets>
                    <li>Registered customers earn <strong>1 point per Rs. 100</strong> spent (after discounts).</li>
                    <li><strong>1 point = Rs. 1</strong> off a later bill.</li>
                    <li>Walk-in sales don't earn points.</li>
                </x-manual.bullets>
                <x-manual.tip>Items with a warranty automatically get a warranty record (one per serial number) the moment the sale is saved. You don't need to do anything else.</x-manual.tip>
                <x-manual.warning>If the system says "Not enough Showroom stock … (N more in Stores — transfer to Showroom first.)", do a <a href="#stock" class="font-medium underline">Stock Transfer</a> before selling.</x-manual.warning>
            </x-manual.section>

            {{-- Jobs --}}
            <x-manual.section id="jobs" :title="$sections['jobs']" :icon="$sectionIcons['jobs']">
                <p>Every device that comes in for repair gets a <strong>job note</strong> with its own number (JOB-00001…).</p>
                <x-manual.flow label="Repair job status flow" :steps="[
                    ['label' => 'Job Pending', 'caption' => 'Booked in', 'class' => 'border-amber-200 bg-amber-50 text-amber-800'],
                    ['label' => 'Ongoing Job', 'caption' => 'On the bench', 'class' => 'border-zinc-300 bg-zinc-100 text-zinc-700'],
                    ['label' => 'Job Done', 'caption' => 'Ready for pickup', 'class' => 'border-green-200 bg-green-50 text-green-800'],
                    ['label' => 'Delivered', 'caption' => 'Billed & collected', 'class' => 'border-blue-200 bg-blue-50 text-blue-800'],
                ]" />
                <p>A job that can't be fixed is marked <span class="badge badge-danger">Can't Repair</span> instead.</p>
                <p class="font-medium text-ink">Booking a device in</p>
                <x-manual.steps>
                    <li>Go to <strong>Jobs → New Job</strong>. The next job number is filled in; you can type your own (e.g. from a paper book) as long as it isn't already used.</li>
                    <li>Search for the customer, or type a new one (name and mobile are required). A new customer is saved automatically.</li>
                    <li>Choose the device type and enter brand, model, serial number and colour. Use the quick chips to list parts inside (RAM, SSD…) with spec and serial.</li>
                    <li>Describe the <strong>fault</strong>, tick the accessories handed over and the physical condition (scratches, cracks, liquid damage…).</li>
                    <li>Assign a technician, add services &amp; charges (mark a line <strong>Free</strong> with a reason, e.g. Warranty), the estimated cost, any advance paid and the expected delivery date.</li>
                    <li>Press <strong>Save Job Note</strong>. Print or download the A4 job note for the customer to sign, and send the confirmation email if they gave an address.</li>
                </x-manual.steps>
                <p class="font-medium text-ink">Updating a job</p>
                <x-manual.steps>
                    <li>Open the job (eye icon) and go to <strong>Update Job Status</strong>.</li>
                    <li>Pick the new status and write what was done. For <strong>Job Done</strong>, enter the final repair price, or press <strong>Use services total</strong>.</li>
                    <li>Press <strong>Save Update</strong>. Every update is kept in the <strong>Job History</strong>. Offer to email the customer.</li>
                </x-manual.steps>
                <x-manual.tip>Search jobs by number ("123" finds JOB-00123), customer name or phone. <strong>Export Report</strong> downloads the filtered jobs with their full history as a spreadsheet.</x-manual.tip>
            </x-manual.section>

            {{-- Billing a finished job --}}
            <x-manual.section id="billing-jobs" :title="$sections['billing-jobs']" :icon="$sectionIcons['billing-jobs']">
                <x-manual.steps>
                    <li>On POS / New Sale press <strong>Find Job to Bill</strong>. Only jobs marked <strong>Job Done</strong> are listed.</li>
                    <li>Pick the job. Its services appear on the bill, free lines show "Free", and the job's customer becomes the bill customer.</li>
                    <li>If the final repair price differs from the services total, an "Other repair charges" or "Repair cost adjustment" line is added. Any advance is taken off as <strong>"Less: advance paid"</strong>.</li>
                    <li>Add any products sold at the same time and check out as normal.</li>
                </x-manual.steps>
                <x-manual.tip>Checking out automatically moves the job to <strong>Delivered</strong> with the invoice number in its history. There's no need to update the job separately.</x-manual.tip>
            </x-manual.section>

            {{-- Bills --}}
            <x-manual.section id="bills" :title="$sections['bills']" :icon="$sectionIcons['bills']">
                <p><strong>Sales → Bills</strong> lists every invoice. Open one to see items, services, totals, payments, cashier and shift, and to <strong>Print</strong>, <strong>Download</strong> or <strong>Email</strong> it again.</p>
                <x-manual.bullets>
                    <li><strong>Edit Bill</strong> (if allowed) changes only the customer name, phone, email, note and payment method. Items and amounts can't be edited.</li>
                    <li><strong>Reverse Bill</strong> (if allowed) needs a reason. It puts the stock back in the Showroom, takes the sale off the shift (if still open), reverses loyalty points and removes the bill's warranties. The bill stays in the list marked <span class="badge badge-danger">Cancelled</span>.</li>
                </x-manual.bullets>
                <x-manual.warning>A reversal can't be undone, and a cancelled bill can't be edited. To correct a wrong sale, reverse it and ring it up again.</x-manual.warning>
            </x-manual.section>

            {{-- Quotations --}}
            <x-manual.section id="quotations" :title="$sections['quotations']" :icon="$sectionIcons['quotations']">
                <x-manual.steps>
                    <li>Go to <strong>Sales → Quotations → New Quotation</strong>.</li>
                    <li>Enter the customer (or leave it as Walk-in), then <strong>Add Item</strong>: search a product or type any item name. Set qty, price and discount.</li>
                    <li>Add an overall discount, notes / terms and the <strong>Valid until</strong> date, and save.</li>
                    <li>Print or download it for the customer. When they reply, open it and press <strong>Mark Accepted</strong> or <strong>Mark Rejected</strong>.</li>
                </x-manual.steps>
                <x-manual.tip>Quotations never touch stock. Nothing is reserved until you actually sell it on the POS.</x-manual.tip>
            </x-manual.section>

            {{-- Warranty --}}
            <x-manual.section id="warranty" :title="$sections['warranty']" :icon="$sectionIcons['warranty']">
                <p><strong>Sales → Warranty</strong> lists every warranty created by sales. Search by customer or product.</p>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-success">Active</span>
                    <span class="badge badge-warning">Expiring Soon · N days</span>
                    <span class="badge badge-danger">Expired</span>
                    <span class="badge badge-default">Claimed</span>
                </div>
                <x-manual.steps>
                    <li>When a customer brings an item back, find it (the serial number is the quickest).</li>
                    <li>Check it is still Active, then press <strong>Claim Warranty</strong> and note what was done (e.g. "Screen replaced under warranty").</li>
                </x-manual.steps>
                <x-manual.warning>The printed warranty terms cover manufacturing defects only: not physical, liquid, electrical or accidental damage, software work, or items with a removed or damaged warranty sticker.</x-manual.warning>
            </x-manual.section>

            {{-- Stock & products --}}
            <x-manual.section id="stock" :title="$sections['stock']" :icon="$sectionIcons['stock']">
                <p>Stock lives in two places:</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="nexora-card p-4">
                        <p class="flex items-center gap-2 font-prata text-ink"><x-lucide-warehouse class="h-4 w-4 text-brand" /> Stores</p>
                        <p class="mt-1 text-sm text-zinc-600">The back room. New stock arrives here by default. The POS <strong>cannot</strong> sell from Stores.</p>
                    </div>
                    <div class="nexora-card p-4">
                        <p class="flex items-center gap-2 font-prata text-ink"><x-lucide-store class="h-4 w-4 text-brand" /> Showroom</p>
                        <p class="mt-1 text-sm text-zinc-600">The shop floor. The POS sells only from here.</p>
                    </div>
                </div>
                <x-manual.flow label="Stock flow" :steps="[
                    ['label' => 'GRN', 'caption' => 'Goods arrive → Stores', 'class' => 'border-zinc-300 bg-white text-ink'],
                    ['label' => 'Stock Transfer', 'caption' => 'Stores → Showroom', 'class' => 'border-zinc-300 bg-white text-ink'],
                    ['label' => 'Sale', 'caption' => 'Showroom → customer', 'class' => 'border-brand bg-brand-light text-brand'],
                ]" />
                <x-manual.bullets>
                    <li><strong>Products</strong>: name, brand, SKU, barcode, categories, selling price, low-stock level, warranty months and whether it is <strong>serial-tracked</strong>. Stock shows "Stores X · Showroom Y".</li>
                    <li><strong>Batches</strong>: each delivery is a batch with its own cost (and optional selling price). Sales use the <strong>oldest batch first (FIFO)</strong>, so profit is worked out on the real cost.</li>
                    <li><strong>GRN</strong> (Goods Received): choose the supplier (optional), location, then each product, cost price, optional selling price and qty (or one serial per unit). The supplier's balance goes up by the GRN total.</li>
                    <li><strong>Stock Transfer</strong>: moves stock from Stores to the Showroom (pick serials for serial-tracked items).</li>
                    <li><strong>Stock Out</strong>: stock leaving without a sale: parts used on a repair job, a sale made elsewhere or anything else, from either location, with who it went to.</li>
                    <li><strong>Stock Movements</strong>: a read-only history of every stock change, with a CSV export.</li>
                    <li><strong>Brands</strong> and <strong>Categories</strong> (main → sub) keep the catalogue tidy.</li>
                </x-manual.bullets>
                <x-manual.tip>When a sale drops a product to its low-stock level, the addresses in <strong>Settings → Low Stock Alerts</strong> get one email. The alert resets when the product is restocked.</x-manual.tip>
                <x-manual.warning>Never "fix" stock by selling or by editing products. Use a GRN to add stock, a Stock Out to remove it, and the batch edit (if allowed) to correct a miscount. Every change is recorded.</x-manual.warning>
            </x-manual.section>

            {{-- Customers & suppliers --}}
            <x-manual.section id="contacts" :title="$sections['contacts']" :icon="$sectionIcons['contacts']">
                <p class="font-medium text-ink">Customers</p>
                <p>Search by the start of the name or phone number. Add or edit name, phone(s), email and address; the list shows each customer's loyalty points.</p>
                <p class="font-medium text-ink">Suppliers</p>
                <x-manual.bullets>
                    <li>The top table lists suppliers you still owe, with how long the balance has been unpaid.</li>
                    <li>Open a supplier to see totals and <strong>Payment History</strong>, and to <strong>Record Payment</strong> (cash, bank transfer, cheque or other; a payment can't be more than the balance).</li>
                    <li>Status: <span class="badge badge-success">Paid</span> <span class="badge badge-warning">Partial</span> <span class="badge badge-danger">Outstanding</span>.</li>
                    <li>Admins can email the supplier an <strong>account statement</strong>. <strong>Suppliers → Payments</strong> is the full payment report with a CSV export.</li>
                </x-manual.bullets>
            </x-manual.section>

            {{-- Finance --}}
            <x-manual.section id="finance" :title="$sections['finance']" :icon="$sectionIcons['finance']">
                <x-manual.bullets>
                    <li><strong>Overview</strong>: Revenue, cost of goods, gross profit, expenses, net profit and margin for the chosen dates, with payment methods, cashier performance and supplier payables.</li>
                    <li><strong>Daily Balance</strong>: a cash book. Set the opening balance and see income, expenses and the running closing balance per day.</li>
                    <li><strong>Shifts</strong>: every shift with its float, takings, expected / counted cash and variance. Open one to see its sales, <strong>Approve</strong> or <strong>Flag</strong> it, or (Admins) force-close it.</li>
                    <li><strong>Expenses</strong>: add rent, utilities, maintenance, marketing or other costs. Tick <strong>Paid from cash drawer</strong> and choose an open shift if the money came out of the till.</li>
                </x-manual.bullets>
                <x-manual.tip>Cancelled bills are left out of every finance figure.</x-manual.tip>
                <x-manual.warning>Cash taken from the drawer for an expense lowers that shift's expected cash. If you forget to tick "Paid from cash drawer", the shift will close short.</x-manual.warning>
            </x-manual.section>

            {{-- Salary --}}
            <x-manual.section id="salary" :title="$sections['salary']" :icon="$sectionIcons['salary']">
                <p>Only people given Salary access can open this page.</p>
                <x-manual.steps>
                    <li><strong>Employee Setup</strong>: set each person's default pay: Monthly, Commission % or Hybrid (both).</li>
                    <li><strong>Issue Payment</strong>: choose the employee. For commission, search and link the sales / jobs being paid for. The base and the amount are worked out but can be changed.</li>
                    <li>Enter the period (e.g. "August 2026"), a note, and optionally the open shift it was paid from. Save.</li>
                </x-manual.steps>
                <x-manual.bullets>
                    <li>Each payment (SAL-…) automatically creates a matching <strong>Salaries</strong> expense.</li>
                    <li>A sale or job can only be paid commission once.</li>
                    <li><strong>Payment History</strong> shows how each amount was worked out, and can <strong>email the payslip</strong>. Deleting a payment also removes its expense and frees its sales / jobs.</li>
                </x-manual.bullets>
            </x-manual.section>

            {{-- Audit log --}}
            <x-manual.section id="audit-log" :title="$sections['audit-log']" :icon="$sectionIcons['audit-log']">
                <p>
                    Every edit to an existing bill, job, GRN, stock transfer, stock out or supplier payment is written to <strong>System → Audit Log</strong>:
                    who, when, which record (e.g. "Bill · INV-00012") and each changed field <strong>before → after</strong>. The log is read-only. Nobody can change it.
                </p>
            </x-manual.section>

            {{-- Settings --}}
            <x-manual.section id="settings" :title="$sections['settings']" :icon="$sectionIcons['settings']">
                <p>Everyone can open Settings. Only Admins and the Super Admin can change things.</p>
                <x-manual.bullets>
                    <li><strong>Shop Info</strong>: name, phone, email and address used on every printout, email and the header.</li>
                    <li><strong>Low Stock Alerts</strong>: the email addresses that get low-stock warnings.</li>
                    <li><strong>Team</strong>: <strong>Add User</strong> (name, email, role, password), edit a user's role, set them Active / Inactive, and switch individual permissions on or off (or <strong>Reset to role defaults</strong>).</li>
                    <li><strong>Database Usage</strong> shows how many records each table holds. <strong>Data tools</strong> (Super Admin only) export or clear tables.</li>
                </x-manual.bullets>
                <x-manual.warning>When someone leaves, set their account to <strong>Inactive</strong> instead of deleting it, so their name stays on old bills and reports. Nobody can edit or delete their own account here.</x-manual.warning>
            </x-manual.section>

            {{-- A normal day --}}
            <x-manual.section id="normal-day" :title="$sections['normal-day']" :icon="$sectionIcons['normal-day']">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="nexora-card p-4">
                        <p class="flex items-center gap-2 font-prata text-ink"><x-lucide-sunrise class="h-4 w-4 text-brand" /> Morning</p>
                        <x-manual.bullets class="mt-2 text-sm">
                            <li>Sign in and count the drawer.</li>
                            <li>Open your shift with that float.</li>
                            <li>Check the Dashboard: overdue repairs, devices ready for pickup, low stock.</li>
                            <li>Transfer stock the shop floor needs.</li>
                        </x-manual.bullets>
                    </div>
                    <div class="nexora-card p-4">
                        <p class="flex items-center gap-2 font-prata text-ink"><x-lucide-sun class="h-4 w-4 text-brand" /> During the day</p>
                        <x-manual.bullets class="mt-2 text-sm">
                            <li>Ring up sales and bill finished jobs on the POS.</li>
                            <li>Book in devices with a job note, and update jobs as work moves on.</li>
                            <li>Receive deliveries with a GRN.</li>
                            <li>Record any cash paid out as an expense from the drawer.</li>
                        </x-manual.bullets>
                    </div>
                    <div class="nexora-card p-4">
                        <p class="flex items-center gap-2 font-prata text-ink"><x-lucide-moon class="h-4 w-4 text-brand" /> Closing</p>
                        <x-manual.bullets class="mt-2 text-sm">
                            <li>Count the drawer and close your shift.</li>
                            <li>Explain any variance in the close note.</li>
                            <li>Manager reviews the closed shifts.</li>
                            <li>Sign out.</li>
                        </x-manual.bullets>
                    </div>
                </div>
            </x-manual.section>

            {{-- FAQ --}}
            <x-manual.section id="faq" :title="$sections['faq']" :icon="$sectionIcons['faq']">
                <div class="nexora-card">
                    <x-manual.faq question="A product is in stock but doesn't show on the POS.">
                        <p>The POS only lists <strong>active</strong> products with <strong>Showroom</strong> stock. Check the product's Stores / Showroom numbers and do a Stock Transfer.</p>
                    </x-manual.faq>
                    <x-manual.faq question="The Checkout button is greyed out.">
                        <p>Either no shift is open (open one at the top of the POS), or a split payment isn't balanced (each amount must be above 0 and they must add up to the total).</p>
                    </x-manual.faq>
                    <x-manual.faq question="I sold the wrong item or the wrong price.">
                        <p>Ask someone with "Reverse / cancel a sale" permission to <strong>Reverse</strong> the bill from Sales → Bills (a reason is required), then ring up the sale again.</p>
                    </x-manual.faq>
                    <x-manual.faq question="I can't close my shift.">
                        <p>Only the cashier who opened a shift can close it. If that person isn't here, an Admin can force-close it from Finance → Shifts.</p>
                    </x-manual.faq>
                    <x-manual.faq question="The customer didn't get the email.">
                        <p>Check the email address saved on the bill, job or customer. It must be one plain address with no spaces, commas or semicolons. Fix it with Edit, ask them to check spam, then send again.</p>
                    </x-manual.faq>
                    <x-manual.faq question="The job I want to bill isn't in Find Job to Bill.">
                        <p>Only jobs with status <strong>Job Done</strong> can be billed. Open the job and update its status first. Jobs already Delivered have been billed.</p>
                    </x-manual.faq>
                    <x-manual.faq question="My shift closed short or over.">
                        <p>Check for cash paid out that wasn't recorded as a drawer expense, change given wrongly, or a card / transfer sale entered as cash. Explain it in the close note; a Manager will review it.</p>
                    </x-manual.faq>
                    <x-manual.faq question="I see “Access Restricted”.">
                        <p>Your account doesn't have permission for that page. Ask an Admin to switch it on in Settings → Team. It works on your next click.</p>
                    </x-manual.faq>
                    <x-manual.faq question="I forgot my password.">
                        <p>Use <strong>Forgot password?</strong> on the sign-in page to get a 6-digit code by email. See <a href="#signing-in" class="font-medium text-brand underline">Signing in</a>.</p>
                    </x-manual.faq>
                </div>
            </x-manual.section>

            <footer class="border-t border-zinc-200 pt-6 text-xs text-zinc-500">
                © {{ now()->year }} {{ $shop->name }} · Design &amp; Developed by plexCode
            </footer>
        </article>
    </div>
</x-layouts.base>
