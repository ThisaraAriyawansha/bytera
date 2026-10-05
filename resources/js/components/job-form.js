import { sendJson } from './record-form';
import { formatMoney } from '../money';

const cents = (value) => Math.round((Number(value) || 0) * 100);

/** Deep-copies plain JSON data — unlike `structuredClone`, this also works on Alpine's reactive proxies. */
const clone = (value) => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)));

let nextRowId = 1;

const rowId = () => `n${Date.now().toString(36)}${nextRowId++}`;

const blankPart = (name = '') => ({ id: rowId(), name, spec: '', serialNo: '' });

const blankService = () => ({ id: rowId(), name: '', chargeType: 'paid', price: '', freeReason: '' });

const blankForm = (jobNo) => ({
    job_no: jobNo,
    customer_id: null,
    customer_name: '',
    customer_company: '',
    customer_address: '',
    customer_city: '',
    customer_phone: '',
    customer_phone2: '',
    customer_email: '',
    device_type: 'Laptop',
    device_type_other: '',
    brand: '',
    model: '',
    serial_no: '',
    color: '',
    parts: [],
    fault_description: '',
    accessories: [],
    accessories_other: '',
    physical_condition: [],
    special_notes: '',
    assigned_technician_id: '',
    services: [],
    estimated_cost: '',
    advance_paid: '',
    expected_delivery_date: '',
});

const DETAIL_FIELDS = Object.keys(blankForm('')).filter((field) => field !== 'job_no');

/**
 * Jobs → New Job Note / Edit Job modal (SPEC §8.7). New: the job number preview (editable for a custom number),
 * customer search or new customer, device, parts with quick-add chips, fault, accessories, condition, technician,
 * services & charges, money and the expected date. Saving a new job opens it in the view modal (`job-show`);
 * saving an edit hands the refreshed job back to the view (`job-loaded`).
 */
export default function jobForm(config) {
    let customerSearchTimer = null;

    return {
        money: formatMoney,
        jobConfig: config,

        formOpen: false,
        editUrl: null,
        editJobNo: '',
        form: blankForm(config.nextJobNo),
        formErrors: {},
        formSaving: false,

        customerQuery: '',
        customerResults: [],
        customerSearching: false,

        init() {
            this.$watch('customerQuery', () => this.searchCustomers());

            // `/jobs?new=1` (the Dashboard's New Job button) opens the New Job Note straight away.
            if (new URLSearchParams(window.location.search).get('new') === '1') {
                this.openNew();
            }
        },

        get isEditing() {
            return this.editUrl !== null;
        },

        openNew() {
            this.editUrl = null;
            this.form = blankForm(this.jobConfig.nextJobNo);
            this.resetFormState();
        },

        openEdit(job) {
            const form = blankForm('');

            DETAIL_FIELDS.forEach((field) => {
                form[field] = clone(job[field] ?? form[field]);
            });

            form.assigned_technician_id = job.assigned_technician_id ?? '';
            form.expected_delivery_date = job.expected_delivery_date ?? '';
            form.estimated_cost = Number(job.estimated_cost) || '';
            form.advance_paid = Number(job.advance_paid) || '';

            this.editUrl = job.urls.update;
            this.editJobNo = job.job_no;
            this.form = form;
            this.resetFormState();
        },

        resetFormState() {
            this.formErrors = {};
            this.customerQuery = '';
            this.customerResults = [];
            this.formOpen = true;
        },

        // ── Customer ───────────────────────────────────────────────────────────

        searchCustomers() {
            clearTimeout(customerSearchTimer);

            const term = this.customerQuery.trim();

            if (term.length < 2) {
                this.customerResults = [];
                this.customerSearching = false;

                return;
            }

            this.customerSearching = true;

            customerSearchTimer = setTimeout(async () => {
                try {
                    const url = new URL(this.jobConfig.urls.customerSearch, window.location.origin);
                    url.searchParams.set('q', term);

                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const { data } = await response.json();

                    if (this.customerQuery.trim() === term) {
                        this.customerResults = data ?? [];
                    }
                } catch (error) {
                    this.customerResults = [];
                } finally {
                    this.customerSearching = false;
                }
            }, 300);
        },

        pickCustomer(customer) {
            Object.assign(this.form, {
                customer_id: customer.id,
                customer_name: customer.name ?? '',
                customer_phone: customer.phone ?? '',
                customer_phone2: customer.phone2 ?? '',
                customer_email: customer.email ?? '',
                customer_address: customer.address ?? '',
            });

            this.customerQuery = '';
            this.customerResults = [];
        },

        clearCustomer() {
            Object.assign(this.form, {
                customer_id: null,
                customer_name: '',
                customer_company: '',
                customer_address: '',
                customer_city: '',
                customer_phone: '',
                customer_phone2: '',
                customer_email: '',
            });
        },

        // ── Device & parts ─────────────────────────────────────────────────────

        get partPresets() {
            return this.jobConfig.partPresets[this.form.device_type] ?? [];
        },

        addPart(name = '') {
            this.form.parts.push(blankPart(name));
        },

        removePart(part) {
            this.form.parts = this.form.parts.filter((other) => other.id !== part.id);
        },

        toggle(list, value) {
            const values = this.form[list];

            this.form[list] = values.includes(value) ? values.filter((other) => other !== value) : [...values, value];
        },

        // ── Services & charges ─────────────────────────────────────────────────

        addService() {
            this.form.services.push(blankService());
        },

        removeService(service) {
            this.form.services = this.form.services.filter((other) => other.id !== service.id);
        },

        setChargeType(service, chargeType) {
            service.chargeType = chargeType;

            if (chargeType === 'free') {
                service.price = '';
            } else {
                service.freeReason = '';
            }
        },

        get servicesTotalCents() {
            return this.form.services
                .filter((service) => service.chargeType === 'paid')
                .reduce((sum, service) => sum + cents(service.price), 0);
        },

        // ── Save ───────────────────────────────────────────────────────────────

        fieldError(field) {
            return this.formErrors[field] ?? '';
        },

        get errorList() {
            return [...new Set(Object.values(this.formErrors))];
        },

        payload() {
            const payload = {
                ...clone(this.form),
                parts: clone(this.form.parts).filter((part) => [part.name, part.spec, part.serialNo].some((value) => String(value ?? '').trim() !== '')),
            };

            if (this.isEditing) {
                delete payload.job_no;
            } else if (String(payload.job_no).trim().toUpperCase() === this.jobConfig.nextJobNo) {
                payload.job_no = null;
            }

            return payload;
        },

        async saveJob() {
            this.formSaving = true;

            let response;

            try {
                response = await sendJson(this.isEditing ? 'PUT' : 'POST', this.editUrl ?? this.jobConfig.urls.store, this.payload());
            } finally {
                this.formSaving = false;
            }

            const { ok, errors, data } = response;

            this.formErrors = errors;

            if (! ok) {
                return;
            }

            this.formOpen = false;

            if (this.isEditing) {
                window.dispatchEvent(new CustomEvent('job-loaded', { detail: data }));

                return;
            }

            this.jobConfig.nextJobNo = data.nextJobNo;
            window.dispatchEvent(new CustomEvent('job-show', { detail: { url: data.showUrl, prompt: 'received', message: data.message } }));
        },
    };
}
