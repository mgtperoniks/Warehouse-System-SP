<?php

namespace App\Livewire\Items;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemBarcode;
use App\Models\ItemImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ItemForm extends Component
{
    use WithFileUploads;

    public $mode = 'create';
    public ?ItemVariant $variant = null;

    // Basic Info
    public $name = '';
    public $erp_code = '';
    public $sku = '';
    public $brand = '';
    public $unit = 'PCS';
    public $description = '';

    // Barcodes
    public $barcodes = [];
    public $newBarcode = '';
    public $primaryBarcodeIndex = 0;

    // Images
    public $photos = [];
    public $existingPhotos = [];
    public $primaryImageId = null;
    public $primaryPhotoIndex = 0; // Default to first new photo if no existing primary

    // Inventory Initialization (Temporary for Create)
    public $initial_stock = 0;
    public $bin_code = '';

    // Intermediate Cropping State
    public $croppedExistingPhoto = null;
    public $editingExistingId = null;

    // ERP Family & Barcode Auto-Suggestion States
    public $erpFamily = '';
    public $lastBarcode = '';
    public $suggestedBarcode = '';

    protected function rules()
    {
        $variantId = $this->variant ? $this->variant->id : 'NULL';
        return [
            'name' => 'required|string|max:255',
            'erp_code' => "nullable|string|max:255|unique:item_variants,erp_code,{$variantId}",
            'sku' => "nullable|string|max:255|unique:item_variants,sku,{$variantId}",
            'brand' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'photos.*' => 'image|max:25600', // 25MB Max
            'bin_code' => 'nullable|string|max:50',
            'initial_stock' => 'nullable|integer|min:0',
        ];
    }

    public function updated($property)
    {
        if ($property === 'erp_code') {
            $this->suggestBarcodeForErp($this->erp_code);
        }
    }

    public function suggestBarcodeForErp($value)
    {
        $value = trim($value);
        if (empty($value)) {
            $this->erpFamily = '';
            $this->lastBarcode = '';
            $this->suggestedBarcode = '';
            return;
        }

        $parts = explode('.', $value);
        if (count($parts) >= 2) {
            $part1 = preg_replace('/[^0-9]/', '', $parts[0]);
            $part2 = preg_replace('/[^0-9]/', '', $parts[1]);
            if ($part1 !== '' && $part2 !== '') {
                // Enforce first segment = 1 digit, second segment = 2 digits using str_pad
                $part1 = str_pad($part1, 1, '0', STR_PAD_LEFT);
                $part2 = str_pad($part2, 2, '0', STR_PAD_LEFT);

                $this->erpFamily = $parts[0] . '.' . $parts[1] . ' → ' . $part1 . $part2;
                $familyPrefix = '1' . $part1 . $part2;

                // Find latest barcode in same family
                $latest = \App\Models\ItemBarcode::where('barcode', 'like', $familyPrefix . '%')
                    ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
                    ->first();

                if ($latest) {
                    $this->lastBarcode = $latest->barcode;
                    $lastNum = (int)$latest->barcode;
                    $nextNum = $lastNum + 1;
                    $this->suggestedBarcode = number_format($nextNum, 0, '', '');
                } else {
                    $this->lastBarcode = 'NONE';
                    $this->suggestedBarcode = $familyPrefix . '000001';
                }

                // Auto-populate newBarcode if it is currently empty, or if it is a generated family barcode from a different family prefix
                $isGeneratedBarcode = false;
                if (!empty($this->newBarcode)) {
                    if (str_starts_with($this->newBarcode, '1') && strlen($this->newBarcode) === 10 && ctype_digit($this->newBarcode)) {
                        $isGeneratedBarcode = true;
                    }
                }

                if (empty($this->newBarcode) || $isGeneratedBarcode) {
                    $this->newBarcode = $this->suggestedBarcode;
                }

                // Auto-register barcode into barcodes[] if empty or if it contains a single generated barcode from a different family prefix
                $shouldAutoRegister = false;
                if (empty($this->barcodes)) {
                    $shouldAutoRegister = true;
                } elseif (count($this->barcodes) === 1) {
                    $firstBc = $this->barcodes[0]['code'];
                    if (str_starts_with($firstBc, '1') && strlen($firstBc) === 10 && ctype_digit($firstBc) && !str_starts_with($firstBc, $familyPrefix)) {
                        $shouldAutoRegister = true;
                    }
                }

                if ($shouldAutoRegister) {
                    $this->barcodes = [
                        [
                            'code' => $this->suggestedBarcode,
                            'is_primary' => true
                        ]
                    ];
                    $this->primaryBarcodeIndex = 0;
                }
                return;
            }
        }

        $this->erpFamily = '';
        $this->lastBarcode = '';
        $this->suggestedBarcode = '';
    }

    public function mount($mode = 'create', $variant = null)
    {
        $this->mode = $mode;
        
        if ($mode === 'edit' && $variant) {
            $this->variant = $variant;
            $this->name = $variant->item->name;
            $this->erp_code = $variant->erp_code;
            $this->sku = $variant->sku;
            $this->brand = $variant->brand;
            $this->unit = $variant->unit;
            $this->description = $variant->description;

            // Load Barcodes
            foreach ($variant->barcodes as $i => $bc) {
                $this->barcodes[] = [
                    'code' => $bc->barcode,
                    'is_primary' => $bc->is_primary,
                ];
                if ($bc->is_primary) {
                    $this->primaryBarcodeIndex = $i;
                }
            }

            // Load Images
            $this->existingPhotos = $variant->images->toArray();
            $primaryImg = $variant->images->where('is_primary', true)->first();
            if ($primaryImg) {
                $this->primaryImageId = $primaryImg->id;
            }

            // Load primary bin code
            $this->bin_code = $variant->bins()->first()?->code ?? '';

            // Generate barcode suggestion context
            $this->suggestBarcodeForErp($this->erp_code);
        }
    }

    public function addBarcode()
    {
        $this->validate(['newBarcode' => 'required|string']);
        
        // Prevent duplicates in current array
        foreach ($this->barcodes as $bc) {
            if ($bc['code'] === $this->newBarcode) {
                $this->addError('newBarcode', 'Barcode already added.');
                return;
            }
        }

        // Global duplicate protection
        $variantId = $this->variant ? $this->variant->id : null;
        $exists = \App\Models\ItemBarcode::where('barcode', $this->newBarcode)
            ->when($variantId, function($q) use ($variantId) {
                $q->where('item_variant_id', '!=', $variantId);
            })
            ->exists();
        
        if ($exists) {
            $this->addError('newBarcode', 'This barcode is already registered to another item variant in the system.');
            return;
        }

        $this->barcodes[] = [
            'code' => $this->newBarcode,
            'is_primary' => count($this->barcodes) === 0 // Make first one primary
        ];
        
        $this->newBarcode = '';
    }

    public function removeBarcode($index)
    {
        unset($this->barcodes[$index]);
        $this->barcodes = array_values($this->barcodes); // Re-index
        
        if ($this->primaryBarcodeIndex === $index) {
            $this->primaryBarcodeIndex = 0;
            if (count($this->barcodes) > 0) {
                $this->barcodes[0]['is_primary'] = true;
            }
        } elseif ($this->primaryBarcodeIndex > $index) {
            $this->primaryBarcodeIndex--;
        }
    }

    public function setPrimaryBarcode($index)
    {
        foreach ($this->barcodes as $i => $bc) {
            $this->barcodes[$i]['is_primary'] = ($i === $index);
        }
        $this->primaryBarcodeIndex = $index;
    }

    public function removeExistingPhoto($imageId)
    {
        $image = ItemImage::find($imageId);
        if ($image) {
            Storage::disk('public')->delete($image->path);
            $image->delete();
            $this->existingPhotos = $this->variant->images()->get()->toArray();
        }
    }

    public function removeUploadedPhoto($index)
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        
        if ($this->primaryPhotoIndex === $index) {
            $this->primaryPhotoIndex = 0;
        } elseif ($this->primaryPhotoIndex > $index) {
            $this->primaryPhotoIndex--;
        }
    }

    public function setPrimaryExisting($imageId)
    {
        $this->primaryImageId = $imageId;
        $this->primaryPhotoIndex = null;
    }

    public function setPrimaryNew($index)
    {
        $this->primaryPhotoIndex = $index;
        $this->primaryImageId = null;
    }

    public function save()
    {
        $this->validate();

        // Hard duplicate protection validation prior to database commit
        foreach ($this->barcodes as $bc) {
            $variantId = $this->variant ? $this->variant->id : null;
            $exists = \App\Models\ItemBarcode::where('barcode', $bc['code'])
                ->when($variantId, function($q) use ($variantId) {
                    $q->where('item_variant_id', '!=', $variantId);
                })
                ->exists();
            if ($exists) {
                $this->addError('newBarcode', "The barcode '{$bc['code']}' is already registered to another item variant in the system.");
                return;
            }
        }

        DB::transaction(function () {
            // Manage Item (Name grouping)
            if ($this->mode === 'create') {
                $item = Item::firstOrCreate(['name' => $this->name]);
                
                $this->variant = ItemVariant::create([
                    'item_id' => $item->id,
                    'erp_code' => $this->erp_code,
                    'sku' => $this->sku,
                    'brand' => $this->brand,
                    'unit' => $this->unit,
                    'description' => $this->description,
                ]);
            } else {
                $this->variant->item->update(['name' => $this->name]);
                $this->variant->update([
                    'erp_code' => $this->erp_code,
                    'sku' => $this->sku,
                    'brand' => $this->brand,
                    'unit' => $this->unit,
                    'description' => $this->description,
                ]);
            }

            // Sync Barcodes
            $this->variant->barcodes()->delete(); // Simple sync: delete all and recreate
            
            // Extract active family prefix from ERP code
            $familyPrefix = '';
            if (!empty($this->erp_code)) {
                $parts = explode('.', $this->erp_code);
                if (count($parts) >= 2) {
                    $part1 = preg_replace('/[^0-9]/', '', $parts[0]);
                    $part2 = preg_replace('/[^0-9]/', '', $parts[1]);
                    if ($part1 !== '' && $part2 !== '') {
                        $part1 = str_pad($part1, 1, '0', STR_PAD_LEFT);
                        $part2 = str_pad($part2, 2, '0', STR_PAD_LEFT);
                        $familyPrefix = '1' . $part1 . $part2;
                    }
                }
            }

            foreach ($this->barcodes as $bc) {
                $barcodeVal = $bc['code'];

                // If it belongs to the active family prefix and matches standard sequence length, regenerate dynamically at insert-time for concurrency safety!
                if ($this->mode === 'create' && $familyPrefix !== '' && str_starts_with($barcodeVal, $familyPrefix) && strlen($barcodeVal) === 10) {
                    // Re-query absolute latest barcode in same family from DB inside locked transaction
                    $latest = ItemBarcode::where('barcode', 'like', $familyPrefix . '%')
                        ->orderByRaw('CAST(barcode AS UNSIGNED) DESC')
                        ->lockForUpdate()
                        ->first();

                    if ($latest) {
                        $lastNum = (int)$latest->barcode;
                        $nextNum = $lastNum + 1;
                        $barcodeVal = number_format($nextNum, 0, '', '');
                    } else {
                        $barcodeVal = $familyPrefix . '000001';
                    }
                }

                // Hard duplicate protection validation prior to insert
                $variantId = $this->variant ? $this->variant->id : null;
                $exists = ItemBarcode::where('barcode', $barcodeVal)
                    ->when($variantId, function($q) use ($variantId) {
                        $q->where('item_variant_id', '!=', $variantId);
                    })
                    ->exists();

                if ($exists) {
                    throw new \Exception("Concurrency collision: Barcode '{$barcodeVal}' is already registered to another item variant in the system. Please reload.");
                }

                ItemBarcode::create([
                    'item_variant_id' => $this->variant->id,
                    'barcode' => $barcodeVal,
                    'type' => 'INTERNAL',
                    'is_primary' => $bc['is_primary']
                ]);
            }

            // Handle Photo Uploads & Primary Selection
            $imageService = app(\App\Services\Media\ImageService::class);
            
            // 1. Reset all primary flags if we are changing primary
            if ($this->primaryImageId || $this->primaryPhotoIndex !== null) {
                $this->variant->images()->update(['is_primary' => false]);
            }

            // 2. Handle Existing Primary
            if ($this->primaryImageId) {
                ItemImage::where('id', $this->primaryImageId)->update(['is_primary' => true]);
            }

            // 3. Handle Photo Uploads
            if (!empty($this->photos)) {
                foreach ($this->photos as $i => $photo) {
                    $path = $photo->store('item-images', 'public');
                    
                    // Compress, Resize and Force Square (Center Crop)
                    $fullPath = storage_path('app/public/' . $path);
                    $imageService->compressAndResize($fullPath, 1200, 75, true);

                    ItemImage::create([
                        'item_variant_id' => $this->variant->id,
                        'path' => $path,
                        'is_primary' => ($this->primaryPhotoIndex === $i),
                    ]);
                }
            }

            // Fallback: If no primary exists at all, set the first available one
            if (!$this->variant->images()->where('is_primary', true)->exists()) {
                $this->variant->images()->first()?->update(['is_primary' => true]);
            }
            // Handle Inventory Initialization / Bin Management
            if ($this->bin_code) {
                // Find or create default location
                $location = \App\Models\Location::firstOrCreate(
                    ['code' => 'MAIN'],
                    ['description' => 'Main Warehouse']
                );

                if ($this->mode === 'create') {
                    // Create primary bin
                    $bin = \App\Models\Bin::create([
                        'location_id' => $location->id,
                        'item_variant_id' => $this->variant->id,
                        'code' => strtoupper($this->bin_code),
                        'current_qty' => 0, // Will be updated by service if initial_stock > 0
                        'warehouse_id' => session('active_warehouse_id') ?? 1,
                    ]);

                    if ($this->initial_stock > 0) {
                        $inventoryService = app(\App\Services\Inventory\InventoryService::class);
                        $inventoryService->moveStock(
                            $bin, 
                            (int)$this->initial_stock, 
                            'ADJUSTMENT', 
                            'Initial Stock Registration', 
                            (string)auth()->id()
                        );
                    }
                } else {
                    // Update existing bin or create if missing
                    $bin = $this->variant->bins()->first();
                    if ($bin) {
                        $bin->update(['code' => strtoupper($this->bin_code)]);
                    } else {
                        \App\Models\Bin::create([
                            'location_id' => $location->id,
                            'item_variant_id' => $this->variant->id,
                            'code' => strtoupper($this->bin_code),
                            'current_qty' => 0,
                            'warehouse_id' => session('active_warehouse_id') ?? 1,
                        ]);
                    }
                }
            }
        });

        session()->flash('message', 'Item successfully saved.');
        return redirect()->route('items.show', $this->variant->id);
    }

    public function render()
    {
        return view('livewire.items.item-form');
    }

    /**
     * Replaces an existing saved photo with its cropped version.
     */
    public function applyExistingCrop($imageId)
    {
        $image = ItemImage::find($imageId);
        if (!$image || !$this->croppedExistingPhoto) {
            return;
        }

        // Overwrite the existing file at its physical path
        $path = $image->path;
        $fullPath = storage_path('app/public/' . $path);

        if (Storage::disk('public')->exists($path)) {
            // Save the new blob over the old file
            Storage::disk('public')->put($path, $this->croppedExistingPhoto->get());
            
            // Re-optimize and Ensure Square (Center Crop)
            $imageService = app(\App\Services\Media\ImageService::class);
            $imageService->compressAndResize($fullPath, 1200, 75, true);
            
            // Touch the model to update the updated_at timestamp used for Cache Busting
            $image->touch();
            
            // Refresh existing photos list from database to get the new timestamp
            $this->existingPhotos = $this->variant->images()->get()->toArray();
            
            $this->reset(['croppedExistingPhoto', 'editingExistingId']);
            $this->dispatch('notyf', type: 'success', message: 'Existing photo updated successfully.');
        }
    }
}
