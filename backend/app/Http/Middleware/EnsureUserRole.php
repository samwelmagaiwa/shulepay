<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        return $next($request);
    }
}
