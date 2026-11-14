<?php

use App\Http\Controllers\Admin\ExercisesController;
use App\Http\Controllers\Admin\PaymentsController;
use App\Http\Controllers\Admin\PayoutsController;
use App\Http\Controllers\Admin\TrainersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('admin.trainers.index'))
            ->name('dashboard');

        Route::get('trainers', [TrainersController::class, 'index'])->name('trainers.index');
        Route::get('trainers/{trainer}', [TrainersController::class, 'show'])->name('trainers.show');
        Route::get('trainers/{trainer}/programs', [TrainersController::class, 'programs'])->name('trainers.programs');
        Route::post('trainers/promote', [TrainersController::class, 'promote'])->name('trainers.promote');
        Route::post('trainers/{trainer}/demote', [TrainersController::class, 'demote'])->name('trainers.demote');
        Route::post('trainers/{trainer}/suspend-bot', [TrainersController::class, 'suspendBot'])->name('trainers.suspend-bot');
        Route::post('trainers/{trainer}/suspend', [TrainersController::class, 'suspend'])->name('trainers.suspend');
        Route::post('trainers/{trainer}/unsuspend', [TrainersController::class, 'unsuspend'])->name('trainers.unsuspend');
        Route::delete('trainers/{trainer}', [TrainersController::class, 'destroy'])->name('trainers.destroy');

        Route::get('payments', [PaymentsController::class, 'index'])->name('payments.index');

        Route::get('payouts', [PayoutsController::class, 'index'])->name('payouts.index');
        Route::post('payouts/{trainer}', [PayoutsController::class, 'store'])->name('payouts.store');
        Route::post('payouts/{payout}/sent', [PayoutsController::class, 'sent'])->name('payouts.sent');

        Route::get('exercises', [ExercisesController::class, 'index'])->name('exercises.index');
        Route::get('exercises/{exercise}/edit', [ExercisesController::class, 'edit'])->name('exercises.edit');
        Route::put('exercises/{exercise}', [ExercisesController::class, 'update'])->name('exercises.update');
    });
