<?php

use App\Http\Controllers\ToolAssistantController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/tools', [ToolAssistantController::class, 'index'])->name('tools.index');
Route::post('/tools/chat', [ToolAssistantController::class, 'chat'])
    ->middleware('throttle:20,1')
    ->name('tools.chat');
