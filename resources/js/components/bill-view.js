import { sendJson } from './record-form';
import { formatMoney } from '../money';
import { downloadPdf, pdfBase64 } from '../bill-pdf';

/**
 * Bills list → view modal (SPEC §8.5): the bill's details and A4 print (Print / Download / Email), Edit Bill
 * (customer name / phone / email, note, payment method) and Reverse Bill with a required reason. The list
 * reloads when the modal closes after a change.
 */
export default function billView() {
    return {
        money: formatMoney,

        viewOpen: false,
        changed: false,
        loading: false,
        bill: null,
        billHtml: '',
        notice: '',
        viewErrors: {},

        editForm: null,
        editSaving: false,

        reverseForm: null,
        reversing: false,

        pdfBusy: '',

        init() {
            this.$watch('viewOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });
        },

        async show(url) {
            this.bill = null;
            this.billHtml = '';
            this.editForm = null;
            this.reverseForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.viewOpen = true;
            this.loading = true;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.loaded(await response.json());
            } catch (error) {
                this.viewErrors = { form: 'Could not load the bill. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        loaded(data) {
            this.bill = data.bill;
            this.billHtml = data.billHtml;
        },

        startEdit() {
            this.reverseForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.editForm = {
                customer_name: this.bill.customer_name ?? '',
                customer_phone: this.bill.customer_phone ?? '',
                customer_email: this.bill.customer_email ?? '',
                note: this.bill.note ?? '',
                payment_method: this.bill.payment_method,
            };
        },

        async saveEdit() {
            this.editSaving = true;

            const { ok, errors, data } = await sendJson('PUT', this.bill.urls.update, this.editForm);

            this.editSaving = false;
            this.viewErrors = errors;

            if (ok) {
                this.loaded(data);
                this.editForm = null;
                this.notice = data.message;
                this.changed = true;
            }
        },

        startReverse() {
            this.editForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.reverseForm = { reason: '' };
        },

        async confirmReverse() {
            if (this.reverseForm.reason.trim() === '') {
                this.viewErrors = { reason: 'Enter the reason for reversing this bill.' };

                return;
            }

            this.reversing = true;

            const { ok, errors, data } = await sendJson('POST', this.bill.urls.reverse, this.reverseForm);

            this.reversing = false;
            this.viewErrors = errors;

            if (ok) {
                this.loaded(data);
                this.reverseForm = null;
                this.notice = data.message;
                this.changed = true;
            }
        },

        billElement() {
            return document.getElementById('bill-print');
        },

        printBill() {
            window.print();
        },

        async downloadBill() {
            this.pdfBusy = 'download';
            this.viewErrors = {};

            try {
                await downloadPdf(this.billElement(), this.bill.invoice_no);
            } catch (error) {
                this.viewErrors = { form: 'Could not create the PDF. Check your connection and try again.' };
            } finally {
                this.pdfBusy = '';
            }
        },

        async emailBill() {
            this.pdfBusy = 'email';
            this.viewErrors = {};
            this.notice = '';

            try {
                const pdf = await pdfBase64(this.billElement());
                const { ok, errors, data } = await sendJson('POST', this.bill.urls.email, { pdf });

                if (ok) {
                    this.notice = data.message;
                } else {
                    this.viewErrors = { form: errors.email ?? errors.pdf ?? errors.form ?? 'Could not send the email.' };
                }
            } catch (error) {
                this.viewErrors = { form: 'Could not create the PDF. Check your connection and try again.' };
            } finally {
                this.pdfBusy = '';
            }
        },
    };
}
