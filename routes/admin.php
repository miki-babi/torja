<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\FoodController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MealProgramController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\TrackingIntegrationController;
use App\Http\Controllers\Admin\TranslationController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::resource('users', UserController::class)
            ->only(['index', 'show', 'update', 'destroy']);

        Route::resource('payments', PaymentController::class)
            ->only(['index', 'show']);
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])
            ->name('payments.verify');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->name('payments.refund');

        Route::resource('programs', ProgramController::class)
            ->except(['show']);
        Route::post('programs/{program}/share', [ProgramController::class, 'share'])
            ->name('programs.share');
        Route::delete('programs/{program}/share', [ProgramController::class, 'unshare'])
            ->name('programs.unshare');
        Route::post('programs/{program}/publish', [ProgramController::class, 'publish'])
            ->name('programs.publish');
        Route::delete('programs/{program}/publish', [ProgramController::class, 'unpublish'])
            ->name('programs.unpublish');

        Route::resource('meal-programs', MealProgramController::class)
            ->except(['show'])
            ->parameters(['meal-programs' => 'mealProgram']);
        Route::post('meal-programs/{mealProgram}/share', [MealProgramController::class, 'share'])
            ->name('meal-programs.share');
        Route::delete('meal-programs/{mealProgram}/share', [MealProgramController::class, 'unshare'])
            ->name('meal-programs.unshare');
        Route::post('meal-programs/{mealProgram}/publish', [MealProgramController::class, 'publish'])
            ->name('meal-programs.publish');
        Route::delete('meal-programs/{mealProgram}/publish', [MealProgramController::class, 'unpublish'])
            ->name('meal-programs.unpublish');

        Route::resource('foods', FoodController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        Route::resource('ingredients', IngredientController::class)
            ->only(['index', 'create', 'store', 'edit', 'update']);

        Route::resource('languages', LanguageController::class)
            ->only(['index', 'store', 'update']);

        Route::resource('translations', TranslationController::class)
            ->only(['index', 'edit', 'update'])
            ->parameters(['translations' => 'translation']);

        Route::resource('tracking', TrackingIntegrationController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['tracking' => 'trackingIntegration']);

        Route::get('analytics', [AnalyticsController::class, 'index'])
            ->name('analytics');
        Route::get('analytics/segments/{key}', [AnalyticsController::class, 'segment'])
            ->name('analytics.segment');
        Route::get('analytics/segments/{key}/export', [AnalyticsController::class, 'segmentCsv'])
            ->name('analytics.segment.csv');
    });
