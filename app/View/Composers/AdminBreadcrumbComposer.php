<?php

namespace App\View\Composers;

use Illuminate\View\View;

class AdminBreadcrumbComposer
{
    public function compose(View $view): void
    {
        if (! $view->offsetExists('breadcrumbs')) {
            $view->with('breadcrumbs', [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard']]);
        }
    }
}
