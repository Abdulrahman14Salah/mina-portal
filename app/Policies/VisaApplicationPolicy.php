<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisaApplication;

class VisaApplicationPolicy
{
    public function view(User $user, VisaApplication $application): bool
    {
        return $user->id === $application->user_id;
    }
}
