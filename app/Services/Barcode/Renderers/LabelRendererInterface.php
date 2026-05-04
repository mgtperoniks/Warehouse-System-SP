<?php

namespace App\Services\Barcode\Renderers;

interface LabelRendererInterface
{
    /**
     * Render the label based on the provided data and template type.
     *
     * @param array $data The data to be injected into the template.
     * @param string $templateType The type of label (e.g., ITEM_LABEL, BIN_LABEL).
     * @return string The rendered output (TSPL commands or HTML).
     * @throws \InvalidArgumentException If required data is missing.
     */
    public function render(array $data, string $templateType): string;
}
