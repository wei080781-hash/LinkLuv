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
// 所有需要登入權限的操作都放在這裡
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    // 訊息相關路由
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/api/messages', [MessageController::class, 'index']); // 更新 API 端點
    Route::delete('/messages/{message}', [App\Http\Controllers\MessageController::class, 'destroy'])
    ->name('messages.destroy');
    // 更新訊息內容的路由
    Route::patch('/messages/{message}',
    [MessageController::class, 'update'])->name('messages.update');
});
// 點擊使用者名稱後的個人頁面路由
Route::get('/profile/{id}', function ($id) {
    return view('profile.show', ['id' => $id]);
})->name('profile.show');

require __DIR__.'/auth.php';

// 路由設定 (routes/web.php)
Route::post('/messages/{message}/like',
[MessageController::class, 'like'])->name('messages.like');