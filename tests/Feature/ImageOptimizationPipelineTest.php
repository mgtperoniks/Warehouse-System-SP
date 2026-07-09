<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemImage;
use App\Services\Media\ImageService;
use App\Livewire\Items\ItemForm;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class ImageOptimizationPipelineTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local'); // For livewire-tmp uploads

        $this->user = User::create([
            'name' => 'Image Test Owner',
            'email' => 'image_owner_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([$this->warehouse->id]);

        $item = Item::create(['name' => 'Test Item with Image']);
        $this->variant = ItemVariant::create([
            'item_id' => $item->id,
            'erp_code' => '5.01.999',
            'sku' => 'SKU-IMG-999',
            'unit' => 'PCS',
        ]);

        session()->put('active_warehouse_id', $this->warehouse->id);
        session()->put('active_warehouse_code', $this->warehouse->code);
        session()->put('active_warehouse_name', $this->warehouse->name);
    }

    /**
     * Helper to create a dummy image file.
     */
    protected function createDummyImage(int $width = 2000, int $height = 2000): string
    {
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img') . '.jpg';
        imagejpeg($img, $tempFile, 95);
        imagedestroy($img);
        
        return $tempFile;
    }

    /**
     * Test ImageService compressAndResize directly.
     */
    public function test_image_service_resizes_and_compresses_correctly(): void
    {
        $imageService = new ImageService();
        $tempPath = $this->createDummyImage(2400, 2400);

        $originalSize = filesize($tempPath);
        $this->assertGreaterThan(0, $originalSize);

        $success = $imageService->compressAndResize($tempPath, 1600, 70, true);
        $this->assertTrue($success);

        clearstatcache(true, $tempPath);
        $optimizedSize = filesize($tempPath);
        $info = getimagesize($tempPath);

        // Verify dimensions: square cropped & maximum dimension of 1600
        $this->assertEquals(1600, $info[0]);
        $this->assertEquals(1600, $info[1]);
        $this->assertLessThan($originalSize, $optimizedSize);

        @unlink($tempPath);
    }

    /**
     * Test Livewire ItemForm photo upload and optimization flow.
     */
    public function test_item_form_photo_upload_is_optimized_and_temp_deleted(): void
    {
        $this->actingAs($this->user);

        // We will mock the UploadedFile
        $uploadedFile = UploadedFile::fake()->image('test_upload.jpg', 2000, 2000);

        // Run the Livewire form create mode
        Livewire::test(ItemForm::class, ['mode' => 'create'])
            ->set('name', 'New Hardened Item')
            ->set('erp_code', '5.01.777')
            ->set('unit', 'PCS')
            ->set('photos', [$uploadedFile])
            ->call('save')
            ->assertHasNoErrors();

        // Verify that the file was processed, resized to 1600x1600 and exists in storage
        $savedImage = ItemImage::whereHas('itemVariant', function ($q) {
            $q->where('erp_code', '5.01.777');
        })->first();

        $this->assertNotNull($savedImage);
        $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($savedImage->path);
        
        $this->assertFileExists($fullPath);
        $info = getimagesize($fullPath);
        $this->assertEquals(1600, $info[0]);
        $this->assertEquals(1600, $info[1]);

        // Cleanup
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
