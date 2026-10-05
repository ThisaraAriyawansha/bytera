import { sendJson } from './record-form';
import { formatMoney } from '../money';

/**
 * Finance → Shifts view modal (SPEC §8.19): every figure, cash paid out, force-close info and the sales made in the
 * shift, plus Force Close (Admin Override, open shifts) and Review (Approve / Flag, closed shifts). The table reloads
 * when the modal closes after a change.
 */
export default function shiftView() {
    return {
        money: formatMoney,

        viewOpen: false,
        changed: false,
        loading: false,
        shift: null,
        notice: '',
        viewErrors: {},
        saving: false,

        forceForm: null,
        reviewForm: null,

        init() {
            this.$watch('viewOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });
        },

        async show(url) {
            this.shift = null;
            this.forceForm = null;
            this.reviewForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.viewOpen = true;
            this.loading = true;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.shift = (await response.json()).shift;
            } catch (error) {
                this.viewErrors = { form: 'Could not load the shift. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        varianceClass(variance) {
            if (variance === null) {
                return 'text-ink';
            }

            return variance < 0 ? 'text-red-600' : (variance > 0 ? 'text-green-700' : 'text-ink');
        },

        varianceLabel(variance) {
            if (variance === null) {
                return '—';
            }

            if (variance === 0) {
                return `${this.money(0)} (exact)`;
            }

            return `${variance > 0 ? '+' : '-'}${this.money(Math.abs(variance))} (${variance > 0 ? 'over' : 'short'})`;
        },

        get forceVariance() {
            if (! this.forceForm || this.forceForm.counted_cash === '') {
                return null;
            }

            return Math.round((Number(this.forceForm.counted_cash) - this.shift.expected_cash) * 100) / 100;
        },

        startForceClose() {
            this.reviewForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.forceForm = { counted_cash: '', note: '' };
        },

        startReview(decision) {
            this.forceForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.reviewForm = { decision, note: this.shift.review_note ?? '' };
        },

        async saveForceClose() {
            await this.send(this.shift.urls.forceClose, this.forceForm, () => {
                this.forceForm = null;
            });
        },

        async saveReview() {
            await this.send(this.shift.urls.review, this.reviewForm, () => {
                this.reviewForm = null;
            });
        },

        async send(url, body, onSuccess) {
            this.saving = true;
            this.notice = '';

            const { ok, errors, data } = await sendJson('POST', url, body);

            this.saving = false;
            this.viewErrors = errors;

            if (ok) {
                onSuccess();
                this.shift = data.shift;
                this.notice = data.message;
                this.changed = true;
            }
        },
    };
}
