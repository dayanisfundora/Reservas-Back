<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Currency;
use App\Models\Siclamon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sync Currencies From Siclamon Command
 * 
 * This command synchronizes currency data from the SICLAMON table 
 * in the ihsecurity database to the local currencies table.
 * 
 * Maps fields:
 * - cod_mone -> code
 * - desc_mone -> description
 * - desc_mone (first 10 chars) -> abbr
 * - tasa -> rate
 * - Sets base_currency based on convertible flag
 * - Sets state to true by default
 * 
 * Usage:
 * php artisan currencies:sync-from-siclamon
 */
class SyncCurrenciesFromSiclamon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currencies:sync-from-siclamon
                            {--force : Force sync even if currency already exists}
                            {--update : Update existing currencies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync currencies from SICLAMON table (ihsecurity database) to local currencies table';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            $this->info('Starting currency synchronization from SICLAMON...');

            $siclamon_currencies = Siclamon::all();

            if ($siclamon_currencies->isEmpty()) {
                $this->warn('No currencies found in SICLAMON table.');
                return Command::SUCCESS;
            }

            $created_count = 0;
            $updated_count = 0;
            $skipped_count = 0;

            foreach ($siclamon_currencies as $siclamon) {
                $existing = Currency::where('code', $siclamon->id_mone)->first();

                // Prepare data for currency
                // New mapping: code = id_mone, abbr = cod_mone
                $currency_data = [
                    'code' => $siclamon->id_mone,
                    'description' => $siclamon->desc_mone,
                    'abbr' => $siclamon->cod_mone,
                    'rate' => (float) str_replace(',', '.', $siclamon->tasa),
                    'base_currency' => ($siclamon->cod_mone === 'CUP'), // Only CUP is base currency
                    'convertible' => (bool) $siclamon->convertible,
                    'facturable' => (bool) $siclamon->facturable,
                    'state' => true,
                ];

                if ($existing) {
                    if ($this->option('update')) {
                        $existing->update($currency_data);
                        $updated_count++;
                        $this->line("Updated: {$siclamon->cod_mone} - {$siclamon->desc_mone}");
                    } else {
                        $skipped_count++;
                        $this->line("Skipped (already exists): {$siclamon->cod_mone}");
                    }
                } else {
                    Currency::create($currency_data);
                    $created_count++;
                    $this->info("Created: {$siclamon->cod_mone} - {$siclamon->desc_mone}");
                }
            }

            $this->newLine();
            $this->info('Synchronization completed!');
            $this->table(
                ['Status', 'Count'],
                [
                    ['Created', $created_count],
                    ['Updated', $updated_count],
                    ['Skipped', $skipped_count],
                    ['Total processed', $siclamon_currencies->count()],
                ]
            );

            Log::info('Currency sync completed', [
                'created' => $created_count,
                'updated' => $updated_count,
                'skipped' => $skipped_count,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during synchronization: ' . $e->getMessage());
            Log::error('Currency sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
