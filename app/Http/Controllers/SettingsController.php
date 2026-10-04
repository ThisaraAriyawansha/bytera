<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\UpdateShopSettingsRequest;
use App\Models\ShopSetting;
use App\Models\User;
use App\Rules\StrictEmail;
use App\Support\DataTables;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Show the settings page. Everyone can open it; only Admin / Super Admin see the edit controls.
     */
    public function index(Request $request): View
    {
        $viewer = $request->user();

        $team = User::query()
            ->when($viewer->role !== Permissions::SUPER_ADMIN, fn ($query) => $query->where('role', '!=', Permissions::SUPER_ADMIN))
            ->orderBy('name')
            ->get()
            ->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->role,
                'status' => $member->status,
                'isSelf' => $viewer->is($member),
                'canManage' => Permissions::canManageUser($viewer, $member),
                'canViewPermissions' => Permissions::isAdmin($viewer) && ! $viewer->is($member),
                'permissions' => array_keys(array_filter(Permissions::effective($member))),
                'updateUrl' => route('settings.users.update', $member),
                'deleteUrl' => route('settings.users.destroy', $member),
            ]);

        $isSuperAdmin = $viewer->role === Permissions::SUPER_ADMIN;

        return view('settings.index', [
            'settings' => ShopSetting::current(),
            'canManage' => Permissions::isAdmin($viewer),
            'isSuperAdmin' => $isSuperAdmin,
            'team' => $team,
            'assignableRoles' => Permissions::assignableRoles($viewer->role),
            'permissionGroups' => Permissions::groupedByModule(),
            'roleDefaults' => collect(Permissions::EDITABLE_ROLES)
                ->mapWithKeys(fn (string $role): array => [$role => array_keys(array_filter(Permissions::defaults($role)))])
                ->all(),
            'tableCounts' => DataTables::rowCounts(),
            'tableLabels' => DataTables::TABLES,
            'cleanableTables' => $isSuperAdmin ? DataTables::CLEANABLE : [],
        ]);
    }

    /**
     * Save the shop info used on prints, emails and the header.
     */
    public function updateShop(UpdateShopSettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $settings = ShopSetting::query()->lockForUpdate()->first() ?? new ShopSetting(['notify_emails' => []]);

            $settings->fill($request->validated())->save();
        });

        return to_route('settings.index')->with('status', 'Shop info saved.');
    }

    /**
     * Add a low stock alert recipient.
     *
     * @throws ValidationException
     */
    public function storeNotifyEmail(Request $request): RedirectResponse
    {
        abort_unless(Permissions::isAdmin($request->user()), 403);

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $email = $request->validateWithBag('notifyEmails', [
            'email' => ['required', 'string', new StrictEmail],
        ])['email'];

        $added = DB::transaction(function () use ($email): bool {
            $settings = ShopSetting::query()->lockForUpdate()->first() ?? new ShopSetting(['name' => ShopSetting::DEFAULT_NAME, 'phone' => '']);
            $recipients = $settings->notify_emails ?? [];

            if (in_array($email, $recipients, true)) {
                return false;
            }

            $settings->notify_emails = [...$recipients, $email];
            $settings->save();

            return true;
        });

        if (! $added) {
            throw ValidationException::withMessages(['email' => 'This email is already on the list.'])
                ->errorBag('notifyEmails');
        }

        return to_route('settings.index')->with('status', "{$email} will now receive low stock alerts.");
    }

    /**
     * Remove a low stock alert recipient.
     */
    public function destroyNotifyEmail(Request $request): RedirectResponse
    {
        abort_unless(Permissions::isAdmin($request->user()), 403);

        $email = (string) $request->validate(['email' => ['required', 'string']])['email'];

        DB::transaction(function () use ($email): void {
            $settings = ShopSetting::query()->lockForUpdate()->first();

            if ($settings === null) {
                return;
            }

            $settings->notify_emails = array_values(array_diff($settings->notify_emails ?? [], [$email]));
            $settings->save();
        });

        return to_route('settings.index')->with('status', "{$email} removed from low stock alerts.");
    }
}
