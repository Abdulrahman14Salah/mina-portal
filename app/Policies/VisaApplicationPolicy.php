<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaApplication;

class VisaApplicationPolicy
{
    public function view(User $user, VisaApplication $application): bool
    {
        // Admin can always view
        if ($user->can('dashboard.admin')) {
            return true;
        }

        // Assigned reviewer can view
        if ($user->can('tasks.view') && $application->assigned_reviewer_id === $user->id) {
            return true;
        }

        // Client can view own application
        return $user->id === $application->user_id;
    }

    public function assignReviewer(User $user, VisaApplication $application): bool
    {
        return $user->can('dashboard.admin');
    }
}
