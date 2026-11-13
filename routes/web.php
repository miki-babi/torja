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
use App\Http\Controllers\Settings\LocalizationController;
use App\Http\Controllers\ShortLinkController;
use App\Http\Controllers\Telegram\TenantWebhookController;
use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::redirect('welcome', '/')->name('home');

Route::post('telegram/webhook', WebhookController::class)
    ->middleware('throttle:300,1')
    ->name('telegram.webhook');

Route::post('telegram/webhook/t/{trainer:slug}', TenantWebhookController::class)
    ->middleware('throttle:300,1')
    ->name('telegram.tenant.webhook');

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
    ->middleware(['auth'])
    ->name('plan.preview');

Route::post('webhooks/jami', [JamiWebhookController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('payment.jami.webhook');

Route::get('shared/programs/{program:share_token}', [Member\SharedProgramController::class, 'show'])
    ->name('programs.shared.show');
Route::get('shared/programs/{program:share_token}/lead', [Member\ProgramLeadController::class, 'create'])
    ->name('programs.shared.lead');
Route::post('shared/programs/{program:share_token}/lead', [Member\ProgramLeadController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('programs.shared.lead.store');
Route::get('shared/programs/{program:share_token}/lead/skip', [Member\ProgramLeadController::class, 'skip'])
    ->name('programs.shared.lead.skip');
Route::get('shared/programs/{program:share_token}/lead/thanks', [Member\ProgramLeadController::class, 'thanks'])
    ->name('programs.shared.lead.thanks');

Route::get('shared/meal-programs/{mealProgram:share_token}', [Member\SharedMealProgramController::class, 'show'])
    ->name('meal-programs.shared.show');

Route::get('s/{code}', ShortLinkController::class)->name('links.resolve');

Route::get('t/{trainer:slug}', Member\TenantEntryController::class)
    ->name('tenant.entry');

Route::get('r/{code}', AmbassadorRedirectController::class)->name('invite.show');

Route::middleware(['auth', 'member.coach'])->group(function () {
    Route::inertia('member/no-coach', 'member/no-coach')
        ->name('member.no-coach');

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

    Route::get('dashboard', fn (Request $request) => $request->user()->isStaff()
        ? to_route('admin.dashboard')
        : ($request->user()->isTrainer()
            ? to_route('trainer.dashboard')
            : to_route('member.home'))
    )->name('dashboard');

    Route::get('/', Member\TodayController::class)->name('member.home');
    Route::redirect('home', '/');
    Route::redirect('today', '/')->name('member.today');
    Route::get('progress', Member\ProgressController::class)->name('member.progress');
    Route::get('progress/history', Member\WorkoutHistoryController::class)->name('member.history');
    Route::get('member/exercises/search', Member\ExerciseSearchController::class)
        ->name('member.exercises.search');
    Route::get('profile', Member\ProfileController::class)->name('member.profile');
    Route::post('member/telegram/link', [Member\TelegramController::class, 'link'])
        ->name('member.telegram.link');
    Route::delete('member/telegram', [Member\TelegramController::class, 'disconnect'])
        ->name('member.telegram.disconnect');

    Route::post('shared/programs/{program:share_token}/save', [Member\SharedProgramController::class, 'save'])
        ->name('programs.shared.save');
    Route::post('shared/programs/{program:share_token}/start', [Member\SharedProgramController::class, 'start'])
        ->name('programs.shared.start');
    Route::post('shared/programs/{program:share_token}/checkout', [Member\SharedProgramController::class, 'checkout'])
        ->name('programs.shared.checkout');
    Route::post('shared/meal-programs/{mealProgram:share_token}/save', [Member\SharedMealProgramController::class, 'save'])
        ->name('meal-programs.shared.save');
    Route::post('shared/meal-programs/{mealProgram:share_token}/checkout', [Member\SharedMealProgramController::class, 'checkout'])
        ->name('meal-programs.shared.checkout');
    Route::get('member/meal-programs/{mealPlan}', [Member\MealProgramController::class, 'show'])
        ->name('member.meal-programs.show');
    Route::get('member/programs/{program}', [Member\ExerciseProgramController::class, 'show'])
        ->name('member.programs.show');
    Route::get('member/program-workouts/{workout}/play', [Member\ExerciseProgramController::class, 'play'])
        ->name('member.program-workouts.play');
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
    Route::get('coaching', [Member\CoachingController::class, 'index'])
        ->name('member.coaching');
    Route::get('member/check-ins', [Member\CheckInController::class, 'index'])
        ->name('member.check-ins.index');
    Route::post('member/check-ins', [Member\CheckInController::class, 'store'])
        ->name('member.check-ins.store');
    Route::patch('member/reminders', [Member\RemindersController::class, 'update'])
        ->name('member.reminders.update');
    Route::get('member/plans/{plan}', [Member\CoachingController::class, 'show'])
        ->name('member.plans.show');
    Route::post('member/plans/{plan}/checkout', [Member\CoachingController::class, 'checkout'])
        ->name('member.plans.checkout');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
