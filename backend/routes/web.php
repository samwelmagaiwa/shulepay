<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

// Serve public storage files in local/dev environments where the symlink may not work
if (app()->environment('local')) {
    Route::get('/storage/{path}', function (string $path) {
        $fullPath = storage_path('app/public/'.$path);
        if (! file_exists($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath);
    })->where('path', '.*');
}
