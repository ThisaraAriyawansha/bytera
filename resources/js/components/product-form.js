import recordForm from './record-form';
import { resizeSerials } from './product-stock';

/**
 * Products Add / Edit modal (SPEC §8.11). Sub categories are filtered by the chosen main category.
 * When adding, initial stock becomes the first Stores batch; serial products get one serial input per unit.
 */
export default function productForm(config) {
    const base = recordForm({
        ...config,
        blank: {
            name: '',
            brand_id: '',
            sku: '',
            barcode: '',
            main_category_id: '',
            sub_category_id: '',
            description: '',
            selling_price: '',
            initial_stock: '',
            cost_price: '',
            low_stock_alert: 5,
            warranty_months: 0,
            track_serial: false,
            active: true,
            serials: [],
        },
    });

    const openRecord = base.openEdit;
    const openBlank = base.openAdd;

    return Object.defineProperties(base, Object.getOwnPropertyDescriptors({
        hasBatches: false,

        get subCategories() {
            const main = config.mainCategories.find((category) => String(category.id) === String(this.form.main_category_id));

            return main?.sub_categories ?? [];
        },

        openAdd() {
            this.hasBatches = false;
            openBlank.call(this);
        },

        openEdit(product) {
            this.hasBatches = product.hasBatches;
            openRecord.call(this, product);
        },

        mainCategoryChanged() {
            this.form.sub_category_id = '';
        },

        syncSerials() {
            this.form.serials = this.form.track_serial ? resizeSerials(this.form.serials, this.form.initial_stock) : [];
        },

        payload() {
            if (! this.isEditing) {
                return this.form;
            }

            const { initial_stock, cost_price, serials, ...details } = this.form;

            return details;
        },
    }));
}
