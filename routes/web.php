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
    Route::get('/items/planning', \App\Livewire\Items\InventoryPlanningPage::class)->name('items.planning');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::get('/items/{variant}', [ItemController::class, 'show'])->name('items.show');
    Route::get('/items/{variant}/edit', [ItemController::class, 'edit'])->name('items.edit');
    Route::put('/items/{variant}', [ItemController::class, 'update'])->name('items.update');
    Route::post('/items/import', [ItemController::class, 'import'])->name('items.import');
    Route::get('/opname', \App\Livewire\Opname\OpnamePage::class)->name('opname');
    Route::get('/inventory-adjustments', \App\Livewire\Governance\InventoryAdjustmentsPage::class)->name('inventory-adjustments');
    Route::get('/governance/baso/view/{id}', [\App\Http\Controllers\Governance\BasoController::class, 'view'])->name('governance.baso.view');
    Route::get('/stock-in', [StockController::class, 'index'])->name('stock-in');
    Route::get('/barcode-printing', \App\Livewire\Barcode\PrintPage::class)->name('barcode.printing');
    Route::post('/warehouse/switch/{id}', [\App\Http\Controllers\WarehouseSwitchController::class, 'switchWarehouse'])->name('warehouse.switch');

    // Reports Hub & Legacy ERP Transfer Bridge
    Route::get('/reports/stock-out', \App\Livewire\Reports\StockOutReport::class)->name('reports.stock-out');
    Route::get('/reports/stock-in', \App\Livewire\Reports\StockInReport::class)->name('reports.stock-in');
    Route::get('/reports/stock-out/csv', [\App\Http\Controllers\ReportController::class, 'exportStockOutCsv'])->name('reports.stock-out.csv');
    Route::get('/reports/stock-in/csv', [\App\Http\Controllers\ReportController::class, 'exportStockInCsv'])->name('reports.stock-in.csv');
    Route::get('/reports/stock-out/preview', [\App\Http\Controllers\ReportController::class, 'previewStockOut'])->name('reports.stock-out.preview');
    Route::get('/reports/stock-out/print', [\App\Http\Controllers\ReportController::class, 'printStockOut'])->name('reports.stock-out.print');
    Route::get('/reports/stock-in/print', [\App\Http\Controllers\ReportController::class, 'printStockIn'])->name('reports.stock-in.print');
    Route::get('/reports/movement-ledger', \App\Livewire\Reports\MovementLedgerReport::class)->name('reports.movement-ledger');
    Route::get('/reports/movement-ledger/print', [\App\Http\Controllers\ReportController::class, 'printMovementLedger'])->name('reports.movement-ledger.print');
    Route::get('/reports/movement-ledger/csv', [\App\Http\Controllers\ReportController::class, 'exportMovementLedgerCsv'])->name('reports.movement-ledger.csv');

    // Settings / Master Data
    Route::get('/settings/departments', \App\Livewire\Settings\DepartmentPage::class)->name('settings.departments');
    Route::get('/settings/users', \App\Livewire\Settings\UserPage::class)->name('settings.users');

    // Test Routes
    Route::get('/barcode/test-print', [\App\Http\Controllers\BarcodeTestController::class, 'index'])->name('barcode.test-print');
});

Route::get('/diagnostic', function () {
    return response()->json([
        'app_url' => config('app.url'),
        'request_url' => request()->url(),
        'request_full_url' => request()->fullUrl(),
        'host' => request()->getHost(),
        'http_host' => request()->getHttpHost(),
        'port' => request()->getPort(),
        'scheme' => request()->getScheme(),
        'server_port' => request()->server('SERVER_PORT'),
        'remote_addr' => request()->server('REMOTE_ADDR'),
        'headers' => request()->headers->all(),
        'has_valid_signature' => request()->hasValidSignature(),
        'generated_upload_url' => \Livewire\Facades\GenerateSignedUploadUrlFacade::forLocal(),
    ]);
});

Route::get('/view-logs', function () {
    $logPath = storage_path('logs/laravel.log');
    if (!file_exists($logPath)) {
        return response('Log file does not exist.', 404);
    }
    $content = file_get_contents($logPath);
    $lines = explode("\n", $content);
    $matches = array_filter($lines, function ($line) {
        return str_contains($line, 'LIVEWIRE_UPLOAD_DEBUG');
    });
    return response()->json(array_values(array_slice($matches, -5)));
});

Route::post(Livewire\Mechanisms\HandleRequests\EndpointResolver::uploadPath(), [App\Http\Controllers\LivewireDebugController::class, 'handle'])
    ->middleware('web');






