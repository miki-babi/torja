<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AmbassadorCommissionController;
use App\Http\Controllers\Admin\AmbassadorController;
use App\Http\Controllers\Admin\AmbassadorPayoutController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\FoodController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MealProgramController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\Admin\ProPageController;
use App\Http\Controllers\Admin\ShopOrderController;
use App\Http\Controllers\Admin\ShopProductController;
use App\Http\Controllers\Admin\TelegramBroadcastController;
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
        Route::post('users/{user}/pro-grant', [UserController::class, 'grantPro'])
            ->name('users.pro-grant.store');
        Route::delete('users/{user}/pro-grant', [UserController::class, 'revokePro'])
            ->name('users.pro-grant.destroy');

        Route::resource('payments', PaymentController::class)
            ->only(['index', 'show']);
        Route::post('payments/{payment}/verify', [PaymentController::class, 'verify'])
            ->name('payments.verify');
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
            ->name('payments.refund');

        Route::get('pro-page', [ProPageController::class, 'edit'])->name('pro-page.edit');
        Route::put('pro-page', [ProPageController::class, 'update'])->name('pro-page.update');
        Route::delete('pro-page', [ProPageController::class, 'destroy'])->name('pro-page.reset');

        Route::get('ambassadors', [AmbassadorController::class, 'index'])->name('ambassadors.index');
        Route::get('ambassadors/settings', [AmbassadorController::class, 'editSettings'])->name('ambassadors.settings.edit');
        Route::put('ambassadors/settings', [AmbassadorController::class, 'updateSettings'])->name('ambassadors.settings.update');
        Route::post('ambassadors/commissions/{commission}/approve', [AmbassadorCommissionController::class, 'approve'])->name('ambassadors.commissions.approve');
        Route::post('ambassadors/commissions/{commission}/void', [AmbassadorCommissionController::class, 'void'])->name('ambassadors.commissions.void');
        Route::post('ambassadors/payouts/{payout}/paid', [AmbassadorPayoutController::class, 'markPaid'])->name('ambassadors.payouts.paid');
        Route::post('ambassadors/payouts/{payout}/reject', [AmbassadorPayoutController::class, 'reject'])->name('ambassadors.payouts.reject');

        Route::post('shop-products/upload-image', [ShopProductController::class, 'uploadImage'])
            ->name('shop-products.upload-image');
        Route::resource('shop-products', ShopProductController::class)
            ->except(['show', 'destroy'])
            ->parameters(['shop-products' => 'shopProduct']);

        Route::post('broadcasts/upload-media', [TelegramBroadcastController::class, 'uploadMedia'])
            ->name('broadcasts.upload-media');
        Route::post('broadcasts/audience-count', [TelegramBroadcastController::class, 'audienceCount'])
            ->name('broadcasts.audience-count');
        Route::get('broadcasts/users-search', [TelegramBroadcastController::class, 'usersSearch'])
            ->name('broadcasts.users-search');
        Route::resource('broadcasts', TelegramBroadcastController::class)
            ->parameters(['broadcasts' => 'broadcast']);
        Route::post('broadcasts/{broadcast}/test', [TelegramBroadcastController::class, 'sendTest'])
            ->name('broadcasts.test');
        Route::post('broadcasts/{broadcast}/send-now', [TelegramBroadcastController::class, 'sendNow'])
            ->name('broadcasts.send-now');
        Route::post('broadcasts/{broadcast}/pause', [TelegramBroadcastController::class, 'pause'])
            ->name('broadcasts.pause');
        Route::post('broadcasts/{broadcast}/resume', [TelegramBroadcastController::class, 'resume'])
            ->name('broadcasts.resume');
        Route::post('broadcasts/{broadcast}/cancel', [TelegramBroadcastController::class, 'cancel'])
            ->name('broadcasts.cancel');
        Route::post('broadcasts/{broadcast}/runs/{run}/resend-failed', [TelegramBroadcastController::class, 'resendFailed'])
            ->name('broadcasts.runs.resend-failed');
        Route::post('broadcasts/{broadcast}/runs/{run}/recall', [TelegramBroadcastController::class, 'recall'])
            ->name('broadcasts.runs.recall');
        Route::resource('shop-orders', ShopOrderController::class)
            ->only(['index', 'show', 'update'])
            ->parameters(['shop-orders' => 'shopOrder']);

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
