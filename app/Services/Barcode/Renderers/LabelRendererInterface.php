<?php

namespace App\Services\Barcode\Renderers;

interface LabelRendererInterface
{
    /**
     * Render the label based on the provided data and template type.
     *
     * @param array $data The data to be injected into the template.
     * @param string $labelVariant The variant of label (e.g., ITEM_LABEL, BIN_LABEL_80X50, BIN_LABEL_A5, BIN_LABEL_A4).
     * @return string The rendered output (TSPL commands or HTML).
     * @throws \InvalidArgumentException If required data is missing.
     */
    public function render(array $data, string $labelVariant): string;
}
