<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Performs HTTP checks and optional content validation for monitors.
 *
 * Content validation (when enabled): expected title must match the page <title> exactly
 * (after normalizing whitespace); expected content must appear as a substring in the body.
 * If the browser-based check cannot run (e.g. Node unavailable or script failure), the
 * monitor is not marked down to avoid false positives.
 */
class MonitorCheckService
{
    private const int TIMEOUT = 30;

    private const int CONNECTIVITY_TIMEOUT = 5;

    /**
     * Check if internet connectivity is available.
     */
    public function checkConnectivity(): bool
    {
        try {
            return Http::timeout(self::CONNECTIVITY_TIMEOUT)->get('https://www.google.com')->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check a monitor's status.
     * Website type: HTTP request. IP type: ping (reachability only).
     *
     * @return array{status: string, response_time: int|null, status_code: int|null, error_message: string|null, content_valid: bool|null}
     */
    public function checkMonitor(Monitor $monitor): array
    {
        $startTime = microtime(true);

        if ($monitor->type === 'ip') {
            return $this->checkByPing($monitor, $startTime);
        }

        $result = $this->getDefaultResult();
        try {
            $response = $this->makeRequest($monitor);
            $responseTime = $this->calculateResponseTime($startTime);

            $result['response_time'] = $responseTime;
            $result['status_code'] = $response->status();

            if ($response->successful()) {
                $body = $response->body();
                $result['content_valid'] = $monitor->enable_content_validation ? $this->validateContent($monitor, $body) : null;
                $result['error_message'] ??= $result['content_valid'] === false ? 'Content validation failed' : null;
                $result['status'] = $result['content_valid'] === false ? 'down' : 'up';
            } else {
                $result['error_message'] = "HTTP {$response->status()}";
                $result['status'] = 'down';
            }
        } catch (\Exception $e) {
            $result = $this->handleException($monitor, $e, $startTime);
        }

        if ($result['status'] === 'down') {
            $this->log('warning', 'Monitor is down', $monitor, ['status_code' => $result['status_code'], 'error_message' => $result['error_message']]);
        }

        return $result;
    }

    /** Check IP reachability by ping (Linux). */
    private function checkByPing(Monitor $monitor, float $startTime): array
    {
        $result = $this->getDefaultResult();
        $host = trim($monitor->url);

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) === false) {
            $result['error_message'] = 'Invalid IP address';

            return $result;
        }

        $code = -1;
        @exec(sprintf('ping -c 1 -W 3 %s 2>/dev/null', escapeshellarg($host)), $_, $code);

        $result['response_time'] = $this->calculateResponseTime($startTime);
        $result['status'] = $code === 0 ? 'up' : 'down';
        if ($result['status'] === 'down') {
            $result['error_message'] = 'Ping failed or host unreachable';
            $this->log('warning', 'Monitor is down', $monitor, ['error_message' => $result['error_message']]);
        }

        return $result;
    }

