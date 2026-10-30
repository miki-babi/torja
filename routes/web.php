<?php

use App\Enums\WizardSection;
use App\Http\Controllers\AmbassadorRedirectController;
use App\Http\Controllers\Auth\TelegramLoginController;
use App\Http\Controllers\Member;
use App\Http\Controllers\Plan\JamiWebhookController;
use App\Http\Controllers\Plan\NutritionPreviewController;
use App\Http\Controllers\Plan\NutritionWizardController;
use App\Http\Controllers\Plan\PaymentController;
use App\Http\Controllers\Plan\ProController;
use App\Http\Controllers\Plan\VerifyCheckoutWebhookController;
use App\Http\Controllers\Settings\LocalizationController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\Telegram\WebhookController;
use App\Http\Controllers\Tracking\BeaconController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('welcome', 'welcome')->name('home');

Route::post('t', BeaconController::class)
    ->middleware('throttle:120,1')
    ->name('tracking.beacon');

Route::post('telegram/webhook', WebhookController::class)
    ->middleware('throttle:60,1')
    ->name('telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::post('auth/telegram/init', [TelegramLoginController::class, 'init'])
        ->middleware('throttle:20,1')
        ->name('telegram.login.init');
    Route::get('auth/telegram/status', [TelegramLoginController::class, 'status'])
        ->middleware('throttle:60,1')
        ->name('telegram.login.status');
    Route::post('auth/telegram/complete', [TelegramLoginController::class, 'complete'])
        ->middleware('throttle:20,1')
        ->name('telegram.login.complete');
    Route::inertia('admin/login', 'auth/admin-login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
    ])->name('admin.login');
    Route::post('auth/telegram/debug', [TelegramLoginController::class, 'debug'])
        ->middleware('throttle:60,1')
        ->name('telegram.login.debug');
});

// Outside the guest group: already-authenticated Mini App sessions also
// post here so a `startapp=ref_<code>` invite binds their referrer.
Route::post('auth/telegram/webapp', [TelegramLoginController::class, 'webapp'])
    ->middleware('throttle:20,1')
    ->name('telegram.login.webapp');

Route::post('locale', [LocalizationController::class, 'update'])
    ->name('locale.set');

Route::inertia('calculator', 'calculator')->name('calculator');
Route::inertia('calculator/bmi', 'calculator/bmi')->name('calculator.bmi');
Route::inertia('calculator/body-fat', 'calculator/body-fat')
    ->name('calculator.body-fat');

Route::get('plan/wizard', [NutritionWizardController::class, 'edit'])->name('plan.wizard');
Route::patch('plan/wizard/{section}', [NutritionWizardController::class, 'update'])
    ->whereIn('section', WizardSection::values())
    ->name('plan.wizard.update');
Route::get('plan/review', [NutritionWizardController::class, 'review'])->name('plan.review');

