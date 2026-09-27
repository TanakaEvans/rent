<?php

use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\ChatController;
use App\Http\Middleware\EnsureHasRole;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

/*
 * In-app chat (Module 05 — Enquiries & Communication).
 *
 * Two conversation kinds: tenant ↔ owner about a property (`direct`) and
 * user ↔ ZimRent support (`support`). Every route is behind the same
 * authenticated stack as the rest of the app; chat is open to all signed-in
 * roles, so there is no `role:` gate on the user-facing group. The staff
 * support inbox sits behind the `admin` gate.
 *
 * These routes are loaded from bootstrap/app.php (withRouting `then`) so the
 * feature ships self-contained without editing the shared routes/web.php.
 */
Route::middleware(['web', 'auth', EnsurePasswordIsChanged::class, EnsureHasRole::class])->group(function () {
    // Any authenticated role: my conversations + unread badge.
    Route::get('/chat', [ChatController::class, 'index'])
        ->name('chat.index')
        ->defaults('description', 'View my chat conversations');
    Route::get('/chat/unread', [ChatController::class, 'unread'])
        ->name('chat.unread')
        ->defaults('description', 'Get my unread chat message count');
    Route::post('/chat/direct/{property}', [ChatController::class, 'startDirect'])
        ->name('chat.start-direct')
        ->whereNumber('property')
        ->defaults('description', 'Start a chat with a property owner');
    Route::post('/chat/support', [ChatController::class, 'startSupport'])
        ->name('chat.start-support')
        ->defaults('description', 'Start a chat with ZimRent support');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])
        ->name('chat.show')
        ->whereNumber('conversation')
        ->defaults('description', 'Open a chat conversation');
    Route::post('/chat/{conversation}/message', [ChatController::class, 'message'])
        ->name('chat.message')
        ->whereNumber('conversation')
        ->defaults('description', 'Post a message to a conversation');

    // Staff support inbox (Admin / Superuser).
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('support', [SupportController::class, 'index'])
            ->name('support.index')
            ->defaults('description', 'View the support conversation inbox');
        Route::get('support/{conversation}', [SupportController::class, 'show'])
            ->name('support.show')
            ->whereNumber('conversation')
            ->defaults('description', 'Open a support conversation');
        Route::post('support/{conversation}/reply', [SupportController::class, 'reply'])
            ->name('support.reply')
            ->whereNumber('conversation')
            ->defaults('description', 'Reply to a support conversation');
    });
});
