<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\GameVoteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [GameController::class, 'index'])->name('dashboard');

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('games', [GameController::class, 'store'])->name('games.store');
        Route::delete('games/{game}', [GameController::class, 'destroy'])->whereNumber('game')->name('games.destroy');

        Route::post('games/{game}/vote', [GameVoteController::class, 'store'])->whereNumber('game')->name('games.vote.store');
        Route::delete('games/{game}/vote', [GameVoteController::class, 'destroy'])->whereNumber('game')->name('games.vote.destroy');
    });
});

require __DIR__.'/settings.php';
