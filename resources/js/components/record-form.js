/**
 * Send a JSON request with the CSRF token. Resolves to `{ ok, errors }`, where `errors` maps each
 * field to its first validation message, or `form` to a general problem.
 */
export async function sendJson(method, url, body) {
    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));

        if (response.ok) {
            return { ok: true, errors: {}, data };
        }

        if (response.status === 422) {
            return {
                ok: false,
                errors: Object.fromEntries(
                    Object.entries(data.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
                ),
            };
        }

        if (response.status === 403) {
            return { ok: false, errors: { form: "You don't have permission to do that." } };
        }

        if (response.status === 419) {
            setTimeout(() => window.location.reload(), 1200);

            return { ok: false, errors: { form: 'Your session expired. Reloading the page…' } };
        }

        return { ok: false, errors: { form: 'Something went wrong. Please try again.' } };
    } catch (error) {
        return { ok: false, errors: { form: 'Could not reach the server. Check your connection and try again.' } };
    }
}

/**
 * Add / Edit modal for a simple record (brands, categories, services, customers). The page passes
 * `{ modal, storeUrl, blank }`; `openEdit(record)` expects the record's fields plus `updateUrl`.
 * Saving submits JSON and reloads the page, where the server has flashed the status message.
 */
export default function recordForm(config) {
    const blank = () => structuredClone(config.blank);

    return {
        form: blank(),
        updateUrl: null,
        errors: {},
        saving: false,

        get isEditing() {
            return this.updateUrl !== null;
        },

        openAdd(defaults = {}) {
            this.open({ ...blank(), ...defaults }, null);
        },

        openEdit(record) {
            const form = blank();

            Object.keys(form).forEach((field) => {
                form[field] = structuredClone(record[field] ?? form[field]);
            });

            this.open(form, record.updateUrl);
        },

        open(form, updateUrl) {
            this.form = form;
            this.updateUrl = updateUrl;
            this.errors = {};
            this.$dispatch('open-modal', config.modal);
        },

        payload() {
            return this.form;
        },

        async save() {
            this.saving = true;

            const { ok, errors } = await sendJson(
                this.isEditing ? 'PUT' : 'POST',
                this.updateUrl ?? config.storeUrl,
                this.payload(),
            );

            this.errors = errors;

            if (ok) {
                window.location.reload();

                return;
            }

            this.saving = false;
        },
    };
}
