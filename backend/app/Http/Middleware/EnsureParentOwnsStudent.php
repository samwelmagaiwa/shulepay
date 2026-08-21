<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureParentOwnsStudent
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        $studentId = $request->route('student')
            ? $request->route('student')->id
            : $request->input('student_id');

        if (! $studentId) {
            return $next($request);
        }

        if (! $user->guardian) {
            abort(403, 'Access denied.');
        }

        $owns = $user->guardian->students()
            ->where('students.id', $studentId)
            ->exists();

        if (! $owns) {
            abort(403, 'Access denied — this student does not belong to you.');
        }

        return $next($request);
    }
}
