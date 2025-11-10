<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MonitorCheckService
{
    private const TIMEOUT = 30;

    private const CONNECTIVITY_TIMEOUT = 5;

    private const MAX_BODY_SIZE = 5000;

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
     *
     * @return array{status: string, response_time: int|null, status_code: int|null, response_body: string|null, error_message: string|null, content_valid: bool|null}
     */
    public function checkMonitor(Monitor $monitor): array
    {
        $startTime = microtime(true);
        $result = $this->getDefaultResult();

        try {
            $response = $this->makeRequest($monitor);
            $responseTime = $this->calculateResponseTime($startTime);

            $result['response_time'] = $responseTime;
            $result['status_code'] = $response->status();

            if ($response->successful()) {
                $body = $response->body();
                $result['response_body'] = Str::limit($body, self::MAX_BODY_SIZE);

                if ($monitor->enable_content_validation) {
                    $result['content_valid'] = $this->validateContent($monitor, $body);
                } else {
                    $result['content_valid'] = null;
                }

                $result['status'] = $result['content_valid'] === false ? 'down' : 'up';
            } else {
                $result['error_message'] = "HTTP {$response->status()}";
                $result['status'] = 'down';
            }
        } catch (\Exception $e) {
            $result['response_time'] = $this->calculateResponseTime($startTime);
            $result['error_message'] = $e->getMessage();
            $result['status'] = 'down';
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
            'response_body' => $checkResult['response_body'],
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
            'response_body' => null,
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
     * Validate content against expected title and content.
     * Uses Playwright for title validation when title is expected (SPAs set title via JS).
     * Falls back to HTTP body validation for content-only checks.
     * Note: Playwright may fail on Alpine Linux due to glibc/musl incompatibility.
     * In such cases, the calling code will fall back to title-only validation.
     */
    private function validateContent(Monitor $monitor, string $body): bool
    {
        $expectedTitle = trim($monitor->expected_title ?? '');
        $hasExpectedTitle = ! empty($expectedTitle);

        // If title is expected, check if it's in static HTML
        // If not found, it's likely an SPA - use Playwright
        if ($hasExpectedTitle) {
            $titleInStaticHtml = $this->extractTitleFromBody($body) === $expectedTitle
                || stripos($body, $expectedTitle) !== false;

            // Title not in static HTML - must be an SPA, use Playwright
            if (! $titleInStaticHtml) {
                return $this->validateWithPlaywright($monitor);
            }
        }

        // Try HTTP body first (faster, works with tests and when title is in static HTML)
        if ($this->validateWithHttpBody($body, $monitor)) {
            return true;
        }

        // Use Playwright as fallback when HTTP body validation fails
        return $this->validateWithPlaywright($monitor);
    }

    /**
     * Validate content using HTTP response body.
     * Returns true only if BOTH title AND content are valid.
     * If either fails, returns false (monitor marked as down).
     */
    private function validateWithHttpBody(string $body, Monitor $monitor): bool
    {
        $expectedTitle = trim($monitor->expected_title ?? '');
        $expectedContent = trim($monitor->expected_content ?? '');

        // If title is expected, it must match
        $titleValid = empty($expectedTitle)
            || $this->extractTitleFromBody($body) === $expectedTitle
            || stripos($body, $expectedTitle) !== false;

        // If content is expected, it must be found in body
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

            return trim(preg_replace('/\s+/', ' ', $title));
        }

        return '';
    }

    /**
     * Validate content using Playwright.
     */
    private function validateWithPlaywright(Monitor $monitor): bool
    {
        if (! $this->commandExists('node')) {
            return false;
        }

        try {
            $config = [
                'url' => $monitor->url,
                'expectedTitle' => $monitor->expected_title,
                'expectedContent' => $monitor->expected_content,
            ];

            $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $script = $this->getPlaywrightScript($configJson);
            $output = trim(shell_exec('node -e '.escapeshellarg($script).' 2>/dev/null') ?: '');

            if (empty($output)) {
                return false;
            }

            $data = json_decode($output, true);
            if (! is_array($data)) {
                return false;
            }

            // Validate title: must match exactly if expected
            $expectedTitle = trim($monitor->expected_title ?? '');
            $titleValid = empty($expectedTitle) || trim($data['title'] ?? '') === $expectedTitle;

            // Validate content: must be found if expected
            $expectedContent = trim($monitor->expected_content ?? '');
            $contentValid = empty($expectedContent) || ($data['hasContent'] ?? false);

            return $titleValid && $contentValid;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get Playwright script for content validation.
     */
    private function getPlaywrightScript(string $configJson): string
    {
        return <<<SCRIPT
const {chromium} = require('playwright');
const config = {$configJson};
(async () => {
  try {
    const browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
    const page = await browser.newPage();
    await page.goto(config.url, {waitUntil: 'networkidle', timeout: 30000});
    const title = await page.title();
    const textContent = await page.textContent('body') || '';
    await browser.close();
    const normalize = (str) => str.replace(/\s+/g, ' ').trim().toLowerCase();
    const hasContent = config.expectedContent
      ? normalize(textContent).includes(normalize(config.expectedContent))
      : true;
    const result = {
      title: title || '',
      hasContent: hasContent
    };
    console.log(JSON.stringify(result));
  } catch(e) {
    console.log(JSON.stringify({title: '', hasContent: false, error: e.message}));
    process.exit(1);
  }
})();
SCRIPT;
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
