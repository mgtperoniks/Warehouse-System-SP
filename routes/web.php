<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OpnameController;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/scan', [ScanController::class, 'index'])->name('scan');
Route::get('/items', [ItemController::class, 'index'])->name('items');
Route::get('/opname', [OpnameController::class, 'index'])->name('opname');
Route::get('/stock-in', \App\Livewire\Stock\StockInPage::class)->name('stock-in');

