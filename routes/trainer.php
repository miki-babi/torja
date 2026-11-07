<?php

use App\Http\Controllers\Trainer\BotController;
use App\Http\Controllers\Trainer\BroadcastsController;
use App\Http\Controllers\Trainer\ClientsController;
use App\Http\Controllers\Trainer\DashboardController;
use App\Http\Controllers\Trainer\FoodSearchController;
use App\Http\Controllers\Trainer\MealProgramsController;
use App\Http\Controllers\Trainer\ProgramsController;
use App\Http\Controllers\Trainer\RegisterController;
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

        Route::get('foods/search', FoodSearchController::class)
            ->name('foods.search');

        Route::resource('meal-programs', MealProgramsController::class)
            ->except(['show'])
            ->parameters(['meal-programs' => 'mealProgram']);
        Route::post('meal-programs/{mealProgram}/share', [MealProgramsController::class, 'share'])
            ->name('meal-programs.share');
        Route::delete('meal-programs/{mealProgram}/share', [MealProgramsController::class, 'unshare'])
            ->name('meal-programs.unshare');
        Route::post('meal-programs/{mealProgram}/assign', [MealProgramsController::class, 'assign'])
            ->name('meal-programs.assign');

        Route::get('broadcasts', [BroadcastsController::class, 'index'])
            ->name('broadcasts.index');
        Route::post('broadcasts', [BroadcastsController::class, 'store'])
            ->name('broadcasts.store');
    });
