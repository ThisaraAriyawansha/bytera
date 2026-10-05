import { sendJson } from './record-form';
import { formatMoney } from '../money';

const SEARCH_DELAY_MS = 250;

/**
 * Salary → Issue Payment (SPEC §8.20). Picking an employee pre-fills the type, monthly amount and commission % from
 * their setup. Commission / Hybrid payments can link unclaimed sales and jobs; the commission base totals them unless
 * typed over, and Amount to Pay = monthly + base × % unless typed over. Optionally paid from an open shift's drawer.
 */
export default function salaryIssue(config) {
    return {
        money: formatMoney,
        employees: config.employees,
        openShifts: config.openShifts,

        form: blankForm(),
        linked: [],
        baseTouched: false,
        amountTouched: false,
        errors: {},
        saving: false,

        query: '',
        results: [],
        searching: false,
        searchTimer: null,

        init() {
            this.search();
        },

        get employee() {
            return this.employees.find((employee) => String(employee.id) === String(this.form.user_id)) ?? null;
        },

        get hasMonthly() {
            return this.form.type === 'monthly' || this.form.type === 'hybrid';
        },

        get hasCommission() {
            return this.form.type === 'commission' || this.form.type === 'hybrid';
        },

        get linkedTotal() {
            return this.linked.reduce((sum, item) => sum + Math.round(item.amount * 100), 0) / 100;
        },

        get commissionBase() {
            return this.baseTouched ? Number(this.form.commission_base || 0) : this.linkedTotal;
        },

        get commissionAmount() {
            return Math.round(this.commissionBase * Number(this.form.commission_percent || 0)) / 100;
        },

        get calculatedAmount() {
            const monthly = this.hasMonthly ? Math.round(Number(this.form.monthly_amount || 0) * 100) : 0;
            const commission = this.hasCommission ? Math.round(this.commissionAmount * 100) : 0;

            return (monthly + commission) / 100;
        },

        get selectedShift() {
            return this.openShifts.find((shift) => String(shift.id) === String(this.form.shift_id)) ?? null;
        },

        /**
         * Keep the auto-filled base and amount in step with the inputs they come from (run by x-effect).
         */
        sync() {
            if (! this.baseTouched) {
                this.form.commission_base = this.linkedTotal === 0 ? '' : this.linkedTotal;
            }

            if (! this.amountTouched) {
                this.form.amount = this.calculatedAmount === 0 ? '' : this.calculatedAmount;
            }
        },

        selectEmployee() {
            const employee = this.employee;

            this.errors = {};
            this.baseTouched = false;
            this.amountTouched = false;

            if (employee === null) {
                return;
            }

            this.form.type = employee.salary_type ?? 'monthly';
            this.form.monthly_amount = employee.monthly_amount ?? '';
            this.form.commission_percent = employee.commission_percent ?? '';
        },

        resetBase() {
            this.baseTouched = false;
        },

        resetAmount() {
            this.amountTouched = false;
        },

        isLinked(item) {
            return this.linked.some((linked) => linked.kind === item.kind && linked.id === item.id);
        },

        get availableResults() {
            return this.results.filter((item) => ! this.isLinked(item));
        },

        link(item) {
            if (! this.isLinked(item)) {
                this.linked.push(item);
            }
        },

        unlink(item) {
            this.linked = this.linked.filter((linked) => ! (linked.kind === item.kind && linked.id === item.id));
        },

        queueSearch() {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.search(), SEARCH_DELAY_MS);
        },

        async search() {
            this.searching = true;

            try {
                const url = new URL(config.urls.commissionItems, window.location.origin);
                url.searchParams.set('q', this.query.trim());

                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.results = (await response.json()).data;
            } catch (error) {
                this.results = [];
            } finally {
                this.searching = false;
            }
        },

        async save() {
            this.saving = true;

            const { ok, errors, data } = await sendJson('POST', config.urls.store, {
                user_id: this.form.user_id,
                type: this.form.type,
                commission_base: this.hasCommission ? this.commissionBase : null,
                commission_percent: this.hasCommission ? this.form.commission_percent : null,
                amount: this.form.amount,
                period_label: this.form.period_label,
                note: this.form.note,
                from_drawer: this.form.from_drawer,
                shift_id: this.form.from_drawer ? this.form.shift_id : null,
                sale_ids: this.hasCommission ? this.linked.filter((item) => item.kind === 'sale').map((item) => item.id) : [],
                job_ids: this.hasCommission ? this.linked.filter((item) => item.kind === 'job').map((item) => item.id) : [],
            });

            this.errors = errors;

            if (ok) {
                window.location.href = data.redirect;

                return;
            }

            this.saving = false;
        },
    };
}

function blankForm() {
    return {
        user_id: '',
        type: 'monthly',
        monthly_amount: '',
        commission_base: '',
        commission_percent: '',
        amount: '',
        period_label: new Date().toLocaleString('en-US', { month: 'long', year: 'numeric' }),
        note: '',
        from_drawer: false,
        shift_id: '',
    };
}
