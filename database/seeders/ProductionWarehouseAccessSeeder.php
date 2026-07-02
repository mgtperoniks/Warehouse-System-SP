<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\UserWarehouseAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProductionWarehouseAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Provision / Update adminpb@peroniks.com
        $user = User::where('email', 'adminpb@peroniks.com')->first();
        if (!$user) {
            User::create([
                'name' => 'SPV GUDANG PEMBANTU',
                'email' => 'adminpb@peroniks.com',
                'role' => 'admin',
                'password' => Hash::make('password'),
            ]);
        } else {
            $user->update([
                'name' => 'SPV GUDANG PEMBANTU',
                'role' => 'admin',
            ]);
        }

        // 2. Define the Target Mappings
        $targetMappings = [
            'adminsp@peroniks.com' => ['SPAREPART'],
            'adminbahanbaku@peroniks.com' => ['RAW_MATERIAL'],
            'adminpb@peroniks.com' => ['CONSUMABLE'],
            'managerppic@peroniks.com' => ['SPAREPART', 'RAW_MATERIAL', 'CONSUMABLE', 'FINISHED_GOODS'],
        ];

        // 3. Apply Mappings
        foreach ($targetMappings as $email => $warehouseCodes) {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $warning = "WARNING: User {$email} not found in database. Skipping warehouse assignment.";
                Log::warning($warning);
                if ($this->command) {
                    $this->command->warn($warning);
                } else {
                    echo $warning . "\n";
                }
                continue;
            }

            // Remove all existing mappings for this user
            UserWarehouseAccess::where('user_id', $user->id)->delete();

            foreach ($warehouseCodes as $code) {
                $warehouse = Warehouse::where('code', $code)->first();

                if (!$warehouse) {
                    $warning = "WARNING: Warehouse {$code} not found in database. Skipping assignment for {$email}.";
                    Log::warning($warning);
                    if ($this->command) {
                        $this->command->warn($warning);
                    } else {
                        echo $warning . "\n";
                    }
                    continue;
                }

                UserWarehouseAccess::create([
                    'user_id' => $user->id,
                    'warehouse_id' => $warehouse->id,
                    'can_stock_in' => true,
                    'can_stock_out' => true,
                    'can_opname' => true,
                    'can_adjust' => true,
                    'can_print' => true,
                    'can_view_reports' => true,
                ]);
            }
        }

        // 4. Verification Output
        if ($this->command) {
            $this->command->info("\n=== Warehouse Mappings Verification ===");
        } else {
            echo "\n=== Warehouse Mappings Verification ===\n";
        }

        foreach ($targetMappings as $email => $expectedCodes) {
            $user = User::where('email', $email)->first();
            
            if ($this->command) {
                $this->command->line($email);
            } else {
                echo $email . "\n";
            }

            if (!$user) {
                $msg = "  WARNING: User not found in database!";
                Log::warning($msg);
                if ($this->command) {
                    $this->command->warn($msg);
                } else {
                    echo $msg . "\n";
                }
                continue;
            }

            $currentAccesses = UserWarehouseAccess::where('user_id', $user->id)
                ->join('warehouses', 'warehouses.id', '=', 'user_warehouse_access.warehouse_id')
                ->pluck('warehouses.code')
                ->toArray();

            // Print mapped warehouses
            foreach ($currentAccesses as $code) {
                if ($this->command) {
                    $this->command->info("  ✓ {$code}");
                } else {
                    echo "  ✓ {$code}\n";
                }
            }

            // Check counts and contents
            $hasMismatch = false;
            if (count($currentAccesses) !== count($expectedCodes)) {
                $hasMismatch = true;
            } else {
                $diff1 = array_diff($currentAccesses, $expectedCodes);
                $diff2 = array_diff($expectedCodes, $currentAccesses);
                if (!empty($diff1) || !empty($diff2)) {
                    $hasMismatch = true;
                }
            }

            if ($hasMismatch) {
                $msg = "  WARNING: Mapping mismatch! Expected [" . implode(', ', $expectedCodes) . "], got [" . implode(', ', $currentAccesses) . "]";
                Log::warning($msg);
                if ($this->command) {
                    $this->command->error($msg);
                } else {
                    echo $msg . "\n";
                }
            }
        }
        
        if ($this->command) {
            $this->command->info("=======================================\n");
        } else {
            echo "=======================================\n\n";
        }
    }
}
