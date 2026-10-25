<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Code written for either one shared database or a database per school asks this before it
     * filters by school_id. This app is the shared kind, so the answer is always no.
     */
    protected function isTenantMode(): bool
    {
        return false;
    }
}
