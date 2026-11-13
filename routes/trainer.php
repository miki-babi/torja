<?php

use App\Http\Controllers\Trainer\BotController;
use App\Http\Controllers\Trainer\BroadcastsController;
use App\Http\Controllers\Trainer\CheckInsController;
use App\Http\Controllers\Trainer\ClientsController;
use App\Http\Controllers\Trainer\DashboardController;
use App\Http\Controllers\Trainer\EarningsController;
use App\Http\Controllers\Trainer\ExercisesController;
use App\Http\Controllers\Trainer\FoodsController;
use App\Http\Controllers\Trainer\FoodSearchController;
use App\Http\Controllers\Trainer\LeadFormController;
use App\Http\Controllers\Trainer\LeadsController;
use App\Http\Controllers\Trainer\MealProgramsController;
use App\Http\Controllers\Trainer\PlansController;
use App\Http\Controllers\Trainer\ProfileController;
use App\Http\Controllers\Trainer\ProgramsController;
use App\Http\Controllers\Trainer\RegisterController;
use App\Http\Controllers\Trainer\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('trainer/register', [RegisterController::class, 'create'])
        ->name('trainer.register');
    Route::post('trainer/register', [RegisterController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('trainer.register.store');
});

Route::middleware(['auth', 'trainer'])
    ->prefix('trainer')
    ->name('trainer.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('bot', [BotController::class, 'edit'])->name('bot.edit');
        Route::post('bot', [BotController::class, 'store'])->name('bot.store');
        Route::patch('bot', [BotController::class, 'update'])->name('bot.update');
        Route::post('bot/reconnect', [BotController::class, 'reconnect'])
            ->name('bot.reconnect');
        Route::delete('bot', [BotController::class, 'destroy'])->name('bot.destroy');

        Route::get('clients', [ClientsController::class, 'index'])->name('clients.index');
        Route::get('clients/{user}', [ClientsController::class, 'show'])->name('clients.show');
        Route::delete('clients/{user}', [ClientsController::class, 'destroy'])
            ->name('clients.destroy');
        Route::post('clients/{user}/message', [ClientsController::class, 'message'])
            ->name('clients.message');

        Route::resource('programs', ProgramsController::class)->except(['show']);
        Route::post('programs/{program}/share', [ProgramsController::class, 'share'])
            ->name('programs.share');
        Route::delete('programs/{program}/share', [ProgramsController::class, 'unshare'])
            ->name('programs.unshare');
        Route::post('programs/{program}/assign', [ProgramsController::class, 'assign'])
            ->name('programs.assign');
        Route::post('programs/{program}/copy-template', [ProgramsController::class, 'copyTemplate'])
            ->name('programs.copy-template');

        Route::resource('exercises', ExercisesController::class)->except(['show']);

        Route::get('foods/search', FoodSearchController::class)
            ->name('foods.search');

        Route::resource('foods', FoodsController::class)->except(['show']);

        Route::resource('meal-programs', MealProgramsController::class)
            ->except(['show'])
            ->parameters(['meal-programs' => 'mealProgram']);
        Route::post('meal-programs/{mealProgram}/share', [MealProgramsController::class, 'share'])
            ->name('meal-programs.share');
        Route::delete('meal-programs/{mealProgram}/share', [MealProgramsController::class, 'unshare'])
            ->name('meal-programs.unshare');
        Route::post('meal-programs/{mealProgram}/assign', [MealProgramsController::class, 'assign'])
            ->name('meal-programs.assign');

        Route::resource('plans', PlansController::class)->except(['show']);
        Route::post('plans/{plan}/toggle', [PlansController::class, 'toggle'])
            ->name('plans.toggle');

        Route::get('earnings', EarningsController::class)->name('earnings');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('leads', [LeadsController::class, 'index'])->name('leads.index');
        Route::get('lead-form', [LeadFormController::class, 'edit'])->name('lead-form.edit');
        Route::patch('lead-form', [LeadFormController::class, 'update'])->name('lead-form.update');
        Route::get('check-ins', [CheckInsController::class, 'index'])
            ->name('check-ins.index');
        Route::post('check-ins/{checkIn}/reply', [CheckInsController::class, 'reply'])
            ->name('check-ins.reply');

        Route::get('broadcasts', [BroadcastsController::class, 'index'])
            ->name('broadcasts.index');
        Route::post('broadcasts', [BroadcastsController::class, 'store'])
            ->name('broadcasts.store');
    });
