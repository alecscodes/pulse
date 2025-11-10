<?php

namespace App\Services;

class PlaywrightInstallationService
{
    /**
     * Check if Playwright browsers are installed.
     */
    public function browsersInstalled(): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        try {
            $script = 'require("playwright").chromium.launch({headless: true}).then(b => { b.close(); process.exit(0); }).catch(() => process.exit(1));';
            $command = sprintf('node -e %s 2>&1', escapeshellarg($script));
            $output = shell_exec($command);

            // Check if output contains error about missing browser
            if (str_contains($output ?? '', 'Executable doesn\'t exist') || str_contains($output ?? '', 'Please run the following command')) {
                return false;
            }

            // If no error message, assume it worked (exit code checking is unreliable with shell_exec)
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Install Playwright browsers.
     */
    public function installBrowsers(): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        try {
            $command = 'npx playwright install --with-deps chromium 2>&1';
            $output = shell_exec($command);

            // Check if installation was successful (look for success indicators or absence of critical errors)
            if (str_contains($output ?? '', 'ERROR') && ! str_contains($output ?? '', 'downloaded')) {
                return false;
            }

            // Verify installation by checking if browsers are now available
            return $this->browsersInstalled();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure Playwright browsers are installed.
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

