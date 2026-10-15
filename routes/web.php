<?php

use App\Http\Controllers\ClosingPdfController;
use App\Http\Controllers\SwitchLanguageController;
use App\Http\Middleware\EnsureHouseholdAdmin;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::get('/language/{locale}', SwitchLanguageController::class)->name('language.switch');
Route::get('/closings/{closing}/pdf', ClosingPdfController::class)
    ->middleware(['auth', EnsureHouseholdAdmin::class, 'throttle:30,1'])->name('closings.pdf');
