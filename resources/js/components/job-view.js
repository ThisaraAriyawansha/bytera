import { sendJson } from './record-form';
import { formatMoney } from '../money';
import { downloadPdf } from '../bill-pdf';

/**
 * Jobs → view modal (SPEC §8.7): details, Print A4 / Download of the job note, Update Job Status (repair cost
 * with "Use services total" for Job Done), the Job History timeline and the email prompts. The table reloads
 * when the modal closes after a change.
 */
export default function jobView({ statuses }) {
    return {
        money: formatMoney,
        statuses,

        viewOpen: false,
        changed: false,
        viewLoading: false,
        job: null,
        printHtml: '',
        viewNotice: '',
        viewErrors: {},

        statusForm: { status: 'pending', repair_cost: '', note: '' },
        statusSaving: false,

        emailPrompt: null,
        emailSending: false,
        pdfBusy: false,

        init() {
            this.$watch('viewOpen', (open) => {
                if (! open && this.changed) {
                    window.location.reload();
                }
            });
        },

        statusLabel(status) {
            return this.statuses[status]?.label ?? status;
        },

        statusBadge(status) {
            return `badge-${this.statuses[status]?.variant ?? 'default'}`;
        },

        statusDot(status) {
            return {
                warning: 'bg-amber-500',
                default: 'bg-zinc-400',
                success: 'bg-green-500',
                info: 'bg-blue-500',
                danger: 'bg-red-500',
            }[this.statuses[status]?.variant] ?? 'bg-zinc-400';
        },

        async show(url, { prompt = null, message = '' } = {}) {
            this.job = null;
            this.printHtml = '';
            this.viewErrors = {};
            this.viewNotice = message;
            this.emailPrompt = null;
            this.viewOpen = true;
            this.viewLoading = true;

            if (prompt) {
                this.changed = true;
            }

            try {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });

                if (! response.ok) {
                    throw new Error(String(response.status));
                }

                this.loaded(await response.json());
                this.offerEmail(prompt);
            } catch (error) {
                this.viewErrors = { form: 'Could not load the job. Please try again.' };
            } finally {
                this.viewLoading = false;
            }
        },

        loaded(data) {
            this.job = data.job;
            this.printHtml = data.printHtml;
            this.statusForm = { status: data.job.status, repair_cost: data.job.repair_cost ?? '', note: '' };
        },

        /**
         * An edit saved in the Edit Job form.
         */
        edited(data) {
            this.loaded(data);
            this.viewNotice = data.message;
            this.changed = true;
        },

        offerEmail(type) {
            this.emailPrompt = type && this.job?.customer_email ? type : null;
        },

        useServicesTotal() {
            this.statusForm.repair_cost = this.job.services_total;
        },

        async saveStatus() {
            this.statusSaving = true;
            this.viewNotice = '';
            this.emailPrompt = null;

            const { ok, errors, data } = await sendJson('POST', this.job.urls.status, {
                status: this.statusForm.status,
                repair_cost: this.statusForm.status === 'done' && this.statusForm.repair_cost !== '' ? this.statusForm.repair_cost : null,
                note: this.statusForm.note,
            });

            this.statusSaving = false;
            this.viewErrors = errors;

            if (ok) {
                this.loaded(data);
                this.viewNotice = data.message;
                this.changed = true;
                this.offerEmail('update');
            }
        },

        async sendEmail() {
            this.emailSending = true;
            this.viewErrors = {};

            const { ok, errors, data } = await sendJson('POST', this.job.urls.email, { type: this.emailPrompt });

            this.emailSending = false;

            if (ok) {
                this.emailPrompt = null;
                this.viewNotice = data.message;
            } else {
                this.viewErrors = { email: errors.email ?? errors.type ?? errors.form ?? 'Could not send the email.' };
            }
        },

        printJob() {
            window.print();
        },

        async downloadJob() {
            this.pdfBusy = true;

            try {
                await downloadPdf(document.getElementById('job-print'), this.job.job_no);
            } catch (error) {
                this.viewErrors = { form: 'Could not create the PDF. Check your connection and try again.' };
            } finally {
                this.pdfBusy = false;
            }
        },
    };
}
