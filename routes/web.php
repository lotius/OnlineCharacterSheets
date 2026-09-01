<?php

use App\Http\Controllers\AdminGameSystemController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CharacterAssignmentController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\CharacterEquipmentController;
use App\Http\Controllers\CharacterItemController;
use App\Http\Controllers\CharacterNoteController;
use App\Http\Controllers\CharacterSkillController;
use App\Http\Controllers\CharacterSpellController;
use App\Http\Controllers\CharacterStatController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\GameSessionJoinController;
use App\Http\Controllers\GameSessionMemberController;
use App\Http\Controllers\GameSessionViewerController;
use App\Http\Controllers\PersonalCharacterSheetController;
use App\Http\Controllers\PublicRoomController;
use App\Http\Controllers\RoomRealtimeController;
use Illuminate\Support\Facades\Route;

Route::pattern('game', '[0-9]+');

Route::inertia('/', 'Welcome')->name('home');
Route::get('/rooms', [PublicRoomController::class, 'index'])->name('public-rooms.index');
Route::get('/games/{game}', [GameSessionController::class, 'show'])->name('games.show');
Route::get('/games/{game}/characters/{character}', [PersonalCharacterSheetController::class, 'show'])->name('games.characters.show');
Route::post('/games/{game}/viewers', [GameSessionViewerController::class, 'store'])->name('games.viewers.store');

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
    Route::patch('/games/{game}', [GameSessionController::class, 'update'])->name('games.update');
    Route::post('/games/join', [GameSessionJoinController::class, 'store'])->name('games.join');
    Route::get('/library', [CatalogController::class, 'personal'])->name('library');
    Route::post('/library/{version}/{type}', [CatalogController::class, 'storePersonal'])->name('library.store');
    Route::patch('/library/{version}/{type}/{definition}', [CatalogController::class, 'updatePersonal'])->name('library.update');
    Route::delete('/library/{type}/{definition}', [CatalogController::class, 'destroyPersonal'])->name('library.destroy');
    Route::post('/games/{game}/realtime/ping', [RoomRealtimeController::class, 'store'])->name('games.realtime.ping');
    Route::post('/games/{game}/characters', [CharacterController::class, 'store'])->name('games.characters.store');
    Route::delete('/games/{game}/characters/{sessionCharacter}', [CharacterController::class, 'destroy'])->name('games.characters.destroy');
    Route::patch('/games/{game}/characters/{sessionCharacter}/assignment', [CharacterAssignmentController::class, 'update'])->name('games.characters.assignment.update');
    Route::delete('/games/{game}/characters/{sessionCharacter}/assignment', [CharacterAssignmentController::class, 'destroy'])->name('games.characters.assignment.destroy');
    Route::patch('/characters/{character}/stats/{stat}', [CharacterStatController::class, 'update'])->name('characters.stats.update');
    Route::post('/characters/{character}/items', [CharacterItemController::class, 'store'])->name('characters.items.store');
    Route::patch('/characters/{character}/items/{item}', [CharacterItemController::class, 'update'])->name('characters.items.update');
    Route::delete('/characters/{character}/items/{item}', [CharacterItemController::class, 'destroy'])->name('characters.items.destroy');
    Route::put('/characters/{character}/items/{item}/slots/{slot}', [CharacterEquipmentController::class, 'update'])->name('characters.items.slots.update');
    Route::delete('/characters/{character}/items/{item}/slots/{slot}', [CharacterEquipmentController::class, 'destroy'])->name('characters.items.slots.destroy');
    Route::post('/characters/{character}/spells', [CharacterSpellController::class, 'store'])->name('characters.spells.store');
    Route::patch('/characters/{character}/spells/{spell}', [CharacterSpellController::class, 'update'])->name('characters.spells.update');
    Route::delete('/characters/{character}/spells/{spell}', [CharacterSpellController::class, 'destroy'])->name('characters.spells.destroy');
    Route::post('/characters/{character}/skills', [CharacterSkillController::class, 'store'])->name('characters.skills.store');
    Route::patch('/characters/{character}/skills/{skill}', [CharacterSkillController::class, 'update'])->name('characters.skills.update');
    Route::delete('/characters/{character}/skills/{skill}', [CharacterSkillController::class, 'destroy'])->name('characters.skills.destroy');
    Route::put('/characters/{character}/notes', [CharacterNoteController::class, 'update'])->name('characters.notes.update');
    Route::patch('/games/{game}/members/{member}/approve', [GameSessionMemberController::class, 'approve'])->name('games.members.approve');
    Route::patch('/games/{game}/members/{member}/reject', [GameSessionMemberController::class, 'reject'])->name('games.members.reject');
    Route::delete('/games/{game}/members/{member}', [GameSessionMemberController::class, 'destroy'])->name('games.members.destroy');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/systems', [AdminGameSystemController::class, 'index'])->name('admin.systems');
        Route::post('/systems', [AdminGameSystemController::class, 'store'])->name('admin.systems.store');
        Route::post('/systems/{system}/versions', [AdminGameSystemController::class, 'storeVersion'])->name('admin.versions.store');
        Route::get('/catalog', [CatalogController::class, 'admin'])->name('admin.catalog');
        Route::post('/catalog/{version}/{type}', [CatalogController::class, 'storeAdmin'])->name('admin.catalog.store');
        Route::patch('/catalog/{version}/{type}/{definition}', [CatalogController::class, 'updateAdmin'])->name('admin.catalog.update');
        Route::delete('/catalog/{type}/{definition}', [CatalogController::class, 'destroyAdmin'])->name('admin.catalog.destroy');
    });
});
