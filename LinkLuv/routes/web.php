<?php

use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Models\Message;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $messages = Message::whereNull('parent_id')->get();
    return view('dashboard', compact('messages'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/feed', function () {
    return view('feed');
})->middleware(['auth', 'verified'])->name('feed');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::delete('/messages/{id}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::get('/api/messages', [MessageController::class, 'index']); // 更新 API 端點
});

Route::get('/profile/{id}', function ($id) {
    return view('profile.show', ['id' => $id]);
})->name('profile.show');

require __DIR__.'/auth.php';
