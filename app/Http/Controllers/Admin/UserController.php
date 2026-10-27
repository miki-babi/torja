<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Searchable, paginated list of all users.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/users/index', [
            'users' => User::query()
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('telegram_username', 'like', "%{$search}%")
                ))
                ->withCount([
                    'payments as successful_payments_count' => fn ($query) => $query->where('status', 'success'),
                ])
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * One user with their nutrition profile summary and payments.
     */
    public function show(User $user): Response
    {
        $user->load([
            'nutritionProfile',
            'payments' => fn ($query) => $query->latest(),
        ]);

        return Inertia::render('admin/users/show', [
            'user' => $user,
        ]);
    }

    /**
     * Toggle the user's role between 'user' and 'admin'.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors([
                'role' => __("You can't change your own role."),
            ]);
        }

        $user->role = $request->validated('role');
        $user->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User role updated.'),
        ]);

        return back();
    }

    /**
     * Delete a user account (cascades their payments/profile).
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors([
                'user' => __("You can't delete your own account here."),
            ]);
        }

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User deleted.'),
        ]);

        return to_route('admin.users.index');
    }
}
