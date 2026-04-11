<?php

namespace App\Console\Commands;

use App\Imports\ItemImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\File;

class ImportItemsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:items {file : Path to the excel file}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Import Master Data from Excel (.xlsx) file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->argument('file');

        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Importing items from: {$filePath}...");

        try {
            Excel::import(new ItemImport, $filePath);
            $this->success("Import completed successfully!");
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function success($message)
    {
        $this->line("<info>✔</info> {$message}");
    }
}
