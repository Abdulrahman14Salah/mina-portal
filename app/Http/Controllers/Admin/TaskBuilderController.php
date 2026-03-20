<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TaskBuilderController extends Controller
{
    public function index(): View
    {
        return view('admin.placeholder', [
            'section' => __('admin.nav_task_builder'),
            'breadcrumbs' => [
                ['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'],
                ['label' => __('admin.nav_task_builder'), 'route' => null],
            ],
        ]);
    }
}
