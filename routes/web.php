<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AuthController;

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Admin Routes (protected)
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');

    Route::get('/review-campaigns/create', [AdminController::class, 'createCampaign'])->name('campaigns.create');
    Route::post('/review-campaigns', [AdminController::class, 'storeCampaign'])->name('campaigns.store');

    Route::get('/review-campaigns/{campaign}', [AdminController::class, 'showCampaign'])->name('campaigns.show');
    Route::get('/review-campaigns/{campaign}/edit', [AdminController::class, 'editCampaign'])->name('campaigns.edit');
    Route::put('/review-campaigns/{campaign}', [AdminController::class, 'updateCampaign'])->name('campaigns.update');
    Route::delete('/review-campaigns/{campaign}', [AdminController::class, 'destroyCampaign'])->name('campaigns.destroy');
    Route::patch('/review-campaigns/{campaign}/toggle', [AdminController::class, 'toggleCampaign'])->name('campaigns.toggle');

    Route::get('/review-campaigns/{campaign}/reviews', [AdminController::class, 'showCampaignReviews'])->name('campaigns.reviews');
    Route::get('/review-campaigns/{campaign}/export-csv', [AdminController::class, 'exportCsv'])->name('campaigns.export-csv');
    Route::get('/review-campaigns/{campaign}/export-pdf', [AdminController::class, 'exportPdf'])->name('campaigns.exportPdf');
});

// Root redirect
Route::get('/', fn() => redirect()->route('admin.dashboard'));

// Student Review Routes (public) — throttle: 5 attempts per minute per IP
Route::get('/review/{token}', [ReviewController::class, 'show'])->name('review.show');
Route::post('/review/{token}', [ReviewController::class, 'store'])->name('review.store')->middleware('throttle:5,1');
Route::get('/review/{token}/success', [ReviewController::class, 'success'])->name('review.success');
