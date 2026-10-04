/**
 * An input that filters a dropdown list (SPEC §2.3 SearchableSelect).
 *
 * Options are `{ value, label, description? }`. With `searchUrl` the list is fetched from the
 * server (`?q=…`, JSON array of options) instead of being filtered locally.
 */
export default function searchableSelect({ options = [], value = null, searchUrl = null, emptyLabel = null }) {
    return {
        options,
        value,
        selected: options.find((option) => String(option.value) === String(value)) ?? null,
        query: '',
        open: false,
        highlighted: 0,
        loading: false,
        debounceTimer: null,

        init() {
            this.query = this.selected?.label ?? '';

            this.$watch('value', (newValue) => {
                if (String(newValue ?? '') !== String(this.selected?.value ?? '')) {
                    this.selected = this.options.find((option) => String(option.value) === String(newValue)) ?? null;
                    this.query = this.selected?.label ?? '';
                }
            });
        },

        get choices() {
            let list = this.options;

            if (! searchUrl && this.query !== '') {
                const needle = this.query.toLowerCase();

                list = list.filter((option) => `${option.label} ${option.description ?? ''}`.toLowerCase().includes(needle));
            }

            return emptyLabel !== null ? [{ value: null, label: emptyLabel }, ...list] : list;
        },

        openList() {
            if (this.open) {
                return;
            }

            this.open = true;
            this.query = '';
            this.highlighted = 0;

            if (searchUrl) {
                this.search();
            }
        },

        close() {
            this.open = false;
            this.query = this.selected?.label ?? '';
        },

        onInput() {
            this.open = true;
            this.highlighted = 0;

            if (searchUrl) {
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => this.search(), 250);
            }
        },

        async search() {
            this.loading = true;

            try {
                const url = new URL(searchUrl, window.location.origin);
                url.searchParams.set('q', this.query);

                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                this.options = response.ok ? await response.json() : [];
            } finally {
                this.loading = false;
            }
        },

        move(step) {
            if (! this.open) {
                this.openList();

                return;
            }

            const count = this.choices.length;

            if (count > 0) {
                this.highlighted = (this.highlighted + step + count) % count;
                this.$nextTick(() => this.$refs.list?.children[this.highlighted]?.scrollIntoView({ block: 'nearest' }));
            }
        },

        choose(option) {
            if (! option) {
                return;
            }

            this.selected = option.value === null ? null : option;
            this.value = option.value;
            this.close();
            this.$nextTick(() => this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true })));
        },

        /**
         * Show an option the static list doesn't hold as selected — e.g. the current record of a server-searched
         * picker. Call from `x-init` with the bound value's option.
         */
        seed(option) {
            if (! option) {
                return;
            }

            if (! this.options.some((existing) => String(existing.value) === String(option.value))) {
                this.options = [option, ...this.options];
            }

            this.selected = option;
            this.query = option.label;
        },

        isSelected(option) {
            return String(option.value ?? '') === String(this.value ?? '');
        },
    };
}
