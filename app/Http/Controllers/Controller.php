<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function __construct()
    {
        if (request()->is(config('api-docs.route_prefix', 'docs/api').'*') || request()->is('api-docs*')) {
            \Inertia\Inertia::setRootView('api-docs::app');
        }
    }
}
