<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected AdminDashboardService $dashboardService
    ) {}

    public function index(): View
    {
        $breadcrumbs = [['label' => __('admin.breadcrumb_home'), 'route' => null]];

        $widgets = [
            'active_count' => $this->dashboardService->getActiveApplicationsCount(),
            'pending_count' => $this->dashboardService->getPendingReviewCount(),
            'client_count' => $this->dashboardService->getTotalClientsCount(),
            'recent' => $this->dashboardService->getRecentApplications(),
        ];

        return view('admin.dashboard', compact('breadcrumbs', 'widgets'));
    }
}
