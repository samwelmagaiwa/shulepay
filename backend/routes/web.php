<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Serve public storage files in local/dev environments where the symlink may not work
if (app()->environment('local')) {
    Route::get('/storage/{path}', function (string $path) {
        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    })->where('path', '.*');
}
