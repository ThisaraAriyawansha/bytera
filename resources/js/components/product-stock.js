import { sendJson } from './record-form';
import { formatMoney } from '../money';

/**
 * Grow or shrink a list of serial-number inputs to `count` entries, keeping what was typed.
 */
export function resizeSerials(serials, count) {
    const size = Math.max(0, Math.min(Number.parseInt(count, 10) || 0, 500));

    return Array.from({ length: size }, (_, index) => serials[index] ?? '');
}

/**
 * Fetch a JSON document, resolving to the data or null on failure.
 */
async function fetchJson(url) {
    try {
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });

        return response.ok ? await response.json() : null;
    } catch (error) {
        return null;
    }
}

/**
 * The first message of a non-field error (stock problems, permission, network) from sendJson().
 */
function generalError(errors, fields) {
    const entry = Object.entries(errors).find(([key]) => ! fields.some((field) => key === field || key.startsWith(`${field}.`)));

    return entry?.[1] ?? '';
}

/**
 * Products → Stock Batches and Serial Numbers modals (SPEC §8.11). Both load JSON from the server;
 * after any change the products table is reloaded when the batches modal closes so its counters refresh.
 */
export default function productStock() {
    return {
        money: formatMoney,

        batchesOpen: false,
        serialsOpen: false,
        changed: false,
        notice: '',

        batchesUrl: null,
        product: null,
        batches: [],
        loadingBatches: false,
        batchForm: null,
        batchErrors: {},
        savingBatch: false,

        unitsUrl: null,
        batch: null,
        units: [],
        loadingUnits: false,
        editingUnitId: null,
        unitSerial: '',
        newSerials: null,
        unitErrors: {},
        savingUnits: false,
        unitsChanged: false,

        init() {
            this.$watch('batchesOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });

            this.$watch('serialsOpen', (open) => {
                if (open) {
                    return;
                }

                if (this.changed && ! this.batchesOpen) {
                    window.location.reload();
                } else if (this.unitsChanged && this.batchesOpen) {
                    this.loadBatches();
                }
            });
        },

        get batchError() {
            return generalError(this.batchErrors, ['cost_price', 'selling_price', 'total_qty', 'remaining_qty', 'note', 'qty', 'serials']);
        },

        get unitError() {
            return generalError(this.unitErrors, ['serial_number', 'serials']);
        },

        locationLabel(location) {
            return location === 'showroom' ? 'Showroom' : 'Stores';
        },

        unitStatusLabel(status) {
            return { in_stock: 'In stock', sold: 'Sold', issued: 'Issued' }[status] ?? status;
        },

        unitStatusVariant(status) {
            return { in_stock: 'badge-success', sold: 'badge-default', issued: 'badge-info' }[status] ?? 'badge-default';
        },

        // ---- Stock Batches -------------------------------------------------------------------

        async openBatches(url) {
            this.batchesUrl = url;
            this.product = null;
            this.batches = [];
            this.batchForm = null;
            this.batchErrors = {};
            this.notice = '';
            this.batchesOpen = true;

            await this.loadBatches();
        },

        async loadBatches() {
            this.loadingBatches = true;

            const data = await fetchJson(this.batchesUrl);

            this.loadingBatches = false;

            if (data === null) {
                this.batchErrors = { form: 'Could not load the batches. Please try again.' };

                return;
            }

            this.product = data.product;
            this.batches = data.batches;
        },

        startAddBatch() {
            this.batchErrors = {};
            this.notice = '';
            this.batchForm = { mode: 'add', cost_price: '', selling_price: '', qty: '', note: '', serials: [] };
        },

        startEditBatch(batch) {
            this.batchErrors = {};
            this.notice = '';
            this.batchForm = {
                mode: 'edit',
                id: batch.id,
                url: batch.updateUrl,
                cost_price: batch.cost_price,
                selling_price: batch.selling_price ?? '',
                total_qty: batch.total_qty,
                remaining_qty: batch.remaining_qty,
                note: batch.note ?? '',
            };
        },

        syncBatchSerials() {
            this.batchForm.serials = resizeSerials(this.batchForm.serials, this.batchForm.qty);
        },

        async saveBatch() {
            const form = this.batchForm;
            const common = { cost_price: form.cost_price, selling_price: form.selling_price, note: form.note };
            let body = { ...common, total_qty: form.total_qty, remaining_qty: form.remaining_qty };

            if (form.mode === 'add') {
                body = this.product.track_serial ? { ...common, serials: form.serials } : { ...common, qty: form.qty };
            }

            this.savingBatch = true;

            const { ok, errors, data } = await sendJson(form.mode === 'add' ? 'POST' : 'PUT', form.mode === 'add' ? this.batchesUrl : form.url, body);

            this.savingBatch = false;
            this.batchErrors = errors;

            if (ok) {
                this.changed = true;
                this.batchForm = null;
                this.notice = data.message;
                await this.loadBatches();
            }
        },

        // ---- Serial Numbers ------------------------------------------------------------------

        async openSerials(batch) {
            this.unitsUrl = batch.unitsUrl;
            this.batch = batch;
            this.units = [];
            this.editingUnitId = null;
            this.newSerials = null;
            this.unitErrors = {};
            this.unitsChanged = false;
            this.serialsOpen = true;

            await this.loadUnits();
        },

        async loadUnits() {
            this.loadingUnits = true;

            const data = await fetchJson(this.unitsUrl);

            this.loadingUnits = false;

            if (data === null) {
                this.unitErrors = { form: 'Could not load the serial numbers. Please try again.' };

                return;
            }

            this.product = data.product;
            this.batch = data.batch;
            this.units = data.units;
        },

        editUnit(unit) {
            this.unitErrors = {};
            this.newSerials = null;
            this.editingUnitId = unit.id;
            this.unitSerial = unit.serial_number;
        },

        async saveUnit(unit) {
            await this.sendUnits('PUT', unit.updateUrl, { serial_number: this.unitSerial });
        },

        startAddSerials() {
            this.unitErrors = {};
            this.editingUnitId = null;
            this.newSerials = { count: 1, serials: [''] };
        },

        syncNewSerials() {
            this.newSerials.serials = resizeSerials(this.newSerials.serials, this.newSerials.count);
        },

        async saveNewSerials() {
            await this.sendUnits('POST', this.unitsUrl, { serials: this.newSerials.serials });
        },

        confirmDeleteUnit(unit) {
            this.$dispatch('open-modal', {
                name: 'delete-unit',
                message: `Remove serial "${unit.serial_number}"? Stock goes down by 1.`,
                payload: unit.deleteUrl,
            });
        },

        async handleConfirmed(detail) {
            if (detail.name === 'delete-unit') {
                await this.sendUnits('DELETE', detail.payload, {});
            }
        },

        async sendUnits(method, url, body) {
            this.savingUnits = true;

            const { ok, errors } = await sendJson(method, url, body);

            this.savingUnits = false;
            this.unitErrors = errors;

            if (ok) {
                this.changed = true;
                this.unitsChanged = true;
                this.editingUnitId = null;
                this.newSerials = null;
                await this.loadUnits();
            }
        },
    };
}
