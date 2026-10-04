import { sendJson } from './record-form';
import { resizeSerials } from './product-stock';
import { formatMoney } from '../money';

const blankDraft = () => ({ product_id: null, cost_price: '', selling_price: '', qty: '', serials: [] });

/**
 * New GRN page (SPEC §8.12): pick the supplier and location, build the item list (qty, or one serial per
 * unit for serial products), then save everything in one request. `products` are the SearchableSelect
 * options with `track_serial`, `sku` and `selling_price` added.
 */
export default function grnForm({ products, storeUrl }) {
    const productsById = new Map(products.map((product) => [String(product.value), product]));
    let nextKey = 1;

    return {
        money: formatMoney,

        supplierId: null,
        location: 'stores',
        note: '',
        items: [],
        draft: blankDraft(),
        draftErrors: {},
        errors: {},
        saving: false,

        init() {
            this.$watch('draft.product_id', () => {
                this.draft.qty = '';
                this.draft.serials = [];
                this.draftErrors = {};
            });
        },

        product(id) {
            return productsById.get(String(id)) ?? null;
        },

        get draftProduct() {
            return this.product(this.draft.product_id);
        },

        syncDraftSerials() {
            this.draft.serials = resizeSerials(this.draft.serials, this.draft.qty);
        },

        addItem() {
            const product = this.draftProduct;
            const draft = this.draft;
            const errors = {};
            const serials = draft.serials.map((serial) => serial.trim());

            if (! product) {
                errors.product_id = 'Select a product.';
            }

            if (! (Number(draft.cost_price) > 0)) {
                errors.cost_price = 'The cost price must be greater than 0.';
            }

            if (draft.selling_price !== '' && Number(draft.selling_price) < 0) {
                errors.selling_price = 'The selling price can\'t be negative.';
            }

            if (! (Number.parseInt(draft.qty, 10) >= 1)) {
                errors.qty = 'Enter a quantity of at least 1.';
            } else if (product?.track_serial && serials.some((serial) => serial === '')) {
                errors.serials = 'Enter a serial number for every unit.';
            } else if (product?.track_serial && new Set(serials.map((serial) => serial.toLowerCase())).size !== serials.length) {
                errors.serials = 'Each unit needs a different serial number.';
            }

            this.draftErrors = errors;

            if (Object.keys(errors).length > 0) {
                return;
            }

            this.items.push({
                key: nextKey++,
                product_id: product.value,
                name: product.label,
                sku: product.sku,
                track_serial: product.track_serial,
                product_price: product.selling_price,
                cost_price: Number(draft.cost_price),
                selling_price: draft.selling_price === '' ? null : Number(draft.selling_price),
                qty: product.track_serial ? serials.length : Number.parseInt(draft.qty, 10),
                serials: product.track_serial ? serials : [],
            });

            this.draft = blankDraft();
            this.errors = {};
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.errors = {};
        },

        lineTotal(item) {
            return Math.round(item.qty * item.cost_price * 100) / 100;
        },

        get total() {
            return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
        },

        get totalUnits() {
            return this.items.reduce((sum, item) => sum + item.qty, 0);
        },

        itemErrors(index) {
            return Object.entries(this.errors)
                .filter(([field]) => field.startsWith(`items.${index}.`))
                .map(([, message]) => message);
        },

        get generalError() {
            return this.errors.form ?? this.errors.items ?? this.errors.location ?? '';
        },

        async save() {
            if (this.items.length === 0) {
                this.errors = { items: 'Add at least one item.' };

                return;
            }

            this.saving = true;

            const { ok, errors, data } = await sendJson('POST', storeUrl, {
                supplier_id: this.supplierId,
                location: this.location,
                note: this.note,
                items: this.items.map((item) => ({
                    product_id: item.product_id,
                    cost_price: item.cost_price,
                    selling_price: item.selling_price,
                    qty: item.qty,
                    serials: item.serials,
                })),
            });

            if (ok) {
                window.location.href = data.redirect;

                return;
            }

            this.errors = errors;
            this.saving = false;
        },
    };
}
