import { sendJson } from './record-form';
import { formatMoney } from '../money';

/**
 * New Quotation modal (SPEC §8.9): customer, items picked from the product search or typed as free text,
 * per-unit discounts, an overall discount, note / terms and a valid-until date. Quotations never touch stock.
 * On save it dispatches `quotation-saved` with the new quotation's view URL.
 */
export default function quotationForm({ products, defaultValidUntil, urls }) {
    let nextKey = 1;

    const blank = () => ({
        customer_name: '',
        customer_phone: '',
        customer_address: '',
        items: [],
        discount_amount: '',
        note: '',
        valid_until: defaultValidUntil,
    });

    const cents = (amount) => Math.round((Number(amount) || 0) * 100);

    return {
        money: formatMoney,
        products,

        form: blank(),
        productSearch: '',
        errors: {},
        saving: false,

        get productResults() {
            const term = this.productSearch.trim().toLowerCase();

            if (term === '') {
                return [];
            }

            return this.products
                .filter((product) => [product.name, product.sku, product.barcode].some((value) => value && value.toLowerCase().includes(term)))
                .slice(0, 8);
        },

        open() {
            this.form = blank();
            this.productSearch = '';
            this.errors = {};
            this.$dispatch('open-modal', 'quotation-form');
        },

        addProduct(product) {
            const existing = this.form.items.find((item) => item.product_id === product.id);

            if (existing) {
                existing.qty = (Number(existing.qty) || 0) + 1;
            } else {
                this.form.items.push({
                    key: nextKey++,
                    product_id: product.id,
                    product_name: product.name,
                    sku: product.sku,
                    qty: 1,
                    unit_price: product.price,
                    discount: '',
                });
            }

            this.productSearch = '';
        },

        addCustomItem() {
            this.form.items.push({
                key: nextKey++,
                product_id: null,
                product_name: this.productSearch.trim(),
                sku: null,
                qty: 1,
                unit_price: '',
                discount: '',
            });

            this.productSearch = '';
            this.$nextTick(() => [...this.$root.querySelectorAll('[data-item-name]')].pop()?.focus());
        },

        removeItem(index) {
            this.form.items.splice(index, 1);
        },

        lineTotalCents(item) {
            return (cents(item.unit_price) - cents(item.discount)) * (parseInt(item.qty, 10) || 0);
        },

        get subtotalCents() {
            return this.form.items.reduce((sum, item) => sum + this.lineTotalCents(item), 0);
        },

        get totalCents() {
            return this.subtotalCents - cents(this.form.discount_amount);
        },

        itemError(index) {
            return ['product_name', 'qty', 'unit_price', 'discount']
                .map((field) => this.errors[`items.${index}.${field}`])
                .find(Boolean) ?? '';
        },

        async save() {
            this.saving = true;

            const { ok, errors, data } = await sendJson('POST', urls.store, {
                ...this.form,
                items: this.form.items.map(({ product_id, product_name, qty, unit_price, discount }) => ({
                    product_id, product_name, qty, unit_price, discount,
                })),
            });

            this.saving = false;
            this.errors = errors;

            if (ok) {
                this.$dispatch('close-modal', 'quotation-form');
                this.$dispatch('quotation-saved', { url: data.showUrl, message: data.message });
            }
        },
    };
}
