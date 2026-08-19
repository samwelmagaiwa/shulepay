<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureParentOwnsStudent {
    public function handle(Request $request, Closure $next): mixed {
        $user = $request->user();

        $studentId = $request->route('student')
            ? $request->route('student')->id
            : $request->input('student_id');

        if (!$studentId) {
            return $next($request);
        }

        if (!$user->guardian) {
            abort(403, 'Ruhusa imekataliwa.');
        }

        $owns = $user->guardian->students()
            ->where('students.id', $studentId)
            ->exists();

        if (!$owns) {
            abort(403, 'Ruhusa imekataliwa — mwanafunzi huyu si wako.');
        }

        return $next($request);
    }
}
