<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OpnameController;
use App\Http\Controllers\StockController;

Route::get('/login', \App\Livewire\Auth\LoginPage::class)->name('login')->middleware('guest');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::post('/logout', function () {
        \Illuminate\Support\Facades\Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->route('login');
    })->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/scan', [ScanController::class, 'index'])->name('scan');
    Route::get('/items', [ItemController::class, 'index'])->name('items');
    Route::get('/items/create', [ItemController::class, 'create'])->name('items.create');
    Route::get('/items/bulk-import', \App\Livewire\Items\BulkImport::class)->name('items.bulk-import');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/{variant}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{variant}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{variant}', [ItemController::class, 'update'])->name('items.update');
    Route::post('/items/import', [ItemController::class, 'import'])->name('items.import');
    Route::get('/opname', \App\Livewire\Opname\OpnamePage::class)->name('opname');
    Route::get('/stock-in', [StockController::class, 'index'])->name('stock-in');
    Route::get('/barcode-printing', \App\Livewire\Barcode\PrintPage::class)->name('barcode.printing');

    // Settings / Master Data
    Route::get('/settings/departments', \App\Livewire\Settings\DepartmentPage::class)->name('settings.departments');
    Route::get('/settings/users', \App\Livewire\Settings\UserPage::class)->name('settings.users');

    // Test Routes
    Route::get('/barcode/test-print', [\App\Http\Controllers\BarcodeTestController::class, 'index'])->name('barcode.test-print');
});

