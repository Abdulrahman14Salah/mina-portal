<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.edit');
    }

    public function deactivate(User $user, User $model): bool
    {
        return $user->can('users.deactivate') && $user->id !== $model->id;
    }

    public function assignRole(User $user, User $model): bool
    {
        return $user->can('roles.assign');
    }
}
