<?php

return [
    [
        'route' => 'admin.dashboard',
        'label_key' => 'admin.nav_dashboard',
        'icon' => 'home',
        'active_pattern' => null,
    ],
    [
        'route' => 'admin.applications.index',
        'label_key' => 'admin.nav_applications',
        'icon' => 'folder',
        'active_pattern' => 'admin.applications.*',
    ],
    [
        'route' => 'admin.clients.index',
        'label_key' => 'admin.nav_clients',
        'icon' => 'users',
        'active_pattern' => 'admin.clients.*',
    ],
    [
        'route' => 'admin.task-builder.index',
        'label_key' => 'admin.nav_task_builder',
        'icon' => 'cog',
        'active_pattern' => 'admin.task-builder.*',
    ],
    [
        'route' => 'admin.reviewers.index',
        'label_key' => 'admin.nav_reviewers',
        'icon' => 'eye',
        'active_pattern' => 'admin.reviewers.*',
    ],
    [
        'route' => 'admin.users.index',
        'label_key' => 'admin.nav_users',
        'icon' => 'user',
        'active_pattern' => 'admin.users.*',
    ],
];
