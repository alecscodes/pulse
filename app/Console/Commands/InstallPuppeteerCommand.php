<?php

namespace App\Console\Commands;

use App\Services\PuppeteerInstallationService;
use Illuminate\Console\Command;

class InstallPuppeteerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'puppeteer:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Puppeteer-core if not already installed (uses system Chromium)';

    /**
     * Execute the console command.
     */
    public function handle(PuppeteerInstallationService $service): int
    {
        $this->info('Checking Puppeteer-core installation...');

        if ($service->browsersInstalled()) {
            $this->info('Puppeteer-core is already installed and Chromium is available.');

            return Command::SUCCESS;
        }

        $this->info('Installing Puppeteer-core (this uses system Chromium)...');

        if ($service->installBrowsers()) {
            $this->info('Puppeteer-core installed successfully.');

            return Command::SUCCESS;
        }

        $this->error('Failed to install Puppeteer-core.');

        return Command::FAILURE;
    }
}
