<?php

namespace App\Console\Commands;

use App\Services\PlaywrightInstallationService;
use Illuminate\Console\Command;

class InstallPlaywrightCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'playwright:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Playwright browsers if not already installed';

    /**
     * Execute the console command.
     */
    public function handle(PlaywrightInstallationService $service): int
    {
        $this->info('Checking Playwright browser installation...');

        if ($service->browsersInstalled()) {
            $this->info('Playwright browsers are already installed.');

            return Command::SUCCESS;
        }

        $this->info('Installing Playwright browsers (this may take a few minutes)...');

        if ($service->installBrowsers()) {
            $this->info('Playwright browsers installed successfully.');

            return Command::SUCCESS;
        }

        $this->error('Failed to install Playwright browsers.');

        return Command::FAILURE;
    }
}