Route::get('plan/preview', [NutritionPreviewController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('plan.preview');

Route::post('plan/payment/webhook', [PaymentController::class, 'webhook'])
    ->name('payment.webhook');

Route::post('webhooks/verify-checkout', [VerifyCheckoutWebhookController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('payment.verify-checkout.webhook');

Route::post('webhooks/jami', [JamiWebhookController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('payment.jami.webhook');

Route::get('explore', Member\ExploreController::class)->name('explore.index');
Route::get('shop/products/{product}', [Member\ExploreController::class, 'product'])
    ->name('shop.products.show');

Route::get('shared/playlists/{playlist:share_token}', [Member\SharedPlaylistController::class, 'show'])
    ->name('playlists.shared.show');

Route::get('shared/programs/{program:share_token}', [Member\SharedProgramController::class, 'show'])
    ->name('programs.shared.show');

Route::get('shared/meal-programs/{mealProgram:share_token}', [Member\SharedMealProgramController::class, 'show'])
    ->name('meal-programs.shared.show');

Route::get('s/{code}', ShortLinkController::class)->name('links.resolve');

Route::get('r/{code}', AmbassadorRedirectController::class)->name('invite.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pro', [ProController::class, 'show'])->name('pro.show');
    Route::post('plan/checkout', [PaymentController::class, 'store'])
        ->name('plan.checkout');
    Route::get('plan/payment/callback', [PaymentController::class, 'callback'])
        ->name('payment.callback');
    Route::post('plan/payment/{payment}/verify', [PaymentController::class, 'verify'])
        ->name('payment.verify');
    Route::get('plan/payment/{payment}/status', [PaymentController::class, 'status'])
        ->middleware('throttle:30,1')
        ->name('payment.status');
    Route::post('plan/payment/{payment}/cancel', [PaymentController::class, 'cancel'])
        ->name('payment.cancel');

    Route::get('dashboard', fn (Request $request) => $request->user()->isAdmin()
        ? to_route('admin.dashboard')
        : to_route('member.home')
    )->name('dashboard');

    Route::get('/', Member\HomeController::class)->name('member.home');
    Route::redirect('home', '/');
    Route::get('today', Member\TodayController::class)->name('member.today');
    Route::get('exercise', Member\ExerciseController::class)->name('member.exercise');
    Route::get('member/exercises/search', Member\ExerciseSearchController::class)
        ->name('member.exercises.search');
    Route::get('exercise/{exercise}', Member\ExerciseShowController::class)
        ->name('member.exercises.show');
    Route::get('meal', Member\MealController::class)->name('member.meal');
    Route::get('meal/{mealPlanMeal}', Member\MealShowController::class)
        ->name('member.meals.show');
    Route::put('meal/{mealPlanMeal}/items/{item}/swap', [Member\MealSwapController::class, 'store'])
        ->name('member.meals.items.swap');
    Route::delete('meal/{mealPlanMeal}/items/{item}/swap', [Member\MealSwapController::class, 'destroy'])
        ->name('member.meals.items.swap.destroy');
    Route::get('playlists/{playlist}', [Member\PlaylistController::class, 'show'])
        ->name('member.playlists.show');
    Route::get('member/playlists/{playlist}/play', [Member\PlaylistController::class, 'play'])
        ->name('member.playlists.play');
    Route::get('profile', Member\ProfileController::class)->name('member.profile');
    Route::post('member/telegram/link', [Member\TelegramController::class, 'link'])
        ->name('member.telegram.link');
    Route::delete('member/telegram', [Member\TelegramController::class, 'disconnect'])
        ->name('member.telegram.disconnect');

    Route::post('shared/playlists/{playlist:share_token}/save', [Member\SharedPlaylistController::class, 'save'])
        ->name('playlists.shared.save');
    Route::post('shared/programs/{program:share_token}/save', [Member\SharedProgramController::class, 'save'])
        ->name('programs.shared.save');
    Route::post('shared/programs/{program:share_token}/start', [Member\SharedProgramController::class, 'start'])
        ->name('programs.shared.start');
    Route::post('shared/meal-programs/{mealProgram:share_token}/save', [Member\SharedMealProgramController::class, 'save'])
        ->name('meal-programs.shared.save');
    Route::get('member/meal-programs/{mealPlan}', [Member\MealProgramController::class, 'show'])
        ->name('member.meal-programs.show');
    Route::get('member/programs/{program}', [Member\ExerciseProgramController::class, 'show'])
        ->name('member.programs.show');
    Route::get('member/program-workouts/{workout}/play', [Member\ExerciseProgramController::class, 'play'])
        ->name('member.program-workouts.play');
    Route::post('member/playlists', [Member\PlaylistController::class, 'store'])
        ->name('member.playlists.store');
    Route::post('member/playlists/{playlist}/share', [Member\PlaylistController::class, 'share'])
        ->name('member.playlists.share');
    Route::delete('member/playlists/{playlist}/share', [Member\PlaylistController::class, 'unshare'])
        ->name('member.playlists.share.destroy');
    Route::patch('member/playlists/{playlist}', [Member\PlaylistController::class, 'update'])
        ->name('member.playlists.update');
    Route::delete('member/playlists/{playlist}', [Member\PlaylistController::class, 'destroy'])
        ->name('member.playlists.destroy');
    Route::post('member/playlists/{playlist}/exercises', [Member\PlaylistController::class, 'addExercise'])
        ->name('member.playlists.exercises.store');
    Route::delete('member/playlists/{playlist}/exercises/{exercise}', [Member\PlaylistController::class, 'removeExercise'])
        ->name('member.playlists.exercises.destroy');
    Route::post('member/sessions', [Member\SessionController::class, 'store'])
        ->name('member.sessions.store');
    Route::post('member/sessions/log-set', [Member\SessionController::class, 'logSet'])
        ->name('member.sessions.log-set');
    Route::get('member/sessions/{session}/exercises', [Member\SessionController::class, 'exercises'])
        ->name('member.sessions.exercises');
    Route::put('member/sessions/{session}/sets', [Member\SessionController::class, 'upsertSet'])
        ->name('member.sessions.sets.upsert');
    Route::delete('member/sessions/{session}/sets/{set}', [Member\SessionController::class, 'destroySet'])
        ->name('member.sessions.sets.destroy');
    Route::get('member/foods/search', Member\FoodSearchController::class)
        ->name('member.foods.search');
    Route::get('habits', [Member\HabitController::class, 'index'])
        ->name('member.habits');
    Route::post('member/habits', [Member\HabitController::class, 'store'])
        ->name('member.habits.store');
    Route::patch('member/habits/{habit}', [Member\HabitController::class, 'update'])
        ->name('member.habits.update');
    Route::delete('member/habits/{habit}', [Member\HabitController::class, 'destroy'])
        ->name('member.habits.destroy');
    Route::post('member/habits/{habit}/toggle', [Member\HabitController::class, 'toggle'])
        ->name('member.habits.toggle');

    Route::post('member/weight-logs', [Member\WeightLogController::class, 'store'])
        ->name('member.weight-logs.store');
    Route::delete('member/weight-logs/{weightLog}', [Member\WeightLogController::class, 'destroy'])
        ->name('member.weight-logs.destroy');
    Route::get('member/orders', [Member\ShopOrderController::class, 'index'])
        ->name('member.orders.index');
    Route::post('member/orders', [Member\ShopOrderController::class, 'store'])
        ->name('member.orders.store');
    Route::post('member/orders/{order}/cancel', [Member\ShopOrderController::class, 'cancel'])
        ->name('member.orders.cancel');
    Route::get('member/diary', [Member\FoodDiaryController::class, 'index'])
        ->name('member.diary.index');
    Route::post('member/diary', [Member\FoodDiaryController::class, 'store'])
        ->name('member.diary.store');
    Route::delete('member/diary/{foodDiaryEntry}', [Member\FoodDiaryController::class, 'destroy'])
        ->name('member.diary.destroy');
    Route::get('ambassador', [Member\AmbassadorController::class, 'show'])
        ->name('member.ambassador');
    Route::post('ambassador/payouts', [Member\AmbassadorController::class, 'storePayout'])
        ->name('member.ambassador.payouts.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
