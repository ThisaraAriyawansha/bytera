import { sendJson } from './record-form';
import { formatMoney } from '../money';
import { downloadPdf } from '../bill-pdf';

/**
 * Quotations list → view modal (SPEC §8.9): details, Print A4 / Download of #quotation-print, Mark Accepted /
 * Mark Rejected and Delete. The list reloads when the modal closes after a change.
 */
export default function quotationView() {
    return {
        money: formatMoney,

        viewOpen: false,
        changed: false,
        loading: false,
        quotation: null,
        printHtml: '',
        notice: '',
        viewErrors: {},
        statusSaving: '',
        pdfBusy: false,

        init() {
            this.$watch('viewOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });
        },

        async show(url, message = '') {
            this.quotation = null;
            this.printHtml = '';
            this.viewErrors = {};
            this.notice = message;
            this.viewOpen = true;
            this.loading = true;

            if (message) {
                this.changed = true;
            }

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.loaded(await response.json());
            } catch (error) {
                this.viewErrors = { form: 'Could not load the quotation. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        loaded(data) {
            this.quotation = data.quotation;
            this.printHtml = data.printHtml;
        },

        async setStatus(status) {
            this.statusSaving = status;
            this.notice = '';

            const { ok, errors, data } = await sendJson('POST', this.quotation.urls.status, { status });

            this.statusSaving = '';
            this.viewErrors = ok ? {} : { form: errors.status ?? errors.form ?? 'Could not update the quotation.' };

            if (ok) {
                this.loaded(data);
                this.notice = data.message;
                this.changed = true;
            }
        },

        confirmDelete() {
            this.$dispatch('open-modal', {
                name: 'quotation-delete',
                message: `Delete ${this.quotation.quotation_no} for ${this.quotation.customer_name}? This can't be undone.`,
                action: this.quotation.urls.destroy,
            });
        },

        printQuotation() {
            window.print();
        },

        async downloadQuotation() {
            this.pdfBusy = true;

            try {
                await downloadPdf(document.getElementById('quotation-print'), this.quotation.quotation_no);
            } catch (error) {
                this.viewErrors = { form: 'Could not create the PDF. Check your connection and try again.' };
            } finally {
                this.pdfBusy = false;
            }
        },
    };
}
