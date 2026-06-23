cat > /var/www/linkluv/routes/web.php << 'EOF'
<?php

use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Models\Message;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return redirect()->route('feed');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/feed', function () {
    return view('feed');
})->middleware(['auth', 'verified'])->name('feed');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');

    // ✅ 加入 GET /messages
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::patch('/messages/{message}', [MessageController::class, 'update'])->name('messages.update');
    Route::post('/messages/{message}/like', [MessageController::class, 'like'])->name('messages.like');
});

Route::get('/profile/{id}', function ($id) {
    return view('profile.show', ['id' => $id]);
})->name('profile.show');

require __DIR__.'/auth.php';
