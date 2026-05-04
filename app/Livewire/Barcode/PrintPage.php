<?php

namespace App\Livewire\Barcode;

use App\Models\ItemVariant;
use App\Models\StockMovement;
use App\Models\BarcodePrintSetting;
use App\Services\Barcode\PrintService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class PrintPage extends Component
{
    // ─── Search ─────────────────────────────────────────────────────────────────
    public string $searchString      = '';
    public ?int   $selectedVariantId = null;

    // ─── Configuration ──────────────────────────────────────────────────────────
    public string $labelType   = 'ITEM_LABEL'; // ITEM_LABEL | BIN_LABEL
    public string $printerType = 'EPSON';      // TSC | EPSON
    public string $binCode     = '';
    public string $printerIp   = '';
    public int    $copies      = 1;

    // ─── UI State ───────────────────────────────────────────────────────────────
    public string $flashMessage      = '';
    public string $flashType         = 'success'; // success | error
    public array  $validationErrors  = [];

    // ─── Lifecycle ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->loadDefaultSettings();
    }

    private function loadDefaultSettings(): void
    {
        $settings = BarcodePrintSetting::getSettings();
        
        $this->labelType   = $settings->default_label_type;
        $this->printerType = $settings->default_printer_type;
        $this->printerIp   = $settings->default_printer_ip ?? '';
        $this->copies      = $settings->default_copies;
    }

    // ─── Computed: Selected Variant ──────────────────────────────────────────────

    #[Computed]
    public function selectedVariant(): ?ItemVariant
    {
        if (! $this->selectedVariantId) {
            return null;
        }

        return ItemVariant::with(['item', 'barcodes', 'bins'])
            ->find($this->selectedVariantId);
    }

    #[Computed]
    public function lastStockInDate(): string
    {
        if (!$this->selectedVariantId) {
            return '-';
        }

        $movement = StockMovement::where('item_variant_id', $this->selectedVariantId)
            ->where('type', 'IN')
            ->orderByDesc('created_at')
            ->first();

        return $movement ? $movement->created_at->format('Y-m-d') : 'No History';
    }

    #[Computed]
    public function previewHtml(): string
    {
        $variant = $this->selectedVariant;
        if (!$variant) {
            return '';
        }

        $data = $this->buildPayload($variant);
        $printService = app(PrintService::class);

        try {
            return $printService->renderPreview($data, $this->labelType);
        } catch (\Throwable $e) {
            return '<div class="text-red-500 p-4">Preview Error: ' . $e->getMessage() . '</div>';
        }
    }

    // ─── Helper: Build Data Payload ─────────────────────────────────────────────

    private function buildPayload(ItemVariant $variant): array
    {
        $barcodeValue = $variant->barcodes->firstWhere('is_primary', true)?->barcode 
                        ?? $variant->barcodes->first()?->barcode 
                        ?? '000000';

        // Source bin code from relationship (Priority 1: bins relationship)
        $actualBinCode = $variant->bins->first()?->code ?? '-';

        $data = [
            'item_name' => $variant->item->name,
            'erp_code'  => $variant->erp_code ?? '-',
            'barcode'   => $barcodeValue,
            'bin_code'  => $actualBinCode, // Include for ITEM_LABEL footer
        ];

        if ($this->labelType === 'ITEM_LABEL') {
            $data['last_stock_in_date'] = $this->lastStockInDate;
        } else {
            // For BIN_LABEL, prioritize the UI-overridden binCode if available
            $data['bin_code'] = $this->binCode ?: ($actualBinCode !== '-' ? $actualBinCode : 'PENDING');
        }

        return $data;
    }

    // ─── Render ─────────────────────────────────────────────────────────────────

    public function render()
    {
        $searchResults = collect();
        if (strlen($this->searchString) > 1) {
            $searchResults = ItemVariant::with(['item', 'barcodes'])
                ->where(function ($q) {
                    $q->where('erp_code', 'like', '%' . $this->searchString . '%')
                      ->orWhere('sku', 'like', '%' . $this->searchString . '%')
                      ->orWhereHas('item', fn ($q) =>
                            $q->where('name', 'like', '%' . $this->searchString . '%')
                      );
                })
                ->take(8)
                ->get();
        }

        return view('livewire.barcode.print-page', compact('searchResults'))
            ->layout('layouts.app');
    }

    // ─── Actions ────────────────────────────────────────────────────────────────

    public function selectItem(int $id): void
    {
        $variant = ItemVariant::with('bins')->find($id);

        if (! $variant) {
            return;
        }

        $this->selectedVariantId = $variant->id;
        $this->searchString      = '';
        $this->validationErrors  = [];
        $this->flashMessage      = '';
        
        $bin = $variant->bins->first();
        $this->binCode = $bin?->code ?? '';
    }

    public function clearItem(): void
    {
        $this->selectedVariantId  = null;
        $this->validationErrors   = [];
        $this->flashMessage       = '';
    }

    public function print(): void
    {
        $this->validationErrors = [];
        $this->flashMessage     = '';

        $variant = $this->selectedVariant;
        if (!$variant) {
            $this->validationErrors = ['Please select an item first.'];
            return;
        }

        $rules = [
            'labelType'   => 'required|in:ITEM_LABEL,BIN_LABEL',
            'printerType' => 'required|in:TSC,EPSON',
            'copies'      => 'required|integer|min:1|max:100',
        ];

        if ($this->labelType === 'BIN_LABEL') {
            $rules['binCode'] = 'required|min:1';
        }

        if ($this->printerType === 'TSC') {
            $rules['printerIp'] = 'required|ip';
        }

        $this->validate($rules);

        $data = $this->buildPayload($variant);
        $printService = app(PrintService::class);

        try {
            $result = $printService->print($data, $this->labelType, $this->printerType, $this->printerIp, $this->copies);

            if ($this->printerType === 'TSC') {
                $this->flashMessage = "✓ Label sent to TSC Printer at {$this->printerIp}";
                $this->flashType    = 'success';
            } else {
                $this->dispatch('open-print-window', html: $result);
                $this->flashMessage = "✓ Preparation complete. Opening print window...";
                $this->flashType    = 'success';
            }
        } catch (\Throwable $e) {
            Log::error('PrintPage: Print failed', ['error' => $e->getMessage()]);
            $this->flashMessage = '✗ Printing failed: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    public function saveSettingsAsDefault(): void
    {
        try {
            $settings = BarcodePrintSetting::getSettings();
            $settings->update([
                'default_printer_type' => $this->printerType,
                'default_printer_ip'   => $this->printerIp,
                'default_label_type'   => $this->labelType,
                'default_copies'       => $this->copies,
                'updated_by'           => auth()->id(),
            ]);

            $this->flashMessage = '✓ Settings saved as default.';
            $this->flashType    = 'success';
        } catch (\Throwable $e) {
            $this->flashMessage = '✗ Failed to save settings.';
            $this->flashType    = 'error';
        }
    }

    public function incrementCopies(): void
    {
        if ($this->copies < 100) {
            $this->copies++;
        }
    }

    public function decrementCopies(): void
    {
        if ($this->copies > 1) {
            $this->copies--;
        }
    }
}
