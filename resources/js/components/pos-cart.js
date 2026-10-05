import { sendJson } from './record-form';
import { formatMoney } from '../money';
import { downloadPdf, pdfBase64 } from '../bill-pdf';

const METHOD_ORDER = ['cash', 'card', 'transfer', 'kokopay'];

const METHOD_LABELS = { cash: 'Cash', card: 'Card', transfer: 'Transfer', kokopay: 'KokoPay' };

const RUPEES_PER_POINT = 100;

/** Whole cents of a rupee amount (typed or from JSON). */
const cents = (value) => Math.round((Number(value) || 0) * 100);

const blankCustomerForm = () => ({ name: '', phone: '', email: '' });

/**
 * POS / New Sale (SPEC §8.4): the whole cart as one component. The catalogue, services, category filters and
 * the cashier's open shift come from the page; batches, serial units, customers, shifts and checkout go through
 * the JSON endpoints in `urls`.
 *
 * Bill maths mirrors App\Services\SaleService::quote() step for step (whole cents, Math.round = floor(x + 0.5)),
 * and the server re-checks the total it is sent.
 *
 * A finished job ("Find Job to Bill") joins the bill as `job` with the billable lines worked out by the server
 * (App\Services\JobService::billableLines); paid lines take the surcharge, free and negative lines don't.
 */
