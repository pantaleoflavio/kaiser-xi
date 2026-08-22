<?php

use App\Http\Controllers\Admin\UpdateLocaleController;
use App\Http\Middleware\EnsureAdminPanelAccess;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/locale/{locale}', UpdateLocaleController::class)
    ->middleware(EnsureAdminPanelAccess::class)
    ->name('admin.locale.update');
