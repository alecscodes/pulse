<?php

namespace App\Services;

class BrowserInstallationService
{
    /**
     * Check if Playwright and Chromium are available.
     */
    public function browsersInstalled(): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        try {
            // Test if Playwright can launch Chromium
            $script = 'import { chromium } from "playwright"; chromium.launch({ headless: true, args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"] }).then(b => { b.close(); process.exit(0); }).catch(() => process.exit(1));';
            $basePath = base_path();
            $command = sprintf('cd %s && node --input-type=module -e %s 2>&1', escapeshellarg($basePath), escapeshellarg($script));
            $output = shell_exec($command);

            // Check if output contains error about missing browser or module
            if (str_contains($output ?? '', 'Cannot find module') || str_contains($output ?? '', 'Executable doesn\'t exist') || str_contains($output ?? '', 'Please run the following command') || str_contains($output ?? '', 'Error')) {
                return false;
            }

            // If no error message, assume it worked (exit code checking is unreliable with shell_exec)
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Install Playwright (includes Chromium browser).
     */
    public function installBrowsers(): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        try {
            $basePath = base_path();
            // Install Playwright package
            $command = sprintf('cd %s && npm install playwright --save 2>&1', escapeshellarg($basePath));
            $output = shell_exec($command);

            // Check if installation was successful
            if (str_contains($output ?? '', 'ERROR') && ! str_contains($output ?? '', 'added') && ! str_contains($output ?? '', 'up to date')) {
                return false;
            }

            // Install Chromium browser binary
            $installBrowserCommand = sprintf('cd %s && npx playwright install chromium 2>&1', escapeshellarg($basePath));
            $browserOutput = shell_exec($installBrowserCommand);

            // Check if browser installation was successful
            if (str_contains($browserOutput ?? '', 'ERROR') && ! str_contains($browserOutput ?? '', 'Installing') && ! str_contains($browserOutput ?? '', 'already installed')) {
                return false;
            }

            // Verify installation by checking if Playwright is now available
            return $this->browsersInstalled();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure Playwright is installed.
     */
    public function ensureInstalled(): bool
    {
        if ($this->browsersInstalled()) {
            return true;
        }

        return $this->installBrowsers();
    }

    /**
     * Check if a command exists in the system.
     */
    private function commandExists(string $command): bool
    {
        $whereIsCommand = (PHP_OS === 'WINNT') ? 'where' : 'which';
        $process = proc_open("$whereIsCommand $command", [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if ($process !== false) {
            $stdout = stream_get_contents($pipes[1]);
            proc_close($process);

            return ! empty($stdout);
        }

        return false;
    }
}
