<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\StoreTeamMemberRequest;
use App\Http\Requests\Settings\UpdateTeamMemberRequest;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamMemberController extends Controller
{
    /**
     * Create a user with the full default permission map for their role.
     */
    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $role = $request->validated('role');

        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $role,
            'status' => 'active',
            'permissions' => Permissions::defaults($role),
        ]);

        $message = "{$user->name} has been added as {$role}.";

        session()->flash('status', $message);

        return response()->json(['message' => $message], 201);
    }

    /**
     * Update a user's name, role, status and permissions.
     */
    public function update(UpdateTeamMemberRequest $request, User $user): JsonResponse
    {
        $user->update([
            'name' => $request->validated('name'),
            'role' => $request->validated('role'),
            'status' => $request->validated('status'),
            'permissions' => Permissions::mapFromGranted($request->validated('permissions')),
        ]);

        $message = "{$user->name} has been updated.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Delete a user. Users referenced by sales, shifts, jobs etc. can only be deactivated.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless(Permissions::canManageUser($request->user(), $user), 403);

        try {
            $user->delete();
        } catch (QueryException $exception) {
            report($exception);

            return to_route('settings.index')->with(
                'error',
                "{$user->name} has records in the system and can't be deleted. Set them to Inactive instead.",
            );
        }

        return to_route('settings.index')->with('status', "{$user->name} has been deleted.");
    }
}
