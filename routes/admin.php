<?php

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
        Route::post('trainers/promote', [TrainersController::class, 'promote'])->name('trainers.promote');
        Route::post('trainers/{trainer}/demote', [TrainersController::class, 'demote'])->name('trainers.demote');
        Route::post('trainers/{trainer}/suspend-bot', [TrainersController::class, 'suspendBot'])->name('trainers.suspend-bot');
        Route::delete('trainers/{trainer}', [TrainersController::class, 'destroy'])->name('trainers.destroy');
    });
