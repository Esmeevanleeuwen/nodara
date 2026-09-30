<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PlatformController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlatformController::class, 'index'])->name('home');
Route::get('/artikelen/{article:slug}', [PlatformController::class, 'article'])->name('articles.show');
Route::get('/debatten', [PlatformController::class, 'debates'])->name('debates.index');
Route::get('/debatten/{debate:slug}', [PlatformController::class, 'debate'])->name('debates.show');
Route::get('/sitemap.xml', [PlatformController::class, 'sitemap']);
Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\nDisallow: /dashboard\nDisallow: /schrijven\nDisallow: /login\nDisallow: /registreren\nSitemap: ".rtrim(config('app.url'), '/')."/sitemap.xml\n")->header('Content-Type', 'text/plain'));
Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth', ['register' => false])->name('login');
    Route::view('/registreren', 'auth', ['register' => true]);
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/registreren', [AuthController::class, 'register'])->middleware('throttle:5,1');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [PlatformController::class, 'dashboard']);
    Route::view('/schrijven', 'write');
    Route::get('/dashboard/artikelen/{article}/voorbeeld', [PlatformController::class, 'preview']);
    Route::get('/dashboard/artikelen/{article}/bewerken', [PlatformController::class, 'edit']);
    Route::put('/dashboard/artikelen/{article}', [PlatformController::class, 'update']);
    Route::delete('/dashboard/artikelen/{article}', [PlatformController::class, 'deleteArticle']);
    Route::post('/artikelen', [PlatformController::class, 'storeArticle'])->middleware('throttle:10,1');
    Route::get('/debat-maken', function () {
        abort_unless(auth()->user()->is_admin, 403);

        return view('create-debate');
    });
    Route::post('/debatten', [PlatformController::class, 'storeDebate'])->middleware('throttle:10,1');
    Route::post('/artikelen/{article:slug}/reacties', [PlatformController::class, 'comment'])->middleware('throttle:10,1');
    Route::post('/debatten/{debate:slug}/stem', [PlatformController::class, 'vote'])->middleware('throttle:20,1');
    Route::post('/artikelen/{article}/publiceren', [PlatformController::class, 'publish']);
    Route::delete('/reacties/{comment}',[PlatformController::class, 'deleteComment']);
});
