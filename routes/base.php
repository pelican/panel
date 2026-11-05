<?php

use App\Http\Controllers\HealthController;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Livewire\Installer\PanelInstaller;
use Illuminate\Support\Facades\Route;

Route::get('installer', PanelInstaller::class)->name('installer')
    ->withoutMiddleware(['auth', RedirectIfNotInstalled::class]);

Route::get('health', HealthController::class)->name('health')
    ->withoutMiddleware(['auth', RedirectIfNotInstalled::class]);
