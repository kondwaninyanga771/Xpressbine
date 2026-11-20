<?php

namespace App\Console\Commands;

use App\Data\JsonFileDriver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ResetJsonDatabase extends Command
{
    protected $signature = 'xpressbine:json-reset {--no-seed : Migrate the JSON store without the demo seeder}';

    protected $description = 'Replace storage/app/data/db.json with a fresh migrated dataset';

    public function handle(JsonFileDriver $driver): int
    {
        if (config('xpressbine.data_driver') !== 'json') {
            $this->error('Set XPRESSBINE_DATA_DRIVER=json before resetting the JSON store.');

            return self::FAILURE;
        }

        $driver->begin(import: false);

        try {
            Artisan::call('migrate:fresh', [
                '--force' => true,
                '--seed' => ! $this->option('no-seed'),
            ]);
            $this->output->write(Artisan::output());
            $driver->commit();
        } catch (Throwable $exception) {
            $driver->abort();

            throw $exception;
        }

        $this->info('Wrote '.$driver->path());

        return self::SUCCESS;
    }
}
