<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Support\Facades\Log;

class AdminDashboardService
{
    private function loadSafely(callable $fn): array
    {
        try {
            return ['data' => $fn(), 'error' => null];
        } catch (\Throwable $e) {
            Log::error('Dashboard widget failed', ['exception' => $e->getMessage()]);

            return ['data' => null, 'error' => __('admin.widget_error')];
        }
    }

    public function getActiveApplicationsCount(): array
    {
        return $this->loadSafely(fn () => VisaApplication::whereNotIn('status', ['rejected', 'cancelled'])->count());
    }

    public function getPendingReviewCount(): array
    {
        return $this->loadSafely(fn () => VisaApplication::where('status', 'pending_review')->count());
    }

    public function getTotalClientsCount(): array
    {
        return $this->loadSafely(fn () => User::role('client')->count());
    }

    public function getRecentApplications(): array
    {
        return $this->loadSafely(fn () => VisaApplication::with(['user', 'visaType'])->latest()->take(5)->get());
    }
}
