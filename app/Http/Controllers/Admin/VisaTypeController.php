<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class VisaTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.placeholder', [
            'section' => __('admin.nav_visa_types'),
            'breadcrumbs' => [
                ['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'],
                ['label' => __('admin.nav_visa_types'), 'route' => null],
            ],
        ]);
    }
}
