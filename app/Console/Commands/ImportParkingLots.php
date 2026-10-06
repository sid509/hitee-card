<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ParkingImportService;
use Illuminate\Console\Command;

class ImportParkingLots extends Command
{
    protected $signature = 'parking:import
                            {file : Path to the parking lots JSON export}
                            {--skip-images : Import data only, don\'t download pictures}
                            {--merchant= : Email of the merchant to assign (defaults to Hitee Solution)}';

    protected $description = 'Import parking lots from the JSON export (fees, attributes, owner info and pictures)';

    public function handle(ParkingImportService $importer): int
    {
        // Image downloads + GD decoding are memory-hungry.
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $file = $this->argument('file');

        if (!is_file($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $lots = json_decode(file_get_contents($file), true);
        if (!is_array($lots) || array_is_list($lots) === false) {
            $this->error('Invalid JSON — expected a top-level array of parking lots.');
            return self::FAILURE;
        }

        $merchant = $this->resolveMerchant($importer);
        if (!$merchant) {
            return self::FAILURE;
        }

        $withImages = !$this->option('skip-images');
        $this->info("Importing " . count($lots) . " parking lots for merchant {$merchant->name}…");

        $result = $importer->import($lots, $merchant, $withImages);

        $this->info("Created: {$result['created']}  Updated: {$result['updated']}  Images: {$result['images']}  Failed: {$result['failed']}");

        foreach ($result['errors'] as $error) {
            $this->warn("  • {$error}");
        }

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveMerchant(ParkingImportService $importer): ?User
    {
        if ($email = $this->option('merchant')) {
            $merchant = User::where('email', $email)->first();
            if (!$merchant) {
                $this->error("No user found with email {$email}.");
            }
            return $merchant;
        }

        return $importer->defaultMerchant();
    }
}
