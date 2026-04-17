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
        ];
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
    }

    public function save()
    {
        $this->validate();

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
            foreach ($this->barcodes as $bc) {
                ItemBarcode::create([
                    'item_variant_id' => $this->variant->id,
                    'barcode' => $bc['code'],
                    'type' => 'INTERNAL',
                    'is_primary' => $bc['is_primary']
                ]);
            }

            // Handle Photo Uploads
            if (!empty($this->photos)) {
                $hasPrimary = $this->variant->images()->where('is_primary', true)->exists();
                
                foreach ($this->photos as $i => $photo) {
                    $path = $photo->store('item-images', 'public');
                    ItemImage::create([
                        'item_variant_id' => $this->variant->id,
                        'path' => $path,
                        'is_primary' => (!$hasPrimary && $i === 0) ? true : false,
                    ]);
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
}
