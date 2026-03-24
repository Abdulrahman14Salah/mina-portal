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

    public function approve(User $user, ApplicationTask $task): bool
    {
        if ($user->can('dashboard.admin')) {
            return true;
        }

        if (! $user->can('tasks.advance')) {
            return false;
        }

        return $task->application->assigned_reviewer_id === $user->id;
    }

    public function reject(User $user, ApplicationTask $task): bool
    {
        if ($user->can('dashboard.admin')) {
            return true;
        }

        if (! $user->can('tasks.reject')) {
            return false;
        }

        return $task->application->assigned_reviewer_id === $user->id;
    }

    public function reopen(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.advance');
    }

    public function submitForReview(User $user, ApplicationTask $task): bool
    {
        return $user->can('tasks.submit-for-review')
            && $task->application->user_id === $user->id
            && $task->status === 'in_progress';
    }
}
