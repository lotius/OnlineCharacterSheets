<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\GameSessionJoinController;
use App\Http\Controllers\GameSessionMemberController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [GameSessionController::class, 'index'])->name('dashboard');
    Route::get('/games', [GameSessionController::class, 'index'])->name('games.index');
    Route::get('/games/create', [GameSessionController::class, 'create'])->name('games.create');
    Route::post('/games', [GameSessionController::class, 'store'])->name('games.store');
    Route::post('/games/join', [GameSessionJoinController::class, 'store'])->name('games.join');
    Route::get('/games/{game}', [GameSessionController::class, 'show'])->name('games.show');
    Route::patch('/games/{game}/members/{member}/approve', [GameSessionMemberController::class, 'approve'])->name('games.members.approve');
    Route::patch('/games/{game}/members/{member}/reject', [GameSessionMemberController::class, 'reject'])->name('games.members.reject');
    Route::delete('/games/{game}/members/{member}', [GameSessionMemberController::class, 'destroy'])->name('games.members.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
