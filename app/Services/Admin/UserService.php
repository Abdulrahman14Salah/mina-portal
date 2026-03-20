<?php

namespace App\Services\Admin;

use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\Auth\AuditLogService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function index(string $search = '', string $sortBy = 'created_at', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $allowedSorts = ['name', 'email', 'created_at'];
        if (! in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->orderBy($sortBy, $sortDir)->paginate(15)->withQueryString();
    }

    public function store(StoreUserRequest $request): User
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        $user->assignRole($request->role);

        $this->auditLog->log('user_created', $user, ['role' => $request->role]);

        return $user;
    }

    public function update(User $user, UpdateUserRequest $request): User
    {
        $user->update($request->only('name', 'email'));

        return $user;
    }

    public function deactivate(User $currentUser, User $targetUser): void
    {
        if ($currentUser->id === $targetUser->id) {
            throw new AuthorizationException();
        }

        $targetUser->update(['is_active' => false]);

        $this->auditLog->log('account_deactivated', $targetUser, ['deactivated_by' => $currentUser->id]);
    }

    public function assignRole(User $currentUser, User $targetUser, string $role): void
    {
        $oldRole = $targetUser->getRoleNames()->first() ?? 'none';

        $targetUser->syncRoles([$role]);

        $this->auditLog->log('role_changed', $targetUser, [
            'old_role' => $oldRole,
            'new_role' => $role,
            'changed_by' => $currentUser->id,
        ]);
    }
}
