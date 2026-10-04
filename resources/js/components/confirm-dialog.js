/**
 * Confirm dialog state (SPEC §2.3). Opened with
 * `$dispatch('open-modal', 'name')` or `$dispatch('open-modal', { name, title?, message?, action?, payload? })`.
 *
 * With an `action` URL, Confirm submits the dialog's form to it; otherwise it dispatches a
 * window `confirmed` event with `{ name, payload }` for the page's own Alpine code to handle.
 */
export default function confirmDialog(defaults) {
    return {
        show: false,
        busy: false,
        payload: null,
        ...defaults,

        open(detail) {
            const options = typeof detail === 'object' && detail !== null ? detail : {};

            if ((options.name ?? detail) !== defaults.name) {
                return;
            }

            this.title = options.title ?? defaults.title;
            this.message = options.message ?? defaults.message;
            this.action = options.action ?? defaults.action;
            this.payload = options.payload ?? null;
            this.busy = false;
            this.show = true;
        },

        confirm() {
            if (this.action) {
                this.busy = true;
                this.$refs.form.action = this.action;
                this.$refs.form.submit();

                return;
            }

            this.$dispatch('confirmed', { name: defaults.name, payload: this.payload });
            this.show = false;
        },
    };
}
