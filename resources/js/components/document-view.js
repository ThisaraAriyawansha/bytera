import { sendJson } from './record-form';
import { formatMoney } from '../money';

/**
 * List page → view modal with an optional admin edit, for stock documents (transfers, stock outs).
 * `show(url)` loads `{ record }`; `startEdit()` copies `editFields` from the record into `editForm`, which
 * is PUT to the record's `updateUrl`. The list reloads on close after a change.
 */
export default function documentView({ editFields = [] } = {}) {
    return {
        money: formatMoney,

        viewOpen: false,
        changed: false,
        loading: false,
        record: null,
        editForm: null,
        viewErrors: {},
        viewSaving: false,
        notice: '',

        init() {
            this.$watch('viewOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });
        },

        async show(url) {
            this.record = null;
            this.editForm = null;
            this.viewErrors = {};
            this.notice = '';
            this.viewOpen = true;
            this.loading = true;

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.record = (await response.json()).record;
            } catch (error) {
                this.viewErrors = { form: 'Could not load the record. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        startEdit() {
            this.viewErrors = {};
            this.notice = '';
            this.editForm = Object.fromEntries(editFields.map((field) => [field, structuredClone(this.record[field] ?? null)]));
        },

        cancelEdit() {
            this.editForm = null;
            this.viewErrors = {};
        },

        async saveEdit() {
            this.viewSaving = true;

            const { ok, errors, data } = await sendJson('PUT', this.record.updateUrl, this.editForm);

            this.viewSaving = false;
            this.viewErrors = errors;

            if (ok) {
                this.record = data.record;
                this.editForm = null;
                this.notice = data.message;
                this.changed = true;
            }
        },
    };
}
