import { sendJson } from './record-form';

/**
 * Settings → Team (SPEC §8.22): Add User / Edit User modals. Both submit JSON and reload the
 * page on success; the server flashes the status message and re-checks every manage rule.
 */
export default function teamManager(config) {
    const blankNewUser = () => ({
        name: '',
        email: '',
        role: config.assignableRoles[0] ?? '',
        password: '',
        password_confirmation: '',
    });

    return {
        newUser: blankNewUser(),
        editing: { name: '', email: '', role: '', status: 'active', permissions: [], url: '', readOnly: false },
        errors: {},
        saving: false,

        openAdd() {
            this.newUser = blankNewUser();
            this.errors = {};
            this.$dispatch('open-modal', 'add-user');
        },

        openEdit(member, readOnly = false) {
            this.editing = {
                name: member.name,
                email: member.email,
                role: member.role,
                status: member.status,
                permissions: [...member.permissions],
                url: member.updateUrl,
                readOnly,
            };
            this.errors = {};
            this.$dispatch('open-modal', 'edit-user');
        },

        hasPermission(key) {
            return this.editing.permissions.includes(key);
        },

        togglePermission(key) {
            this.editing.permissions = this.hasPermission(key)
                ? this.editing.permissions.filter((granted) => granted !== key)
                : [...this.editing.permissions, key];
        },

        resetToRoleDefaults() {
            this.editing.permissions = [...(config.roleDefaults[this.editing.role] ?? [])];
        },

        async saveNew() {
            if (await this.send('POST', config.storeUrl, this.newUser)) {
                window.location.reload();
            }
        },

        async saveEdit() {
            const { name, role, status, permissions } = this.editing;

            if (await this.send('PUT', this.editing.url, { name, role, status, permissions })) {
                window.location.reload();
            }
        },

        async send(method, url, body) {
            this.saving = true;

            const { ok, errors } = await sendJson(method, url, body);

            this.errors = errors;
            this.saving = false;

            return ok;
        },
    };
}
