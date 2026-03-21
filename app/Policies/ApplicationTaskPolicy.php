<?php

namespace App\Policies;

use App\Models\ApplicationTask;
use App\Models\User;

class ApplicationTaskPolicy
{
    public function advance(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.advance');
    }

    public function reject(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.reject');
    }

    public function reopen(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.advance');
    }
}
