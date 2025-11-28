<?php

namespace App\Console\Commands;

use App\Services\BrowserInstallationService;
use Illuminate\Console\Command;

class InstallBrowserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'browser:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Playwright with Chromium if not already installed';

    /**
     * Execute the console command.
     */
    public function handle(BrowserInstallationService $service): int
    {
        $this->info('Checking Playwright installation...');

        if ($service->browsersInstalled()) {
            $this->info('Playwright is already installed and Firefox is available.');

            return Command::SUCCESS;
        }

        $this->info('Installing Playwright with Firefox (this may take a few minutes)...');

        if ($service->installBrowsers()) {
            $this->info('Playwright installed successfully.');

            return Command::SUCCESS;
        }

        $this->error('Failed to install Playwright.');

        return Command::FAILURE;
    }
}
