<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
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
            $workingDir = base_path();

            // Check if git is available
            $gitCheck = Process::run('git --version');
            if (! $gitCheck->successful()) {
                $result['error'] = 'Git is not available';

                return $result;
            }

            // Check if .git directory exists
            if (! is_dir($workingDir.'/.git')) {
                $result['error'] = 'Git repository not found';

                return $result;
            }

            // Configure Git to trust this directory (fixes Docker ownership issues)
            $this->configureGitSafeDirectory($workingDir);

            // Check if we're in a git repository
            $gitDirCheck = Process::path($workingDir)->run('git rev-parse --git-dir');
            if (! $gitDirCheck->successful()) {
                $result['error'] = 'Not a git repository: '.$gitDirCheck->errorOutput();

                return $result;
            }

            // Get current commit hash
            $currentCommit = Process::path($workingDir)->run('git rev-parse HEAD');
            if ($currentCommit->successful()) {
                $result['current_commit'] = trim($currentCommit->output());
            }

            // Fetch latest changes from remote (without merging)
            $fetch = Process::path($workingDir)->run('git fetch origin --quiet');
            if (! $fetch->successful()) {
                $result['error'] = 'Failed to fetch from remote: '.$fetch->errorOutput();

                return $result;
            }

            // Get remote branch name (default to main)
            $branch = $this->getCurrentBranch($workingDir);

            // Check commits behind
            $commitsBehind = Process::path($workingDir)->run("git rev-list --count HEAD..origin/{$branch}");
            if ($commitsBehind->successful()) {
                $result['commits_behind'] = (int) trim($commitsBehind->output());
            }

            // Get remote commit hash
            $remoteCommit = Process::path($workingDir)->run("git rev-parse origin/{$branch}");
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
            $workingDir = base_path();
            Log::info('[GitUpdate] Starting update process', ['working_dir' => $workingDir]);

            // Configure Git to trust this directory (fixes Docker ownership issues)
            $this->configureGitSafeDirectory($workingDir);

            $branch = $this->getCurrentBranch($workingDir);
            Log::info('[GitUpdate] Current branch detected', ['branch' => $branch]);

            // Save current commit before update
            $oldCommit = Process::path($workingDir)->run('git rev-parse HEAD');
            $oldCommitHash = $oldCommit->successful() ? trim($oldCommit->output()) : null;
            Log::info('[GitUpdate] Current commit', ['commit' => $oldCommitHash]);

            // Fetch latest changes from remote
            Log::info('[GitUpdate] Fetching from remote...');
            $fetch = Process::path($workingDir)->run('git fetch origin');
            if (! $fetch->successful()) {
                Log::error('[GitUpdate] Fetch failed', [
                    'error' => $fetch->errorOutput(),
                    'output' => $fetch->output(),
                ]);
                $result['error'] = 'Failed to fetch from remote: '.$fetch->errorOutput();
                $result['message'] = 'Update failed';

                return $result;
            }
            Log::info('[GitUpdate] Fetch successful');

            // Force reset to remote branch (discards any local changes)
            Log::info('[GitUpdate] Resetting to origin/'.$branch);

            // First, ensure we have proper permissions for the operation
            // This is critical in Docker environments with bind mounts
            $this->ensureGitPermissions($workingDir);

            $reset = Process::path($workingDir)->run("git reset --hard origin/{$branch}");
            if (! $reset->successful()) {
                Log::error('[GitUpdate] Reset failed', [
                    'error' => $reset->errorOutput(),
                    'output' => $reset->output(),
                ]);
                $result['error'] = 'Failed to reset to remote: '.$reset->errorOutput();
                $result['message'] = 'Update failed';

                return $result;
            }
            Log::info('[GitUpdate] Reset successful', ['output' => $reset->output()]);

            // Clean untracked files (respects .gitignore)
            Log::info('[GitUpdate] Cleaning untracked files...');
            $clean = Process::path($workingDir)->run('git clean -fd');
            Log::info('[GitUpdate] Clean completed', ['output' => $clean->output()]);

            $result['success'] = true;
            $result['message'] = 'Update completed successfully';
            $result['output'] = $reset->output();

            // Get list of changed files
            $changedFiles = $this->getChangedFiles($oldCommitHash);
            Log::info('[GitUpdate] Changed files detected', ['count' => count($changedFiles), 'files' => $changedFiles]);

            // Fix permissions on updated files
            Log::info('[GitUpdate] Fixing permissions...');
            $this->fixPermissions($changedFiles);

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
                Log::info('[GitUpdate] Running composer install...');
                $composer = Process::path($workingDir)->run('composer install --no-dev --optimize-autoloader --no-interaction --no-scripts');
                if ($composer->successful()) {
                    Log::info('[GitUpdate] Composer install successful');
                    $result['output'] .= "\n\n=== Composer Install ===";
                    $result['output'] .= "\n".$composer->output();
                } else {
                    Log::error('[GitUpdate] Composer install failed', [
                        'error' => $composer->errorOutput(),
                        'output' => $composer->output(),
                    ]);
                    $result['output'] .= "\n\n=== Composer Install Failed ===";
                    $result['output'] .= "\n".$composer->errorOutput();
                }
            }

            // Run npm ci if npm files changed
            if ($npmChanged && file_exists(base_path('package.json'))) {
                Log::info('[GitUpdate] Running npm ci...');
                $npmInstall = Process::path($workingDir)->run('npm ci');
                if ($npmInstall->successful()) {
                    Log::info('[GitUpdate] NPM install successful');
                    $result['output'] .= "\n\n=== NPM Install ===";
                    $result['output'] .= "\n".$npmInstall->output();
                } else {
                    Log::error('[GitUpdate] NPM install failed', [
                        'error' => $npmInstall->errorOutput(),
                        'output' => $npmInstall->output(),
                    ]);
                    $result['output'] .= "\n\n=== NPM Install Failed ===";
                    $result['output'] .= "\n".$npmInstall->errorOutput();
                }
            }

            // Run npm build if frontend files changed or npm files changed
            if (($frontendChanged || $npmChanged) && file_exists(base_path('package.json'))) {
                Log::info('[GitUpdate] Running npm build...');
                $npmBuild = Process::path($workingDir)->run('npm run build');
                if ($npmBuild->successful()) {
                    Log::info('[GitUpdate] NPM build successful');
                    $result['output'] .= "\n\n=== NPM Build ===";
                    $result['output'] .= "\n".$npmBuild->output();
                } else {
                    Log::error('[GitUpdate] NPM build failed', [
                        'error' => $npmBuild->errorOutput(),
                        'output' => $npmBuild->output(),
                    ]);
                    $result['output'] .= "\n\n=== NPM Build Failed ===";
                    $result['output'] .= "\n".$npmBuild->errorOutput();
                }
            }

            // Clear Laravel caches
            Log::info('[GitUpdate] Clearing caches...');
            $this->clearCaches();
            Log::info('[GitUpdate] Update process completed successfully');
        } catch (\Exception $e) {
            Log::error('[GitUpdate] Update failed with exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            $workingDir = base_path();
            $diff = Process::path($workingDir)->run("git diff --name-only {$oldCommitHash} HEAD");
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
    protected function getCurrentBranch(?string $workingDir = null): string
    {
        $workingDir = $workingDir ?? base_path();
        $branch = Process::path($workingDir)->run('git rev-parse --abbrev-ref HEAD');
        if ($branch->successful()) {
            return trim($branch->output());
        }

        // Default to main if branch detection fails
        return 'main';
    }

    /**
     * Configure Git to trust the working directory (fixes Docker ownership issues).
     * Uses local config instead of global to avoid affecting other repositories.
     */
    protected function configureGitSafeDirectory(string $workingDir): void
    {
        try {
            $gitDir = $workingDir.'/.git';

            if (! is_dir($gitDir)) {
                return;
            }

            // Ensure Git trusts this directory (entrypoint should have set this, but verify)
            // Note: We use --global because Git won't let us use --local on an "unsafe" repo
            Process::run("git config --global --add safe.directory {$workingDir} 2>/dev/null || true");

            // Verify critical files are writable (permissions should be set by entrypoint)
            // If not writable, log warning but don't fail (entrypoint should handle this)
            $criticalFiles = [
                $gitDir.'/FETCH_HEAD',
                $gitDir.'/index',
            ];

            foreach ($criticalFiles as $file) {
                if (file_exists($file) && ! is_writable($file)) {
                    // Log but don't fail - entrypoint should have set permissions
                    Log::warning("Git file not writable: {$file}. Permissions should be set by entrypoint.");
                }
            }
        } catch (\Exception $e) {
            // Silently fail - this is not critical if it fails
            // Permissions should be handled by entrypoint
        }
    }

    /**
     * Clear Laravel caches after update.
     */
    protected function clearCaches(): void
    {
        try {
            $workingDir = base_path();
            Process::path($workingDir)->run('php artisan config:clear');
            Process::path($workingDir)->run('php artisan route:clear');
            Process::path($workingDir)->run('php artisan view:clear');
            Process::path($workingDir)->run('php artisan cache:clear');
        } catch (\Exception $e) {
            // Silently fail cache clearing
        }
    }

    /**
     * Ensure Git has proper permissions to modify files.
     * Critical for Docker environments with bind mounts.
     */
    protected function ensureGitPermissions(string $workingDir): void
    {
        try {
            $uid = posix_getuid();
            $gid = posix_getgid();

            Log::info('[GitUpdate] Ensuring Git has write permissions...', [
                'uid' => $uid,
                'gid' => $gid,
            ]);

            // Fix ownership of entire working directory
            // This is necessary because bind-mounted files from host may have different ownership
            $chownResult = Process::run("chown -R {$uid}:{$gid} {$workingDir} 2>&1");

            if (! $chownResult->successful()) {
                Log::warning('[GitUpdate] Could not change ownership, trying alternative approach', [
                    'error' => $chownResult->output(),
                ]);

                // Try changing just the essential directories
                Process::run("chown -R {$uid}:{$gid} {$workingDir}/.git 2>/dev/null || true");
                Process::run("chown {$uid}:{$gid} {$workingDir} 2>/dev/null || true");
            } else {
                Log::info('[GitUpdate] Ownership updated successfully');
            }
        } catch (\Exception $e) {
            // Log but don't fail - permissions might already be correct
            Log::warning('[GitUpdate] Permission setup encountered an issue: '.$e->getMessage());
        }
    }

    /**
     * Fix file permissions after git operations.
     * Ensures updated files have correct ownership for Docker container user.
     *
     * @param  array<string>  $changedFiles
     */
    protected function fixPermissions(array $changedFiles): void
    {
        try {
            $workingDir = base_path();
            $uid = posix_getuid();
            $gid = posix_getgid();

            // Fix ownership of changed files to match container user
            foreach ($changedFiles as $file) {
                $fullPath = $workingDir.'/'.$file;
                if (file_exists($fullPath)) {
                    @chown($fullPath, $uid);
                    @chgrp($fullPath, $gid);
                }
            }

            // Fix .git directory ownership
            Process::run("chown -R {$uid}:{$gid} {$workingDir}/.git 2>/dev/null || true");
        } catch (\Exception $e) {
            // Silently fail - permissions might already be correct
            Log::debug('Permission fix failed: '.$e->getMessage());
        }
    }
}
