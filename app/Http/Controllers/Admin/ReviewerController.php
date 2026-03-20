<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReviewerController extends Controller
{
    public function index(): View
    {
        return view('admin.placeholder', [
            'section' => __('admin.nav_reviewers'),
            'breadcrumbs' => [
                ['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'],
                ['label' => __('admin.nav_reviewers'), 'route' => null],
            ],
        ]);
    }
}
