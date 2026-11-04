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
    ->middleware('throttle:300,1')
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
    Route::post('member/workouts/build', Member\WorkoutBuilderController::class)
        ->name('member.workouts.build');
    Route::get('exercise', Member\ExerciseController::class)->name('member.exercise');
    Route::get('progress', Member\ProgressController::class)->name('member.progress');
    Route::get('progress/records', Member\PersonalRecordsController::class)->name('member.records');
    Route::get('progress/history', Member\WorkoutHistoryController::class)->name('member.history');
    Route::get('member/exercises/search', Member\ExerciseSearchController::class)
        ->name('member.exercises.search');
    Route::get('exercise/{exercise}', Member\ExerciseShowController::class)
        ->name('member.exercises.show');
    Route::get('meal', Member\MealController::class)->name('member.meal');
    Route::get('meal-plans/create', Member\MealPlanBuilderController::class)
        ->name('member.meal-plans.create');
    Route::post('meal-plans', Member\MealPlanStoreController::class)
        ->name('member.meal-plans.store');
    Route::get('meal/{mealPlanMeal}', Member\MealShowController::class)
        ->name('member.meals.show');
    Route::get('playlists/{playlist}', [Member\PlaylistController::class, 'show'])
        ->name('member.playlists.show');
    Route::get('member/playlists/{playlist}/play', [Member\PlaylistController::class, 'play'])
        ->name('member.playlists.play');
    Route::get('profile', Member\ProfileController::class)->name('member.profile');
    Route::post('member/telegram/link', [Member\TelegramController::class, 'link'])
        ->name('member.telegram.link');
    Route::delete('member/telegram', [Member\TelegramController::class, 'disconnect'])
        ->name('member.telegram.disconnect');
    Route::post('member/location', [Member\LocationController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('member.location.store');

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
    Route::post('member/sessions/{session}/finish', [Member\SessionController::class, 'finish'])
        ->name('member.sessions.finish');
    Route::post('member/sessions/log-set', [Member\SessionController::class, 'logSet'])
        ->name('member.sessions.log-set');
    Route::post('member/sessions/backfill', [Member\SessionController::class, 'storeBackfill'])
        ->name('member.sessions.backfill');
    Route::get('member/sessions/{session}/exercises', [Member\SessionController::class, 'exercises'])
        ->name('member.sessions.exercises');
    Route::get('member/sessions/{session}/play', [Member\SessionController::class, 'play'])
        ->name('member.sessions.play');
    Route::put('member/sessions/{session}/sets', [Member\SessionController::class, 'upsertSet'])
        ->name('member.sessions.sets.upsert');
    Route::delete('member/sessions/{session}/sets/{set}', [Member\SessionController::class, 'destroySet'])
        ->name('member.sessions.sets.destroy');

    Route::get('member/recaps', [Member\RecapController::class, 'index'])
        ->name('member.recaps.index');
    Route::post('member/recaps', [Member\RecapController::class, 'store'])
        ->name('member.recaps.store');
    Route::patch('member/recaps/{recap}', [Member\RecapController::class, 'update'])
        ->name('member.recaps.update');
    Route::delete('member/recaps/{recap}', [Member\RecapController::class, 'destroy'])
        ->name('member.recaps.destroy');
    Route::post('member/recaps/{recap}/bravo', [Member\RecapController::class, 'bravo'])
        ->middleware('throttle:60,1')
        ->name('member.recaps.bravo');
    Route::get('member/recaps/{recap}/comments', [Member\RecapController::class, 'comments'])
        ->name('member.recaps.comments.index');
    Route::post('member/recaps/{recap}/comments', [Member\RecapController::class, 'storeComment'])
        ->middleware('throttle:30,1')
        ->name('member.recaps.comments.store');
    Route::delete('member/recaps/{recap}/comments/{comment}', [Member\RecapController::class, 'destroyComment'])
        ->name('member.recaps.comments.destroy');
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
    Route::post('member/measurements', [Member\MeasurementController::class, 'store'])
        ->name('member.measurements.store');
    Route::delete('member/measurements/{measurement}', [Member\MeasurementController::class, 'destroy'])
        ->name('member.measurements.destroy');
    Route::post('member/progress-photos', [Member\ProgressPhotoController::class, 'store'])
        ->name('member.progress-photos.store');
    Route::delete('member/progress-photos/{progressPhoto}', [Member\ProgressPhotoController::class, 'destroy'])
        ->name('member.progress-photos.destroy');

    Route::get('member/ranks', [Member\RanksController::class, 'index'])->name('member.ranks');
    Route::post('member/ranks/opt-in', [Member\RanksController::class, 'optIn'])
        ->name('member.ranks.opt-in');
    Route::post('member/ranks/opt-out', [Member\RanksController::class, 'optOut'])
        ->name('member.ranks.opt-out');
    Route::get('member/ranks/friends/search', [Member\FriendController::class, 'search'])
        ->name('member.ranks.friends.search');
    Route::post('member/ranks/friends', [Member\FriendController::class, 'store'])
        ->name('member.ranks.friends.store');
    Route::post('member/ranks/friends/{friendship}/accept', [Member\FriendController::class, 'accept'])
        ->name('member.ranks.friends.accept');
    Route::delete('member/ranks/friends/{friendship}', [Member\FriendController::class, 'destroy'])
        ->name('member.ranks.friends.destroy');
    Route::get('member/ranks/groups/search', [Member\GroupController::class, 'search'])
        ->name('member.ranks.groups.search');
    Route::post('member/ranks/groups', [Member\GroupController::class, 'store'])
        ->name('member.ranks.groups.store');
    Route::post('member/ranks/groups/join', [Member\GroupController::class, 'join'])
        ->name('member.ranks.groups.join');
    Route::delete('member/ranks/groups/{group}', [Member\GroupController::class, 'destroy'])
        ->name('member.ranks.groups.destroy');
    Route::get('member/ranks/gyms', [Member\GymController::class, 'index'])
        ->name('member.ranks.gyms.index');
    Route::post('member/ranks/gym', [Member\GymController::class, 'store'])
        ->name('member.ranks.gym.store');
    Route::delete('member/ranks/gym', [Member\GymController::class, 'destroy'])
        ->name('member.ranks.gym.destroy');

    Route::get('member/messages', [Member\ConversationController::class, 'index'])
        ->name('member.messages');
    Route::post('member/messages/dm', [Member\ConversationController::class, 'dm'])
        ->name('member.messages.dm');

    Route::scopeBindings()->group(function () {
        Route::get('member/messages/{conversation}', [Member\ConversationController::class, 'show'])
            ->name('member.messages.show');
        Route::get('member/messages/{conversation}/older', [Member\ConversationController::class, 'older'])
            ->name('member.messages.older');
        Route::post('member/messages/{conversation}/read', [Member\ConversationController::class, 'read'])
            ->name('member.messages.read');
        Route::post('member/messages/{conversation}/mute', [Member\ConversationController::class, 'mute'])
            ->name('member.messages.mute');
        Route::post('member/messages/{conversation}/unmute', [Member\ConversationController::class, 'unmute'])
            ->name('member.messages.unmute');
        Route::post('member/messages/{conversation}/messages', [Member\MessageController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('member.messages.store');
        Route::patch('member/messages/{conversation}/messages/{message}', [Member\MessageController::class, 'update'])
            ->name('member.messages.update');
        Route::delete('member/messages/{conversation}/messages/{message}', [Member\MessageController::class, 'destroy'])
            ->name('member.messages.destroy');
        Route::post('member/messages/{conversation}/messages/{message}/reactions', [Member\MessageController::class, 'react'])
            ->name('member.messages.react');
    });
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