export default function posCart({ products, services, mainCategories, shift, urls }) {
    let nextKey = 1;
    let customerSearchTimer = null;
    let jobSearchTimer = null;

    return {
        money: formatMoney,
        methodLabels: METHOD_LABELS,

        products,
        services,
        mainCategories,
        shift,

        tab: 'products',
        search: '',
        serviceSearch: '',
        mainCategoryId: '',
        subCategoryId: '',
        notice: '',

        lines: [],
        serviceLines: [],
        customer: null,
        billDiscount: '',
        redeemPoints: '',
        methods: ['cash'],
        legs: { cash: '', card: '', transfer: '' },
        tendered: '',
        cardPercent: null,
        kokopayPercent: null,
        errors: {},
        processing: false,

        openShiftModal: false,
        openShiftForm: { opening_float: '', note: '' },
        closeShiftModal: false,
        closeShiftForm: { counted_cash: '', note: '' },
        shiftErrors: {},
        shiftSaving: false,
        shiftResultModal: false,
        shiftResult: null,

        batchModal: false,
        batchProduct: null,
        batches: [],
        batchLoading: false,
        batchError: '',

        serialModal: false,
        serialProduct: null,
        serialUnits: [],
        serialSelected: [],
        serialLoading: false,
        serialError: '',

        serviceModal: false,
        serviceDraft: null,

        customerModal: false,
        customerQuery: '',
        customerResults: [],
        customerLoading: false,
        customerCreating: false,
        customerSaving: false,
        customerForm: blankCustomerForm(),
        customerErrors: {},

        job: null,
        jobModal: false,
        jobQuery: '',
        jobResults: [],
        jobLoading: false,
        jobError: '',

        chargeModal: false,
        chargeMethod: 'card',
        chargeInput: '',
        chargeError: '',

        completeModal: false,
        completed: null,
        billHtml: '',
        billBusy: '',
        billMessage: '',
        billError: '',

        init() {
            this.$watch('mainCategoryId', () => {
                this.subCategoryId = '';
            });

            this.$watch('customerQuery', () => this.searchCustomers());
            this.$watch('jobQuery', () => this.searchJobs());

            // However Sale Complete is closed, the sold cart must not stay around to be checked out twice.
            this.$watch('completeModal', (open) => {
                if (! open && this.completed) {
                    this.newSale();
                }
            });
        },

        // ── Catalogue ──────────────────────────────────────────────────────────

        get listedProducts() {
            return this.products.filter((product) => product.showroom > 0);
        },

        get filteredProducts() {
            const term = this.search.trim().toLowerCase();

            return this.listedProducts.filter((product) => (! this.mainCategoryId || String(product.main_category_id) === String(this.mainCategoryId))
                && (! this.subCategoryId || String(product.sub_category_id) === String(this.subCategoryId))
                && (term === ''
                    || product.name.toLowerCase().includes(term)
                    || product.sku.toLowerCase().includes(term)
                    || (product.barcode ?? '').toLowerCase().includes(term)));
        },

        get subCategories() {
            return this.mainCategories.find((main) => String(main.id) === String(this.mainCategoryId))?.subs ?? [];
        },

        get filteredServices() {
            const term = this.serviceSearch.trim().toLowerCase();

            return this.services.filter((service) => term === '' || service.name.toLowerCase().includes(term));
        },

        clearFilters() {
            this.search = '';
            this.mainCategoryId = '';
            this.subCategoryId = '';
        },

        /**
         * Barcode scanners type the code and press Enter: an exact barcode / SKU match goes straight into the cart.
         */
        scanSearch() {
            const code = this.search.trim().toLowerCase();

            if (code === '') {
                return;
            }

            const match = this.listedProducts.find((product) => (product.barcode ?? '').toLowerCase() === code || product.sku.toLowerCase() === code);

            if (match) {
                this.search = '';
                this.pickProduct(match);
            }
        },

        findProduct(id) {
            return this.products.find((product) => product.id === id) ?? null;
        },

        inCart(productId) {
            return this.lines.filter((line) => line.product_id === productId).reduce((sum, line) => sum + line.qty, 0);
        },

        available(productId) {
            return (this.findProduct(productId)?.showroom ?? 0) - this.inCart(productId);
        },

        serviceCount(serviceId) {
            return this.serviceLines.filter((line) => line.service_id === serviceId).length;
        },

        flash(message) {
            this.notice = message;
            setTimeout(() => {
                if (this.notice === message) {
                    this.notice = '';
                }
            }, 3500);
        },

        // ── Adding products ────────────────────────────────────────────────────

        pickProduct(product) {
            if (this.available(product.id) < 1) {
                this.flash(`All ${product.showroom} of "${product.name}" in Showroom are already in the cart.`);

                return;
            }

            if (product.track_serial) {
                this.openSerialPicker(product);
            } else {
                this.openBatchPicker(product);
            }
        },

        async fetchJson(template, productId) {
            const url = new URL(template.replace('__PRODUCT__', productId), window.location.origin);
            url.searchParams.set('location', 'showroom');

            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (! response.ok) {
                throw new Error(String(response.status));
            }

            return response.json();
        },

        async openBatchPicker(product) {
            this.batchProduct = product;
            this.batches = [];
            this.batchError = '';
            this.batchLoading = true;

            try {
                const { batches } = await this.fetchJson(urls.batches, product.id);

                if (batches.length === 0) {
                    this.addBatchLine(product, null);

                    return;
                }

                this.batches = batches;
                this.batchModal = true;
            } catch (error) {
                this.flash('Could not load the batches. Please try again.');
            } finally {
                this.batchLoading = false;
            }
        },

        chooseBatch(batch) {
            if (this.addBatchLine(this.batchProduct, batch)) {
                this.batchModal = false;
            }
        },

        addBatchLine(product, batch) {
            if (this.available(product.id) < 1) {
                this.batchError = `No more "${product.name}" in Showroom.`;

                return false;
            }

            const batchId = batch?.id ?? null;
            const existing = this.lines.find((line) => ! line.track_serial && line.product_id === product.id && line.batch_id === batchId);

            if (existing) {
                existing.qty++;
            } else {
                this.lines.push({
                    key: nextKey++,
                    product_id: product.id,
                    name: product.name,
                    sku: product.sku,
                    track_serial: false,
                    batch_id: batchId,
                    batchCost: batch?.cost_price ?? null,
                    basePrice: batch?.selling_price ?? product.price,
                    qty: 1,
                    discount: '',
                    units: [],
                });
            }

            return true;
        },

        async openSerialPicker(product) {
            this.serialProduct = product;
            this.serialUnits = [];
            this.serialSelected = [];
            this.serialError = '';
            this.serialLoading = true;
            this.serialModal = true;

            try {
                const { units } = await this.fetchJson(urls.units, product.id);
                const taken = new Set(this.lines.flatMap((line) => line.units.map((unit) => unit.id)));

                this.serialUnits = units.filter((unit) => ! taken.has(unit.id));
            } catch (error) {
                this.serialError = 'Could not load the serial numbers. Please try again.';
            } finally {
                this.serialLoading = false;
            }
        },

        toggleAllSerials() {
            this.serialSelected = this.serialSelected.length === this.serialUnits.length ? [] : this.serialUnits.map((unit) => unit.id);
        },

        /**
         * Picked units join cart lines by their selling price (batch price ?? product price).
         */
        addSerials() {
            const product = this.serialProduct;
            const picked = this.serialUnits.filter((unit) => this.serialSelected.includes(unit.id));

            if (picked.length === 0) {
                this.serialError = 'Tick at least one serial number.';

                return;
            }

            picked.forEach((unit) => {
                const price = unit.selling_price ?? product.price;
                const existing = this.lines.find((line) => line.track_serial && line.product_id === product.id && cents(line.basePrice) === cents(price));
                const entry = { id: unit.id, serial_number: unit.serial_number, batch_id: unit.batch_id };

                if (existing) {
                    existing.units.push(entry);
                    existing.qty = existing.units.length;
                } else {
                    this.lines.push({
                        key: nextKey++,
                        product_id: product.id,
                        name: product.name,
                        sku: product.sku,
                        track_serial: true,
                        batch_id: null,
                        batchCost: null,
                        basePrice: price,
                        qty: 1,
                        discount: '',
                        units: [entry],
                    });
                }
            });

            this.serialModal = false;
        },

        lineDetail(line) {
            if (line.track_serial) {
                return `Serial: ${line.units.map((unit) => unit.serial_number).join(', ')}`;
            }

            return line.batch_id === null ? 'Auto (FIFO)' : `Batch · ${this.money(line.batchCost)}/unit cost`;
        },

        increase(line) {
            if (line.track_serial) {
                this.openSerialPicker(this.findProduct(line.product_id));

                return;
            }

            if (this.available(line.product_id) < 1) {
                this.flash(`Only ${this.findProduct(line.product_id)?.showroom ?? 0} of "${line.name}" in Showroom.`);

                return;
            }

            line.qty++;
        },

        decrease(line) {
            if (line.track_serial) {
                line.units.pop();
                line.qty = line.units.length;

                if (line.qty === 0) {
                    this.removeLine(line);
                }

                return;
            }

            if (line.qty > 1) {
                line.qty--;
            }
        },

        removeLine(line) {
            this.lines = this.lines.filter((other) => other.key !== line.key);
        },

        // ── Services ───────────────────────────────────────────────────────────

        openService(service, line = null) {
            const values = Object.fromEntries((service.custom_fields ?? []).map((field) => [
                field.id,
                line?.values?.[field.id] ?? (field.type === 'checkbox' ? false : ''),
            ]));

            this.serviceDraft = {
                key: line?.key ?? null,
                service,
                price: line ? line.basePrice : service.default_price,
                values,
                errors: {},
            };
            this.serviceModal = true;
        },

        editServiceLine(line) {
            const service = this.services.find((candidate) => candidate.id === line.service_id);

            if (service) {
                this.openService(service, line);
            }
        },

        saveService() {
            const draft = this.serviceDraft;
            const errors = {};

            if (draft.price === '' || ! (Number(draft.price) >= 0)) {
                errors.price = 'Enter a price of 0 or more.';
            }

            (draft.service.custom_fields ?? []).forEach((field) => {
                const value = draft.values[field.id];

                if (field.required && field.type === 'checkbox' && ! value) {
                    errors[field.id] = `"${field.label}" must be ticked.`;
                } else if (field.required && field.type !== 'checkbox' && String(value ?? '').trim() === '') {
                    errors[field.id] = `"${field.label}" is required.`;
                }
            });

            draft.errors = errors;

            if (Object.keys(errors).length > 0) {
                return;
            }

            const entry = {
                key: draft.key ?? nextKey++,
                service_id: draft.service.id,
                name: draft.service.name,
                basePrice: Number(draft.price),
                values: { ...draft.values },
                summary: (draft.service.custom_fields ?? [])
                    .filter((field) => draft.values[field.id] !== '' && draft.values[field.id] !== false)
                    .map((field) => `${field.label}: ${draft.values[field.id] === true ? 'Yes' : draft.values[field.id]}`)
                    .join(' · '),
            };

            const index = this.serviceLines.findIndex((line) => line.key === entry.key);

            if (index === -1) {
                this.serviceLines.push(entry);
            } else {
                this.serviceLines.splice(index, 1, entry);
            }

            this.serviceModal = false;
        },

        removeServiceLine(line) {
            this.serviceLines = this.serviceLines.filter((other) => other.key !== line.key);
        },

        get cartIsEmpty() {
            return this.lines.length === 0 && this.serviceLines.length === 0 && this.job === null;
        },

        // ── Find Job to Bill ───────────────────────────────────────────────────

        openJobPicker() {
            this.jobQuery = '';
            this.jobError = '';
            this.jobModal = true;
            this.searchJobs(0);
        },

        /**
         * Finished (Job Done) jobs by job no ("123" → JOB-00123), customer name or mobile; the latest with no term.
         */
        searchJobs(delay = 300) {
            clearTimeout(jobSearchTimer);

            const term = this.jobQuery.trim();
            this.jobLoading = true;

            jobSearchTimer = setTimeout(async () => {
                try {
                    const url = new URL(urls.jobSearch, window.location.origin);
                    url.searchParams.set('q', term);

                    const response = await fetch(url, { headers: { Accept: 'application/json' } });

                    if (! response.ok) {
                        throw new Error(String(response.status));
                    }

                    const { data } = await response.json();

                    if (this.jobQuery.trim() === term) {
                        this.jobResults = data ?? [];
                        this.jobError = '';
                    }
                } catch (error) {
                    this.jobResults = [];
                    this.jobError = 'Could not load the jobs. Please try again.';
                } finally {
                    this.jobLoading = false;
                }
            }, delay);
        },

        /**
         * Attach the job; its customer becomes the bill's customer when none is selected.
         */
        attachJob(job) {
            this.job = job;

            if (! this.customer && job.customer) {
                this.selectCustomer(job.customer);
            }

            this.jobModal = false;
        },

        detachJob() {
            this.job = null;
        },

        jobLinePrice(line) {
            return line.surcharge ? this.price(line.price) : Number(line.price);
        },

        // ── Customer ───────────────────────────────────────────────────────────

        openCustomerPicker() {
            this.customerQuery = '';
            this.customerResults = [];
            this.customerCreating = false;
            this.customerErrors = {};
            this.customerModal = true;
        },

        searchCustomers() {
            clearTimeout(customerSearchTimer);

            const term = this.customerQuery.trim();

            if (term.length < 2) {
                this.customerResults = [];
                this.customerLoading = false;

                return;
            }

            this.customerLoading = true;

            customerSearchTimer = setTimeout(async () => {
                try {
                    const url = new URL(urls.customerSearch, window.location.origin);
                    url.searchParams.set('q', term);

                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const { data } = await response.json();

                    if (this.customerQuery.trim() === term) {
                        this.customerResults = data ?? [];
                    }
                } catch (error) {
                    this.customerResults = [];
                } finally {
                    this.customerLoading = false;
                }
            }, 300);
        },

        selectCustomer(customer) {
            this.customer = customer;
            this.redeemPoints = '';
            this.customerModal = false;
        },

        clearCustomer() {
            this.customer = null;
            this.redeemPoints = '';
        },

        startNewCustomer() {
            this.customerForm = { ...blankCustomerForm(), [/^[\d+\s]+$/.test(this.customerQuery) ? 'phone' : 'name']: this.customerQuery.trim() };
            this.customerErrors = {};
            this.customerCreating = true;
        },

        async saveCustomer() {
            this.customerSaving = true;

            const { ok, errors, data } = await sendJson('POST', urls.customerStore, this.customerForm);

            this.customerSaving = false;
            this.customerErrors = errors;

            if (ok) {
                this.selectCustomer(data.customer);
            }
        },

        // ── Payment & bill maths (mirrors SaleService::quote) ──────────────────

        get isSplit() {
            return this.methods.length > 1;
        },

        hasMethod(method) {
            return this.methods.includes(method);
        },

        toggleMethod(method) {
            if (method === 'kokopay') {
                this.methods = ['kokopay'];
                this.openCharge('kokopay');

                return;
            }

            if (this.hasMethod('kokopay')) {
                this.methods = [method];
            } else if (this.hasMethod(method)) {
                if (this.methods.length === 1) {
                    return;
                }

                this.methods = this.methods.filter((other) => other !== method);
            } else {
                this.methods = [...this.methods, method].sort((a, b) => METHOD_ORDER.indexOf(a) - METHOD_ORDER.indexOf(b));
            }

            if (method === 'card' && this.methods.length === 1 && this.methods[0] === 'card') {
                this.openCharge('card');
            }
        },

        openCharge(method) {
            this.chargeMethod = method;
            this.chargeInput = (method === 'card' ? this.cardPercent : this.kokopayPercent) ?? '';
            this.chargeError = '';
            this.chargeModal = true;
        },

        saveCharge() {
            const percent = this.chargeInput === '' ? 0 : Number(this.chargeInput);

            if (! (percent >= 0 && percent <= 100)) {
                this.chargeError = 'Enter a percentage between 0 and 100.';

                return;
            }

            if (this.chargeMethod === 'card') {
                this.cardPercent = percent > 0 ? percent : null;
            } else {
                this.kokopayPercent = percent > 0 ? percent : null;
            }

            this.chargeModal = false;
        },

        get discountCents() {
            return cents(this.billDiscount);
        },

        get pointsRedeemed() {
            return this.customer ? Math.max(0, Number.parseInt(this.redeemPoints, 10) || 0) : 0;
        },

        get otherLegsCents() {
            if (! this.isSplit) {
                return 0;
            }

            return this.methods.filter((method) => method !== 'card').reduce((sum, method) => sum + cents(this.legs[method]), 0);
        },

        get baseSubtotalCents() {
            return this.lines.reduce((sum, line) => sum + (cents(line.basePrice) - cents(line.discount)) * line.qty, 0)
                + (this.job?.lines ?? []).reduce((sum, line) => sum + cents(line.price), 0)
                + this.serviceLines.reduce((sum, line) => sum + cents(line.basePrice), 0);
        },

        /**
         * The surcharge in force: KokoPay or a lone Card raise every price by the %; Card in a split only adds
         * the % of the card portion, spread over the prices.
         */
        get charge() {
            const base = this.baseSubtotalCents;

            if (! this.isSplit && this.methods[0] === 'kokopay' && this.kokopayPercent > 0) {
                return { multiplier: 1 + this.kokopayPercent / 100, method: 'kokopay', percent: this.kokopayPercent };
            }

            if (! this.isSplit && this.methods[0] === 'card' && this.cardPercent > 0) {
                return { multiplier: 1 + this.cardPercent / 100, method: 'card', percent: this.cardPercent };
            }

            if (this.isSplit && this.hasMethod('card') && this.cardPercent > 0 && base > 0) {
                const cardBase = Math.max(0, (base - this.discountCents - this.pointsRedeemed * 100 - this.otherLegsCents) / 100);
                const fee = Math.round(cardBase * this.cardPercent / 100);

                return { multiplier: 1 + fee / (base / 100), method: 'card', percent: this.cardPercent, cardBase, fee };
            }

            return { multiplier: 1, method: null, percent: null };
        },

        price(basePrice) {
            const multiplier = this.charge.multiplier;

            return multiplier === 1 ? Number(basePrice) : Math.round(Number(basePrice) * multiplier);
        },

        lineTotalCents(line) {
            return (cents(this.price(line.basePrice)) - cents(line.discount)) * line.qty;
        },

        get subtotalCents() {
            return this.lines.reduce((sum, line) => sum + this.lineTotalCents(line), 0)
                + (this.job?.lines ?? []).reduce((sum, line) => sum + cents(this.jobLinePrice(line)), 0)
                + this.serviceLines.reduce((sum, line) => sum + cents(this.price(line.basePrice)), 0);
        },

        get chargeAmountCents() {
            return this.subtotalCents - this.baseSubtotalCents;
        },

        get maxPoints() {
            if (! this.customer) {
                return 0;
            }

            return Math.max(0, Math.min(this.customer.loyalty_points, Math.floor((this.subtotalCents - this.discountCents) / 100)));
        },

        get totalCents() {
            return this.subtotalCents - this.discountCents - this.pointsRedeemed * 100;
        },

        legCents(method) {
            if (! this.isSplit) {
                return this.totalCents;
            }

            return method === 'card' ? this.totalCents - this.otherLegsCents : cents(this.legs[method]);
        },

        get splitDifferenceCents() {
            return this.totalCents - this.methods.reduce((sum, method) => sum + this.legCents(method), 0);
        },

        get changeCents() {
            return this.tendered === '' ? null : cents(this.tendered) - this.totalCents;
        },

        get pointsEarned() {
            return this.customer ? Math.floor(Math.max(0, this.subtotalCents - this.discountCents) / (RUPEES_PER_POINT * 100)) : 0;
        },

        get pointsAfter() {
            return this.customer ? this.customer.loyalty_points - this.pointsRedeemed + this.pointsEarned : 0;
        },

        lineDiscountError(line) {
            return cents(line.discount) < 0 || cents(line.discount) > cents(line.basePrice) ? "Discount can't be more than the price." : '';
        },

        /**
         * Why Checkout is disabled, or '' when the sale can go through.
         */
        get checkoutBlocker() {
            if (! this.shift) {
                return 'Open a shift above before checking out.';
            }

            if (this.cartIsEmpty) {
                return 'The cart is empty.';
            }

            if (this.lines.some((line) => this.lineDiscountError(line))) {
                return 'A line discount is more than its price.';
            }

            if (this.discountCents < 0 || this.discountCents > this.subtotalCents) {
                return 'The bill discount must be between 0 and the subtotal.';
            }

            if (this.pointsRedeemed > this.maxPoints) {
                return `You can redeem at most ${this.maxPoints} points.`;
            }

            if (this.isSplit) {
                if (this.methods.some((method) => this.legCents(method) <= 0)) {
                    return 'Each payment must be above zero.';
                }

                if (this.splitDifferenceCents !== 0) {
                    return 'The payments must add up to the total.';
                }
            } else if (this.methods[0] === 'cash' && this.changeCents !== null && this.changeCents < 0) {
                return 'The amount tendered is less than the total.';
            }

            return '';
        },

        get errorMessages() {
            return [...new Set(Object.values(this.errors))];
        },

        // ── Shift ──────────────────────────────────────────────────────────────

        startOpenShift() {
            this.openShiftForm = { opening_float: '', note: '' };
            this.shiftErrors = {};
            this.openShiftModal = true;
        },

        async openShift() {
            this.shiftSaving = true;

            const { ok, errors, data } = await sendJson('POST', urls.openShift, this.openShiftForm);

            this.shiftSaving = false;
            this.shiftErrors = errors;

            if (ok) {
                this.shift = data.shift;
                this.openShiftModal = false;
            }
        },

        startCloseShift() {
            this.closeShiftForm = { counted_cash: '', note: '' };
            this.shiftErrors = {};
            this.closeShiftModal = true;
        },

        /**
         * Counted minus expected drawer cash as the cashier types, or null until a count is entered.
         */
        get closeShiftVarianceCents() {
            if (! this.shift || this.closeShiftForm.counted_cash === '' || this.closeShiftForm.counted_cash === null) {
                return null;
            }

            return cents(this.closeShiftForm.counted_cash) - cents(this.shift.expected_cash);
        },

        fillCountedWithExpected() {
            this.closeShiftForm.counted_cash = cents(this.shift.expected_cash) / 100;
        },

        async closeShift() {
            this.shiftSaving = true;

            const { ok, errors, data } = await sendJson('POST', this.shift.closeUrl, this.closeShiftForm);

            this.shiftSaving = false;
            this.shiftErrors = errors;

            if (ok) {
                this.shift = null;
                this.shiftResult = data.result;
                this.closeShiftModal = false;
                this.shiftResultModal = true;
            }
        },

        varianceLabel(variance) {
            if (variance === 0) {
                return 'Exact';
            }

            return variance < 0 ? 'Short' : 'Over';
        },

        // ── Checkout ───────────────────────────────────────────────────────────

        payload() {
            return {
                customer_id: this.customer?.id ?? null,
                job_id: this.job?.id ?? null,
                items: this.lines.map((line) => ({
                    product_id: line.product_id,
                    qty: line.track_serial ? null : line.qty,
                    batch_id: line.batch_id,
                    unit_ids: line.units.map((unit) => unit.id),
                    discount: cents(line.discount) / 100,
                })),
                services: this.serviceLines.map((line) => ({
                    service_id: line.service_id,
                    price: line.basePrice,
                    fields: line.values,
                })),
                discount_amount: this.discountCents / 100,
                points_redeemed: this.pointsRedeemed,
                payments: this.methods.map((method) => ({ method, amount: this.legCents(method) / 100 })),
                card_charge_percent: this.hasMethod('card') ? this.cardPercent : null,
                kokopay_charge_percent: this.hasMethod('kokopay') ? this.kokopayPercent : null,
                amount_tendered: ! this.isSplit && this.methods[0] === 'cash' && this.tendered !== '' ? cents(this.tendered) / 100 : null,
                expected_total: this.totalCents / 100,
            };
        },

        async checkout() {
            if (this.checkoutBlocker || this.processing) {
                return;
            }

            this.processing = true;
            this.errors = {};

            const { ok, errors, data } = await sendJson('POST', urls.checkout, this.payload());

            this.processing = false;

            if (! ok) {
                this.errors = errors;

                return;
            }

            data.products.forEach(({ id, showroom }) => {
                const product = this.findProduct(id);

                if (product) {
                    product.showroom = showroom;
                }
            });

            this.products = this.products.filter((product) => product.showroom > 0);
            this.shift = data.shift;

            if (data.customer) {
                this.customer = data.customer;
            }

            this.completed = data.sale;
            this.billHtml = data.billHtml;
            this.billBusy = '';
            this.billMessage = '';
            this.billError = '';
            this.completeModal = true;
        },

        newSale() {
            this.completed = null;
            this.lines = [];
            this.serviceLines = [];
            this.job = null;
            this.customer = null;
            this.billDiscount = '';
            this.redeemPoints = '';
            this.methods = ['cash'];
            this.legs = { cash: '', card: '', transfer: '' };
            this.tendered = '';
            this.cardPercent = null;
            this.kokopayPercent = null;
            this.errors = {};
            this.completeModal = false;
        },

        // ── Bill actions ───────────────────────────────────────────────────────

        billElement() {
            return document.getElementById('bill-print');
        },

        printBill() {
            window.print();
        },

        async downloadBill() {
            this.billBusy = 'download';
            this.billError = '';

            try {
                await downloadPdf(this.billElement(), this.completed.invoice_no);
            } catch (error) {
                this.billError = 'Could not create the PDF. Check your connection and try again.';
            } finally {
                this.billBusy = '';
            }
        },

        async emailBill() {
            this.billBusy = 'email';
            this.billError = '';
            this.billMessage = '';

            try {
                const pdf = await pdfBase64(this.billElement());
                const { ok, errors, data } = await sendJson('POST', this.completed.emailUrl, { pdf });

                if (ok) {
                    this.billMessage = data.message;
                } else {
                    this.billError = Object.values(errors)[0] ?? 'Could not send the email.';
                }
            } catch (error) {
                this.billError = 'Could not create the PDF. Check your connection and try again.';
            } finally {
                this.billBusy = '';
            }
        },
    };
}
