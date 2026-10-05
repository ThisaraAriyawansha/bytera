import { sendJson } from './record-form';
import { formatMoney } from '../money';

/**
 * Salary → Payment History view modal (SPEC §8.20): how the amount was calculated, the linked sales / jobs and the
 * note, with Email payslip. Deleting goes through the page's confirm dialog.
 */
export default function salaryView() {
    return {
        money: formatMoney,

        viewOpen: false,
        loading: false,
        payment: null,
        notice: '',
        viewErrors: {},
        sending: false,

        async show(url) {
            this.payment = null;
            this.viewErrors = {};
            this.notice = '';
            this.viewOpen = true;
            this.loading = true;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.payment = (await response.json()).payment;
            } catch (error) {
                this.viewErrors = { form: 'Could not load the payment. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        async emailPayslip() {
            this.sending = true;
            this.notice = '';

            const { ok, errors, data } = await sendJson('POST', this.payment.urls.email, {});

            this.sending = false;
            this.viewErrors = ok ? {} : { form: errors.email ?? errors.form ?? 'Could not send the email.' };

            if (ok) {
                this.payment = data.payment;
                this.notice = data.message;
            }
        },
    };
}