    /**
     * Create a monitor check record.
     */
    public function createCheck(Monitor $monitor, array $checkResult): MonitorCheck
    {
        return MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => $checkResult['status'],
            'response_time' => $checkResult['response_time'],
            'status_code' => $checkResult['status_code'],
            'error_message' => $checkResult['error_message'],
            'content_valid' => $checkResult['content_valid'],
            'checked_at' => now(),
        ]);
    }

    /**
     * Get default result structure.
     */
    private function getDefaultResult(): array
    {
        return [
            'status' => 'down',
            'response_time' => null,
            'status_code' => null,
            'error_message' => null,
            'content_valid' => null,
        ];
    }

    /**
     * Make HTTP request based on monitor configuration.
     */
    private function makeRequest(Monitor $monitor)
    {
        $request = Http::timeout(self::TIMEOUT);

        if (! empty($monitor->headers)) {
            $request = $request->withHeaders($monitor->headers);
        }

        $method = strtolower($monitor->method);
        $parameters = $monitor->parameters ?? [];

        return $method === 'post'
            ? $request->post($monitor->url, $parameters)
            : $request->get($monitor->url, $parameters);
    }

    /**
     * Calculate response time in milliseconds.
     */
    private function calculateResponseTime(float $startTime): int
    {
        return (int) ((microtime(true) - $startTime) * 1000);
    }

    /**
     * Normalize title for exact match comparison (trim and collapse whitespace).
     */
    private function normalizeTitle(string $title): string
    {
        return trim(preg_replace('/\s+/', ' ', $title));
    }

    /**
     * Validate content against expected title and content.
     * Tries HTTP body first (fast). If that fails, runs browser script (Playwright) for SPAs that set title/content via JS.
     * When browser script cannot run (null), returns true to avoid false "down" notifications.
     */
    private function validateContent(Monitor $monitor, string $body): bool
    {
        if ($this->validateWithHttpBody($body, $monitor)) {
            return true;
        }

        $browserResult = $this->validateWithBrowser($monitor);

        return $browserResult;
    }

    /**
     * Validate content using HTTP response body.
     * Title: exact match only — the page <title> must equal the expected title (after normalization).
     * Content: body must contain the expected string (substring match).
     * Returns true only if both title and content checks pass.
     */
    private function validateWithHttpBody(string $body, Monitor $monitor): bool
    {
        $expectedTitle = $this->normalizeTitle($monitor->expected_title ?? '');
        $expectedContent = trim($monitor->expected_content ?? '');

        $extractedTitle = $this->normalizeTitle($this->extractTitleFromBody($body));

        $titleValid = empty($expectedTitle) || $extractedTitle === $expectedTitle;

        $contentValid = empty($expectedContent)
            || stripos($body, $expectedContent) !== false;

        return $titleValid && $contentValid;
    }

    /**
     * Extract title from HTML body.
     */
    private function extractTitleFromBody(string $body): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches)) {
            $title = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $this->normalizeTitle($title);
        }

        return '';
    }

    /**
     * Validate content using Playwright (Chromium) via external script.
     * Used when HTTP body validation fails (e.g. SPAs that set title/content via JS).
     * Returns true when script could not run (null) to avoid false "down" notifications.
     */
    private function validateWithBrowser(Monitor $monitor): bool
    {
        if (! $this->commandExists('node')) {
            return true;
        }

        $data = $this->runBrowserValidationScript($monitor);
        if ($data === null) {
            return true;
        }

        return $this->isBrowserContentValid($monitor, $data);
    }

    /**
     * Run the SPA validation script and return parsed result or null on failure.
     *
     * @return array{title: string, textContent: string}|null
     */
    private function runBrowserValidationScript(Monitor $monitor): ?array
    {
        $config = [
            'url' => $monitor->url,
            'expectedTitle' => $monitor->expected_title,
            'expectedContent' => $monitor->expected_content,
        ];
        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $result = Process::timeout(self::TIMEOUT + 15)
            ->path(base_path())
            ->run([
                'node',
                base_path('scripts/validate-spa-content.js'),
                $configJson,
            ]);

        $output = trim($result->output());
        if ($output === '') {
            return null;
        }

        $data = json_decode($output, true);
        if (! \is_array($data) || isset($data['error'])) {
            return null;
        }

        return $data;
    }

    /**
     * Check that browser result matches expected title (exact match) and content (contains).
     */
    private function isBrowserContentValid(Monitor $monitor, array $data): bool
    {
        $expectedTitle = $this->normalizeTitle($monitor->expected_title ?? '');
        $actualTitle = $this->normalizeTitle($data['title'] ?? '');
        $titleValid = $expectedTitle === '' || $actualTitle === $expectedTitle;

        $expectedContent = trim($monitor->expected_content ?? '');
        if ($expectedContent === '') {
            return $titleValid;
        }

        $textContent = preg_replace('/\s+/', ' ', $data['textContent'] ?? '');
        $normalizedExpected = preg_replace('/\s+/', ' ', $expectedContent);

        return $titleValid && stripos($textContent, $normalizedExpected) !== false;
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

    /**
     * Handle exception during monitor check.
     */
    private function handleException(Monitor $monitor, \Exception $e, float $startTime): array
    {
        $result = $this->getDefaultResult();
        $result['response_time'] = $this->calculateResponseTime($startTime);
        $result['error_message'] = $e->getMessage();

        if ($this->isTemporarySystemError($e)) {
            $result['status'] = 'up';
            $this->log('warning', 'Monitor check skipped due to temporary system error', $monitor, ['error' => $e->getMessage()]);

            return $result;
        }

        $result['status'] = 'down';
        $this->log('error', 'Monitor check failed', $monitor, ['error' => $e->getMessage()]);

        return $result;
    }

    /**
     * Check if an exception represents a temporary system error that should not mark monitor as down.
     */
    private function isTemporarySystemError(\Exception $e): bool
    {
        return str_contains($e->getMessage(), 'proc_open') ||
            str_contains($e->getMessage(), 'posix_spawn') ||
            str_contains($e->getMessage(), 'Resource temporarily unavailable');
    }

    /**
     * Log monitor event.
     */
    private function log(string $level, string $message, Monitor $monitor, array $context = []): void
    {
        Log::channel('database')->{$level}($message, array_merge([
            'category' => 'monitor',
            'monitor_id' => $monitor->id,
            'monitor_name' => $monitor->name,
        ], $context));
    }
}
