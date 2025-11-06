<?php

namespace App\Console\Commands;

use App\Services\GitUpdateService;
use Illuminate\Console\Command;

class TestUpdateCommand extends Command
{
    protected $signature = 'update:test';

    protected $description = 'Test the update service and show logs';

    public function handle(GitUpdateService $updateService): int
    {
        $this->info('Checking for updates...');

        $checkResult = $updateService->checkForUpdates();
        $this->table(
            ['Key', 'Value'],
            collect($checkResult)->map(fn ($value, $key) => [$key, is_bool($value) ? ($value ? 'true' : 'false') : $value])
        );

        if (! $checkResult['available']) {
            $this->info('No updates available or error occurred.');

            return self::SUCCESS;
        }

        if ($this->confirm('Do you want to perform the update?', true)) {
            $this->info('Performing update...');

            $updateResult = $updateService->performUpdate();

            $this->newLine();
            $this->info('Update Result:');
            $this->table(
                ['Key', 'Value'],
                collect($updateResult)->except('output')->map(fn ($value, $key) => [
                    $key,
                    is_bool($value) ? ($value ? 'true' : 'false') : ($value ?? 'null'),
                ])
            );

            if ($updateResult['output']) {
                $this->newLine();
                $this->info('Output:');
                $this->line($updateResult['output']);
            }

            if ($updateResult['success']) {
                $this->info('✓ Update completed successfully!');

                return self::SUCCESS;
            } else {
                $this->error('✗ Update failed!');

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
