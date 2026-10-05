import { sendJson } from './record-form';

/**
 * Claim Warranty modal (SPEC §8.10): a note about the claim, then the warranty is marked claimed. The page
 * reloads to show the flashed status message.
 */
export default function warrantyClaim() {
    return {
        warranty: null,
        claimNote: '',
        errors: {},
        saving: false,

        open(warranty) {
            this.warranty = warranty;
            this.claimNote = '';
            this.errors = {};
            this.$dispatch('open-modal', 'warranty-claim');
        },

        async save() {
            this.saving = true;

            const { ok, errors } = await sendJson('POST', this.warranty.claimUrl, { claim_note: this.claimNote });

            this.errors = errors;

            if (ok) {
                window.location.reload();

                return;
            }

            this.saving = false;
        },
    };
}
