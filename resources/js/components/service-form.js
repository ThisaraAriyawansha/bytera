import recordForm from './record-form';

let nextKey = 0;

/**
 * Services Add / Edit modal (SPEC §8.8) with the custom fields builder: add, remove and reorder
 * rows; Dropdown options are typed comma-separated and sent as an array.
 */
export default function serviceForm(config) {
    const base = recordForm({
        ...config,
        blank: { name: '', default_price: '', description: '', active: true, custom_fields: [] },
    });

    const toRow = (field = {}) => ({
        key: ++nextKey,
        id: field.id ?? null,
        label: field.label ?? '',
        type: field.type ?? 'text',
        required: field.required ?? false,
        placeholder: field.placeholder ?? '',
        optionsText: (field.options ?? []).join(', '),
    });

    const openRecord = base.openEdit;

    return Object.defineProperties(base, Object.getOwnPropertyDescriptors({
        openEdit(service) {
            openRecord.call(this, service);
            this.form.custom_fields = this.form.custom_fields.map(toRow);
        },

        addField() {
            this.form.custom_fields.push(toRow());
        },

        removeField(index) {
            this.form.custom_fields.splice(index, 1);
            this.errors = {};
        },

        moveField(index, offset) {
            const target = index + offset;

            if (target < 0 || target >= this.form.custom_fields.length) {
                return;
            }

            const rows = this.form.custom_fields;
            [rows[index], rows[target]] = [rows[target], rows[index]];
            this.errors = {};
        },

        fieldError(index, attribute) {
            return this.errors[`custom_fields.${index}.${attribute}`]
                ?? (attribute === 'options' ? this.errors[`custom_fields.${index}.options.0`] : undefined);
        },

        payload() {
            return {
                ...this.form,
                custom_fields: this.form.custom_fields.map((row) => ({
                    id: row.id,
                    label: row.label,
                    type: row.type,
                    required: row.required,
                    placeholder: row.placeholder,
                    options: row.type === 'select'
                        ? row.optionsText.split(',').map((option) => option.trim()).filter(Boolean)
                        : [],
                })),
            };
        },
    }));
}
