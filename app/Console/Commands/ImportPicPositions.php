<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\User;
use Illuminate\Console\Command;

class ImportPicPositions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pic:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk import PIC Positions based on approved department matrix';

    /**
     * The approved PIC Position Matrix grouped by Department Code.
     *
     * @var array
     */
    protected $matrix = [
        'AL' => [
            'SPV ALUMINIUM',
            'SPV MAINTENANCE PF (AL)',
        ],
        'BB' => [
            'SPV BAHAN BAKU',
            'SPV MAINTENANCE CR (BB)',
        ],
        'BK' => [
            'KABAG MAINTENANCE',
            'SPV MAINTENANCE PF (BK)',
            'SPV MAINTENANCE BBT (BK)',
            'SPV MAINTENANCE CR (BK)',
        ],
        'BR' => [
            'KABAG BOR',
            'SPV BOR',
            'SPV MAINTENANCE BBT (BR)',
        ],
        'BTFL' => [
            'SPV BUBUT CNC FL',
            'SPV BUBUT OTO FL',
            'SPV MILLING',
            'KABAG BUBUT CNC FL',
            'SPV MAINTENANCE BBT (BTFL)',
        ],
        'BTPF' => [
            'KABAG BUBUT CNC PF',
            'SPV CNC FL',
            'SPV MAINTENANCE BBT (BTPF)',
        ],
        'CR' => [
            'KABAG COR FL',
            'SPV COR FL',
            'SPV QC SPEKTRO',
            'SPV MAINTENANCE CR (CR)',
        ],
        'FBESI' => [
            'SPV BUBUT BESI',
            'SPV MAINTENANCE BBT (FBESI)',
        ],
        'GJ' => [
            'SPV GUDANG JADI FL',
            'SPV GUDANG JADI PF',
            'SPV MARKING',
            'SPV MAINTENANCE BBT (GJ)',
            'SPV MAINTENANCE CR (GJ)',
        ],
        'LL' => [
            'SPV GA',
            'SUPIR',
            'SPV MAINTENANCE PF (LL)',
        ],
        'LN' => [
            'KABAG FITTING',
            'SPV COR PF',
            'SPV CETAK',
            'SPV RANGKAI',
            'SPV LAPISAN',
            'SPV MAINTENANCE PF (LN)',
        ],
        'NT' => [
            'SPV NETTO FLANGE',
            'SPV NETTO FITTING',
            'SPV MAINTENANCE CR (NT)',
        ],
        'PR' => [
            'SPV GA PROJECT',
            'TUKANG',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting PIC positions import...');

        $summary = [];

        foreach ($this->matrix as $deptCode => $positions) {
            $department = Department::where('code', $deptCode)->first();

            if (!$department) {
                $this->warn("Skipping Department Code [{$deptCode}]: Department does not exist in database.");
                $summary[] = [
                    'Department' => $deptCode,
                    'Created' => 0,
                    'Updated' => 0,
                    'Skipped' => count($positions),
                ];
                continue;
            }

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            foreach ($positions as $positionName) {
                // Safeguard against modifying System Accounts
                if (in_array($positionName, ['Admin Sparepart', 'Manager PPIC', 'Auditor'])) {
                    $skippedCount++;
                    continue;
                }

                // Check if user record with this name already exists
                $existingUser = User::where('name', $positionName)->first();

                if ($existingUser) {
                    // Update department and enforce ACTIVE status
                    $existingUser->update([
                        'department_id' => $department->id,
                        'is_active' => true,
                    ]);
                    $updatedCount++;
                } else {
                    // Generate a unique email based on position name
                    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $positionName));
                    $email = $slug . '@sparepart.local';
                    
                    $count = 1;
                    while (User::where('email', $email)->exists()) {
                        $email = $slug . $count . '@sparepart.local';
                        $count++;
                    }

                    // Create the new active PIC position record
                    User::create([
                        'name' => $positionName,
                        'email' => $email,
                        'department_id' => $department->id,
                        'password' => bcrypt('password123'),
                        'role' => 'pic',
                        'is_active' => true,
                    ]);
                    $createdCount++;
                }
            }

            $summary[] = [
                'Department' => "{$department->name} ({$deptCode})",
                'Created' => $createdCount,
                'Updated' => $updatedCount,
                'Skipped' => $skippedCount,
            ];
        }

        $this->newLine();
        $this->info('PIC Positions Import Summary:');
        $this->table(['Department', 'Created', 'Updated', 'Skipped'], $summary);

        return Command::SUCCESS;
    }
}
