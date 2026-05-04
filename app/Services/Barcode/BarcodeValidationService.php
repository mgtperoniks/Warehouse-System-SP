<?php

namespace App\Services\Barcode;

use App\Models\ItemBarcode;
use App\Models\ItemVariant;
use App\Models\LabelTemplate;
use App\Models\Printer;

class BarcodeValidationService
{
    /**
     * Run all pre-print validations. Returns an array of error messages.
     * Empty array = all validations pass.
     *
     * @param  ItemVariant    $variant
     * @param  Printer        $printer
     * @param  LabelTemplate  $template
     * @param  string         $barcodeValue
     * @param  int            $copies
     * @return string[]
     */
    public function validate(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): array {
        $errors = [];

        foreach ($this->getGuards() as $guard) {
            $error = $this->$guard($variant, $printer, $template, $barcodeValue, $copies);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Convenience method — returns true if ALL validations pass.
     */
    public function passes(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): bool {
        return empty($this->validate($variant, $printer, $template, $barcodeValue, $copies));
    }

    // ─── Guard Definitions ───────────────────────────────────────────────────────

    /**
     * List of guard methods to run, in priority order.
     */
    private function getGuards(): array
    {
        return [
            'guardCopies',
            'guardItemActive',
            'guardPrinterActive',
            'guardTemplateActive',
            'guardBarcodeIntegrity',
        ];
    }

    private function guardCopies(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): ?string {
        if ($copies <= 0) {
            return 'Jumlah cetak harus lebih dari 0.';
        }

        if ($copies > 100) {
            return 'Jumlah cetak tidak boleh lebih dari 100 per sesi.';
        }

        return null;
    }

    private function guardItemActive(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): ?string {
        // ItemVariant currently has no is_active flag — reserved for future soft-delete.
        // We check that the variant exists and has a valid item relation.
        if (! $variant->exists) {
            return 'Item tidak ditemukan di sistem.';
        }

        if (! $variant->item) {
            return 'Item tidak memiliki kategori induk yang valid.';
        }

        return null;
    }

    private function guardPrinterActive(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): ?string {
        if (! $printer->is_active) {
            return "Printer [{$printer->printer_name}] tidak aktif.";
        }

        if ($printer->status === 'offline') {
            return "Printer [{$printer->printer_name}] sedang offline. Periksa koneksi jaringan.";
        }

        return null;
    }

    private function guardTemplateActive(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): ?string {
        if (! $template->is_active) {
            return "Template label [{$template->template_name}] tidak aktif.";
        }

        return null;
    }

    private function guardBarcodeIntegrity(
        ItemVariant $variant,
        Printer $printer,
        LabelTemplate $template,
        string $barcodeValue,
        int $copies
    ): ?string {
        if (empty(trim($barcodeValue))) {
            return 'Nilai barcode tidak boleh kosong.';
        }

        if (strlen($barcodeValue) < 3) {
            return 'Nilai barcode terlalu pendek (minimal 3 karakter).';
        }

        // Validate that the barcode_value actually belongs to this variant
        $owned = ItemBarcode::where('barcode', $barcodeValue)
            ->where('item_variant_id', $variant->id)
            ->exists();

        if (! $owned) {
            return 'Nilai barcode tidak terdaftar pada item ini.';
        }

        return null;
    }
}
