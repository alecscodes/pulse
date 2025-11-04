<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class GitUpdateService
{
    /**
     * Check if updates are available from the remote repository.
     *
     * @return array{available: bool, current_commit: string|null, remote_commit: string|null, commits_behind: int, error: string|null}
     */
    public function checkForUpdates(): array
    {
        $result = [
            'available' => false,
            'current_commit' => null,
            'remote_commit' => null,
            'commits_behind' => 0,
            'error' => null,
        ];

        try {
            // Check if git is available
            $gitCheck = Process::run('git --version');
            if (! $gitCheck->successful()) {
                $result['error'] = 'Git is not available';

                return $result;
            }

            // Check if we're in a git repository
            $gitDirCheck = Process::run('git rev-parse --git-dir');
            if (! $gitDirCheck->successful()) {
                $result['error'] = 'Not a git repository';

                return $result;
            }

            // Get current commit hash
            $currentCommit = Process::run('git rev-parse HEAD');
            if ($currentCommit->successful()) {
                $result['current_commit'] = trim($currentCommit->output());
            }

            // Fetch latest changes from remote (without merging)
            $fetch = Process::run('git fetch origin --quiet');
            if (! $fetch->successful()) {
                $result['error'] = 'Failed to fetch from remote: '.$fetch->errorOutput();

                return $result;
            }

            // Get remote branch name (default to main)
            $branch = $this->getCurrentBranch();

            // Check commits behind
            $commitsBehind = Process::run("git rev-list --count HEAD..origin/{$branch}");
            if ($commitsBehind->successful()) {
                $result['commits_behind'] = (int) trim($commitsBehind->output());
            }

            // Get remote commit hash
            $remoteCommit = Process::run("git rev-parse origin/{$branch}");
            if ($remoteCommit->successful()) {
                $result['remote_commit'] = trim($remoteCommit->output());
            }

            $result['available'] = $result['commits_behind'] > 0;
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Perform the update by pulling latest changes.
     *
     * @return array{success: bool, message: string, output: string|null, error: string|null}
     */
    public function performUpdate(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'output' => null,
            'error' => null,
        ];

        try {
            $branch = $this->getCurrentBranch();

            // Save current commit before pull
            $oldCommit = Process::run('git rev-parse HEAD');
            $oldCommitHash = $oldCommit->successful() ? trim($oldCommit->output()) : null;

            // Pull latest changes
            $pull = Process::run("git pull origin {$branch}");
            if ($pull->successful()) {
                $result['success'] = true;
                $result['message'] = 'Update completed successfully';
                $result['output'] = $pull->output();

                // Get list of changed files
                $changedFiles = $this->getChangedFiles($oldCommitHash);

                // Check if composer files were modified
                $composerChanged = $this->filesChanged($changedFiles, [
                    'composer.json',
                    'composer.lock',
                ]);

                // Check if npm files were modified
                $npmChanged = $this->filesChanged($changedFiles, [
                    'package.json',
                    'package-lock.json',
                ]);

                // Check if frontend files were modified
                $frontendChanged = $this->filesChanged($changedFiles, [
                    'resources/js/',
                    'resources/css/',
                    'vite.config.ts',
                    'vite.config.js',
                    'tsconfig.json',
                    'tailwind.config.js',
                    'tailwind.config.ts',
                    'postcss.config.js',
                    'postcss.config.ts',
                    'eslint.config.js',
                    'eslint.config.ts',
                ]);

                // Run composer install if composer files changed
                if ($composerChanged && file_exists(base_path('composer.json'))) {
                    $composer = Process::run('composer install --no-dev --optimize-autoloader --no-interaction --no-scripts');
                    if ($composer->successful()) {
                        $result['output'] .= "\n\n=== Composer Install ===";
                        $result['output'] .= "\n".$composer->output();
                    } else {
                        $result['output'] .= "\n\n=== Composer Install Failed ===";
                        $result['output'] .= "\n".$composer->errorOutput();
                    }
                }

                // Run npm ci if npm files changed
                if ($npmChanged && file_exists(base_path('package.json'))) {
                    $npmInstall = Process::run('npm ci');
                    if ($npmInstall->successful()) {
                        $result['output'] .= "\n\n=== NPM Install ===";
                        $result['output'] .= "\n".$npmInstall->output();
                    } else {
                        $result['output'] .= "\n\n=== NPM Install Failed ===";
                        $result['output'] .= "\n".$npmInstall->errorOutput();
                    }
                }

                // Run npm build if frontend files changed or npm files changed
                if (($frontendChanged || $npmChanged) && file_exists(base_path('package.json'))) {
                    $npmBuild = Process::run('npm run build');
                    if ($npmBuild->successful()) {
                        $result['output'] .= "\n\n=== NPM Build ===";
                        $result['output'] .= "\n".$npmBuild->output();
                    } else {
                        $result['output'] .= "\n\n=== NPM Build Failed ===";
                        $result['output'] .= "\n".$npmBuild->errorOutput();
                    }
                }

                // Clear Laravel caches
                $this->clearCaches();
            } else {
                $result['error'] = $pull->errorOutput();
                $result['message'] = 'Update failed';
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['message'] = 'Update failed: '.$e->getMessage();
        }

        return $result;
    }

    /**
     * Get list of changed files between two commits.
     *
     * @return array<string>
     */
    protected function getChangedFiles(?string $oldCommitHash): array
    {
        if ($oldCommitHash === null) {
            return [];
        }

        try {
            $diff = Process::run("git diff --name-only {$oldCommitHash} HEAD");
            if ($diff->successful()) {
                $output = trim($diff->output());

                return $output ? explode("\n", $output) : [];
            }
        } catch (\Exception $e) {
            // Silently fail and return empty array
        }

        return [];
    }

    /**
     * Check if any of the specified files/patterns were changed.
     *
     * @param  array<string>  $changedFiles
     * @param  array<string>  $patterns
     * @return bool
     */
    protected function filesChanged(array $changedFiles, array $patterns): bool
    {
        foreach ($changedFiles as $file) {
            foreach ($patterns as $pattern) {
                // Check exact match
                if ($file === $pattern) {
                    return true;
                }

                // Check if file path starts with pattern (for directories)
                if (str_starts_with($file, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get the current git branch.
     */
    protected function getCurrentBranch(): string
    {
        $branch = Process::run('git rev-parse --abbrev-ref HEAD');
        if ($branch->successful()) {
            return trim($branch->output());
        }

        // Default to main if branch detection fails
        return 'main';
    }

    /**
     * Clear Laravel caches after update.
     */
    protected function clearCaches(): void
    {
        try {
            Process::run('php artisan config:clear');
            Process::run('php artisan route:clear');
            Process::run('php artisan view:clear');
            Process::run('php artisan cache:clear');
        } catch (\Exception $e) {
            // Silently fail cache clearing
        }
    }
}
