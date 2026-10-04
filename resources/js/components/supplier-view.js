import { sendJson } from './record-form';
import { formatMoney } from '../money';

const STATUSES = {
    paid: { label: 'Paid', variant: 'badge-success' },
    partial: { label: 'Partial', variant: 'badge-warning' },
    outstanding: { label: 'Outstanding', variant: 'badge-danger' },
};

/**
 * Suppliers → view modal (SPEC §8.18): contact, totals, Record Payment, Payment History with
 * Edit payment, and Send account statement. The table is reloaded when the modal closes after a change.
 */
export default function supplierView() {
    return {
        money: formatMoney,

        viewOpen: false,
        changed: false,
        loading: false,
        url: null,
        supplier: null,
        payments: [],
        notice: '',
        viewErrors: {},
        paymentForm: null,
        editing: null,
        viewSaving: false,
        sending: false,

        init() {
            this.$watch('viewOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });
        },

        statusLabel(status) {
            return STATUSES[status]?.label ?? status;
        },

        statusVariant(status) {
            return STATUSES[status]?.variant ?? 'badge-default';
        },

        get generalError() {
            return this.viewErrors.form ?? this.viewErrors.email ?? '';
        },

        async show(url) {
            this.url = url;
            this.supplier = null;
            this.payments = [];
            this.paymentForm = null;
            this.editing = null;
            this.viewErrors = {};
            this.notice = '';
            this.viewOpen = true;

            await this.load();
        },

        async load() {
            this.loading = true;

            try {
                const response = await fetch(this.url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                const data = await response.json();

                this.supplier = data.supplier;
                this.payments = data.payments;
            } catch (error) {
                this.viewErrors = { form: 'Could not load the supplier. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        startPayment() {
            this.editing = null;
            this.viewErrors = {};
            this.notice = '';
            this.paymentForm = { amount: '', method: 'cash', reference: '', note: '' };
        },

        startEdit(payment) {
            this.paymentForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.editing = {
                id: payment.id,
                url: payment.updateUrl,
                payment_no: payment.payment_no,
                amount: payment.amount,
                method: payment.method,
                reference: payment.reference ?? '',
                note: payment.note ?? '',
            };
        },

        get paymentTooLarge() {
            return this.paymentForm !== null && Number(this.paymentForm.amount) > Number(this.supplier?.balance ?? 0);
        },

        async savePayment() {
            await this.send('POST', this.supplier.paymentsUrl, this.paymentForm, () => {
                this.paymentForm = null;
            });
        },

        async saveEdit() {
            const { url, amount, method, reference, note } = this.editing;

            await this.send('PUT', url, { amount, method, reference, note }, () => {
                this.editing = null;
            });
        },

        async sendStatement() {
            this.sending = true;
            this.paymentForm = null;
            this.editing = null;

            await this.send('POST', this.supplier.statementUrl, {}, () => {});

            this.sending = false;
        },

        async send(method, url, body, onSuccess) {
            this.viewSaving = true;
            this.notice = '';

            const { ok, errors, data } = await sendJson(method, url, body);

            this.viewSaving = false;
            this.viewErrors = errors;

            if (ok) {
                onSuccess();
                this.changed = true;
                this.notice = data.message;
                await this.load();
            }
        },
    };
}
