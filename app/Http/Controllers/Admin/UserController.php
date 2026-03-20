<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $userService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = $request->input('search', '');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $users = $this->userService->index($search, $sortBy, $sortDir);

        $breadcrumbs = [
            ['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'],
            ['label' => __('admin.nav_users'), 'route' => null],
        ];

        return view('admin.users.index', compact('users', 'search', 'sortBy', 'sortDir', 'breadcrumbs'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', ['roles' => ['admin', 'client', 'reviewer']]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->userService->store($request);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => ['admin', 'client', 'reviewer'],
            'currentRole' => $user->getRoleNames()->first(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->userService->update($user, $request);

        return redirect()->route('admin.users.edit', $user)->with('success', 'User updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $this->userService->deactivate($request->user(), $user);

        return redirect()->route('admin.users.index')->with('success', 'User deactivated.');
    }

    public function assignRole(AssignRoleRequest $request, User $user): RedirectResponse
    {
        $this->authorize('assignRole', $user);

        $this->userService->assignRole($request->user(), $user, $request->role);

        return redirect()->route('admin.users.edit', $user)->with('success', 'Role updated.');
    }
}
