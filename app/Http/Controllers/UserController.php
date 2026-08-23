<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff accounts for one shop.
 *
 * The list is store-scoped by the global scope, so a cafe owner can only ever
 * see and touch their own people.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->orderByRaw("CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 ELSE 2 END")
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                    'is_active' => $user->is_active,
                    'is_self' => $user->id === request()->user()?->id,
                ]),
            'canManageOwners' => request()->user()?->isOwner(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        // store_id is stamped by the BelongsToStore trait, so a new account
        // always lands in the creator's own shop.
        User::create($request->validated());

        return redirect()->route('users.index')->with('success', 'Account created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        if ($this->wouldLockThemselvesOut($request, $user, $data)) {
            return redirect()->back()->withErrors([
                'role' => 'You cannot remove your own access — ask another owner to do it.',
            ]);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Account updated.');
    }

    /**
     * Deactivating rather than deleting: sales, stock logs and voids all point
     * at the person who made them, and that history has to stay readable.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->back()->withErrors([
                'user' => 'You cannot deactivate your own account.',
            ]);
        }

        if ($user->isOwner() && ! $request->user()->isOwner()) {
            return redirect()->back()->withErrors([
                'user' => 'Only an owner can deactivate another owner.',
            ]);
        }

        $user->update(['is_active' => false]);

        return redirect()->route('users.index')->with('success', "{$user->name} deactivated.");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function wouldLockThemselvesOut(Request $request, User $user, array $data): bool
    {
        if ($user->id !== $request->user()->id) {
            return false;
        }

        return $data['is_active'] === false
            || $data['role'] !== $user->role->value;
    }
}
