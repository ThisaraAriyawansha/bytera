import { sendJson } from './record-form';

const blankDraft = () => ({ product_id: null, qty: '', unit_ids: [] });

const LOCATION_LABELS = { stores: 'Stores', showroom: 'Showroom' };

/**
 * New Stock Transfer / New Stock Out pages (SPEC §8.13, §8.14): build a list of items taken from one location —
 * a quantity for ordinary products, or picked serial units for serial-tracked ones — and save it with the
 * page's own `header` fields in one request.
 *
 * `products` are SearchableSelect options with `sku`, `track_serial` and `stock: { stores, showroom }` added.
 * `unitsUrl` contains `__PRODUCT__`, replaced by the product id when loading its in-stock units.
 */
export default function stockItemsForm({ products, storeUrl, unitsUrl, location = 'stores', header = {} }) {
    const productsById = new Map(products.map((product) => [String(product.value), product]));
    let nextKey = 1;

    return {
        location,
        header,
        items: [],
        draft: blankDraft(),
        draftUnits: [],
        loadingUnits: false,
        draftErrors: {},
        errors: {},
        saving: false,

        init() {
            this.$watch('draft.product_id', () => {
                this.draft.qty = '';
                this.draft.unit_ids = [];
                this.draftErrors = {};
                this.loadDraftUnits();
            });

            this.$watch('location', () => {
                this.items = [];
                this.errors = {};
                this.draftErrors = {};
                this.draft.unit_ids = [];
                this.loadDraftUnits();
            });
        },

        get locationLabel() {
            return LOCATION_LABELS[this.location];
        },

        product(id) {
            return productsById.get(String(id)) ?? null;
        },

        get draftProduct() {
            return this.product(this.draft.product_id);
        },

        line(productId) {
            return this.items.find((item) => String(item.product_id) === String(productId)) ?? null;
        },

        /**
         * Units of a product still free to add at the current location (stock minus what's already listed).
         */
        available(product) {
            return product ? product.stock[this.location] - (this.line(product.value)?.qty ?? 0) : 0;
        },

        get pickableUnits() {
            const listed = new Set((this.line(this.draft.product_id)?.units ?? []).map((unit) => unit.id));

            return this.draftUnits.filter((unit) => ! listed.has(unit.id));
        },

        async loadDraftUnits() {
            this.draftUnits = [];

            const product = this.draftProduct;

            if (! product?.track_serial) {
                return;
            }

            this.loadingUnits = true;

            try {
                const url = new URL(unitsUrl.replace('__PRODUCT__', product.value), window.location.origin);
                url.searchParams.set('location', this.location);

                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                if (String(this.draft.product_id) === String(product.value)) {
                    this.draftUnits = (await response.json()).units;
                }
            } catch (error) {
                this.draftErrors = { unit_ids: 'Could not load the serial numbers. Please try again.' };
            } finally {
                this.loadingUnits = false;
            }
        },

        toggleAllUnits() {
            this.draft.unit_ids = this.draft.unit_ids.length === this.pickableUnits.length
                ? []
                : this.pickableUnits.map((unit) => unit.id);
        },

        addItem() {
            const product = this.draftProduct;
            const errors = {};
            const qty = Number.parseInt(this.draft.qty, 10);
            const available = this.available(product);

            if (! product) {
                errors.product_id = 'Select a product.';
            } else if (available < 1) {
                errors.product_id = `No more "${product.label}" in ${this.locationLabel}.`;
            } else if (product.track_serial && this.draft.unit_ids.length === 0) {
                errors.unit_ids = 'Pick at least one serial number.';
            } else if (! product.track_serial && ! (qty >= 1)) {
                errors.qty = 'Enter a quantity of at least 1.';
            } else if (! product.track_serial && qty > available) {
                errors.qty = `Only ${available} available in ${this.locationLabel}.`;
            }

            this.draftErrors = errors;

            if (Object.keys(errors).length > 0) {
                return;
            }

            const picked = this.draftUnits.filter((unit) => this.draft.unit_ids.includes(unit.id));
            const existing = this.line(product.value);

            if (existing) {
                existing.units.push(...picked);
                existing.qty = product.track_serial ? existing.units.length : existing.qty + qty;
            } else {
                this.items.push({
                    key: nextKey++,
                    product_id: product.value,
                    name: product.label,
                    sku: product.sku,
                    track_serial: product.track_serial,
                    qty: product.track_serial ? picked.length : qty,
                    units: picked,
                });
            }

            this.draft = blankDraft();
            this.draftUnits = [];
            this.errors = {};
        },

        removeItem(index) {
            this.items.splice(index, 1);
            this.errors = {};
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
            return this.errors.form ?? this.errors.items ?? this.errors.stock ?? this.errors.location ?? '';
        },

        async save() {
            if (this.items.length === 0) {
                this.errors = { items: 'Add at least one item.' };

                return;
            }

            this.saving = true;

            const { ok, errors, data } = await sendJson('POST', storeUrl, {
                ...this.header,
                location: this.location,
                items: this.items.map((item) => ({
                    product_id: item.product_id,
                    qty: item.track_serial ? null : item.qty,
                    unit_ids: item.units.map((unit) => unit.id),
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
