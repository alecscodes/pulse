<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MonitorCheckService
{
    /**
     * Check if internet connectivity is available.
     */
    public function checkConnectivity(): bool
    {
        try {
            $response = Http::timeout(5)->get('https://www.google.com');

            return $response->successful();
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
        $result = [
            'status' => 'down',
            'response_time' => null,
            'status_code' => null,
            'response_body' => null,
            'error_message' => null,
            'content_valid' => null,
        ];

        try {
            $url = $monitor->url;
            $headers = $monitor->headers ?? [];
            $method = strtolower($monitor->method);

            $request = Http::timeout(30);

            if (! empty($headers)) {
                $request = $request->withHeaders($headers);
            }

            if ($method === 'post') {
                $parameters = $monitor->parameters ?? [];
                $response = $request->post($url, $parameters);
            } else {
                $parameters = $monitor->parameters ?? [];
                $response = $request->get($url, $parameters);
            }

            $responseTime = (int) ((microtime(true) - $startTime) * 1000);

            $result['response_time'] = $responseTime;
            $result['status_code'] = $response->status();

            if ($response->successful()) {
                $responseBody = $response->body();
                $result['response_body'] = Str::limit($responseBody, 5000); // Limit body size

                if ($monitor->enable_content_validation) {
                    $result['content_valid'] = $this->validateContent($responseBody, $monitor);
                    $result['status'] = $result['content_valid'] ? 'up' : 'down';
                } else {
                    $result['status'] = 'up';
                }
            } else {
                $result['error_message'] = "HTTP {$response->status()}";
                $result['status'] = 'down';
            }
        } catch (\Exception $e) {
            $responseTime = (int) ((microtime(true) - $startTime) * 1000);
            $result['response_time'] = $responseTime;
            $result['error_message'] = $e->getMessage();
            $result['status'] = 'down';
        }

        return $result;
    }

    /**
     * Validate content against expected title and content.
     */
    protected function validateContent(string $body, Monitor $monitor): bool
    {
        $isValid = true;

        if ($monitor->expected_title) {
            $title = $this->extractTitle($body);
            $isValid = stripos($title, $monitor->expected_title) !== false;
        }

        if ($monitor->expected_content) {
            $isValid = $isValid && (stripos($body, $monitor->expected_content) !== false);
        }

        return $isValid;
    }

    /**
     * Extract title from HTML body.
     */
    protected function extractTitle(string $body): string
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $body, $matches)) {
            return trim($matches[1]);
        }

        return '';
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
}
