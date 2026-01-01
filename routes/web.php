<?php

use App\Http\Controllers\RoomViewController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RoomViewController::class, 'home'])->name('home');
Route::get('/room/{code}', [RoomViewController::class, 'room'])->name('room');
Route::get('/room/{code}/lobby', [RoomViewController::class, 'lobby'])->name('lobby');
