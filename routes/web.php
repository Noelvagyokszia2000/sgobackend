<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 404);

    $disk = Storage::disk('public');

    abort_unless($disk->exists($path), 404);

    return response($disk->get($path), 200)
        ->header('Content-Type', $disk->mimeType($path) ?: 'application/octet-stream')
        ->header('Cache-Control', 'public, max-age=31536000');
})->where('path', '.*');
