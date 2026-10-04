import { sendJson } from './record-form';
import { formatMoney } from '../money';

/**
 * GRN list → view modal with the admin edit (SPEC §8.12): supplier, note and each line's cost /
 * selling price. Quantities and serials stay locked; the list reloads on close after a change.
 */
export default function grnView() {
    return {
        money: formatMoney,

        viewOpen: false,
        changed: false,
        loading: false,
        grn: null,
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
            this.grn = null;
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

                this.grn = (await response.json()).grn;
            } catch (error) {
                this.viewErrors = { form: 'Could not load the GRN. Please try again.' };
            } finally {
                this.loading = false;
            }
        },

        startEdit() {
            this.viewErrors = {};
            this.notice = '';
            this.editForm = {
                supplier_id: this.grn.supplier_id,
                note: this.grn.note ?? '',
                items: this.grn.items.map((item) => ({
                    id: item.id,
                    cost_price: item.cost_price,
                    selling_price: item.selling_price ?? '',
                })),
            };
        },

        itemError(index) {
            return this.viewErrors[`items.${index}.cost_price`] ?? this.viewErrors[`items.${index}.selling_price`] ?? '';
        },

        async saveEdit() {
            this.viewSaving = true;

            const { ok, errors, data } = await sendJson('PUT', this.grn.updateUrl, this.editForm);

            this.viewSaving = false;
            this.viewErrors = errors;

            if (ok) {
                this.grn = data.grn;
                this.editForm = null;
                this.notice = data.message;
                this.changed = true;
            }
        },
    };
}
