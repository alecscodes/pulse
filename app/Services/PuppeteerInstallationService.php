<?php

namespace App\Services;

class PuppeteerInstallationService
{
    /**
     * Check if Puppeteer and Chromium are available.
     */
    public function browsersInstalled(): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        if (! $this->commandExists('chromium-browser') && ! $this->commandExists('chromium')) {
            return false;
        }

        try {
            $script = 'const puppeteer = require("puppeteer-core"); const chromiumPath = process.env.CHROMIUM_PATH || "/usr/bin/chromium-browser" || "/usr/bin/chromium"; puppeteer.launch({executablePath: chromiumPath, headless: true, args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"]}).then(b => { b.close(); process.exit(0); }).catch(() => process.exit(1));';
            $basePath = base_path();
            $command = sprintf('cd %s && node -e %s 2>&1', escapeshellarg($basePath), escapeshellarg($script));
            $output = shell_exec($command);

            // Check if output contains error about missing browser or module
            if (str_contains($output ?? '', 'Cannot find module') || str_contains($output ?? '', 'Executable doesn\'t exist') || str_contains($output ?? '', 'Please run the following command')) {
                return false;
            }

            // If no error message, assume it worked (exit code checking is unreliable with shell_exec)
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Install Puppeteer-core (doesn't download Chromium, uses system one).
     */
    public function installBrowsers(): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        try {
            $basePath = base_path();
            $command = sprintf('cd %s && npm install puppeteer-core --save 2>&1', escapeshellarg($basePath));

            $output = shell_exec($command);

            // Check if installation was successful
            if (str_contains($output ?? '', 'ERROR') && ! str_contains($output ?? '', 'added') && ! str_contains($output ?? '', 'up to date')) {
                return false;
            }

            // Verify installation by checking if puppeteer-core is now available
            return $this->browsersInstalled();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure Puppeteer-core is installed.
     */
    public function ensureInstalled(): bool
    {
        if ($this->browsersInstalled()) {
            return true;
        }

        return $this->installBrowsers();
    }

    /**
     * Get Chromium executable path.
     */
    public function getChromiumPath(): string
    {
        if ($this->commandExists('chromium-browser')) {
            return trim(shell_exec('which chromium-browser') ?: '');
        }

        if ($this->commandExists('chromium')) {
            return trim(shell_exec('which chromium') ?: '');
        }

        // Default paths for Alpine Linux
        if (file_exists('/usr/bin/chromium-browser')) {
            return '/usr/bin/chromium-browser';
        }

        if (file_exists('/usr/bin/chromium')) {
            return '/usr/bin/chromium';
        }

        return '/usr/bin/chromium';
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
