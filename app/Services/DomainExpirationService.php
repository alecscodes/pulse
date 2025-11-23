<?php

namespace App\Services;

use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DomainExpirationService
{
    private const int TIMEOUT = 10;

    private const int EXPIRATION_WARNING_DAYS = 30;

    private const int CACHE_TTL = 86400;

    private const array WHOIS_SERVERS = [
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
        'info' => 'whois.afilias.net',
        'biz' => 'whois.neulevel.biz',
        'us' => 'whois.nic.us',
        'uk' => 'whois.nic.uk',
        'de' => 'whois.denic.de',
        'fr' => 'whois.afnic.fr',
        'it' => 'whois.nic.it',
        'es' => 'whois.nic.es',
        'nl' => 'whois.domain-registry.nl',
        'eu' => 'whois.eu',
        'io' => 'whois.nic.io',
        'co' => 'whois.nic.co',
        'dev' => 'whois.nic.google',
        'app' => 'whois.nic.google',
        'store' => 'whois.nic.store',
        'ro' => 'whois.rotld.ro',
    ];

    private const array DATE_PATTERNS = [
        '/expir[^:]*:\s*(\d{4}[-.\/]\d{2}[-.\/]\d{2})/i',
        '/expir[^:]*:\s*(\d{2}[-.\/]\d{2}[-.\/]\d{4})/i',
        '/Registry Expiry Date:\s*(\d{4}-\d{2}-\d{2})/i',
        '/expires[^:]*:\s*(\d{4}-\d{2}-\d{2})/i',
    ];

    public function getDomainExpiration(Monitor $monitor): array
    {
        $domain = $this->extractDomain($monitor->url);

        if (! $domain) {
            $this->logError($monitor, 'Could not extract domain from URL', ['url' => $monitor->url]);

            return $this->errorResult('Could not extract domain from URL');
        }

        return Cache::remember("domain_expiration_{$domain}", self::CACHE_TTL, fn () => $this->queryWhois($domain, $monitor));
    }

    public function isExpiringSoon(?int $daysUntilExpiration): bool
    {
        return $daysUntilExpiration !== null && $daysUntilExpiration <= self::EXPIRATION_WARNING_DAYS;
    }

    private function extractDomain(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $host = strtok($host, ':');
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return $host;
        }

        // Handle multi-part TLDs (e.g., co.uk, com.au)
        $multiPartTlds = ['co.uk', 'com.au', 'co.za', 'com.br', 'co.jp', 'com.mx'];
        $tld = strtolower(end($parts));
        $secondLevel = strtolower($parts[count($parts) - 2] ?? '');

        if (in_array("{$secondLevel}.{$tld}", $multiPartTlds, true) && count($parts) >= 3) {
            return implode('.', array_slice($parts, -3));
        }

        return implode('.', array_slice($parts, -2));
    }

    private function queryWhois(string $domain, Monitor $monitor): array
    {
        $parts = explode('.', $domain);
        $tld = strtolower(end($parts));
        $whoisServer = self::WHOIS_SERVERS[$tld] ?? 'whois.iana.org';

        try {
            $socket = @fsockopen($whoisServer, 43, $errno, $errstr, self::TIMEOUT);

            if (! $socket) {
                $this->logError($monitor, $errstr ?: 'Connection failed', ['domain' => $domain, 'whois_server' => $whoisServer]);

                return $this->errorResult($errstr ?: 'Connection failed');
            }

            fwrite($socket, "{$domain}\r\n");
            $response = stream_get_contents($socket);
            fclose($socket);

            if (empty($response)) {
                $this->logError($monitor, 'Empty WHOIS response', ['domain' => $domain]);

                return $this->errorResult('Empty WHOIS response');
            }

            return $this->parseWhoisResponse($response, $domain, $monitor);
        } catch (\Exception $e) {
            $this->logError($monitor, $e->getMessage(), ['domain' => $domain]);

            return $this->errorResult($e->getMessage());
        }
    }

    private function parseWhoisResponse(string $response, string $domain, Monitor $monitor): array
    {
        foreach (self::DATE_PATTERNS as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                try {
                    $dateString = str_replace(['/', '.'], '-', $matches[1]);
                    // Handle DD-MM-YYYY format
                    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $dateString, $dateParts)) {
                        $dateString = "{$dateParts[3]}-{$dateParts[2]}-{$dateParts[1]}";
                    }
                    $expiresAt = Carbon::parse($dateString);
                    $daysUntilExpiration = max(0, (int) now()->diffInDays($expiresAt, false));

                    if ($daysUntilExpiration <= 30) {
                        Log::channel('database')->warning('Domain expiring soon', [
                            'category' => 'domain',
                            'monitor_id' => $monitor->id,
                            'monitor_name' => $monitor->name,
                            'domain' => $domain,
                            'days_until_expiration' => $daysUntilExpiration,
                        ]);
                    }

                    return [
                        'expires_at' => $expiresAt,
                        'days_until_expiration' => $daysUntilExpiration,
                        'error_message' => null,
                    ];
                } catch (\Exception $e) {
                    $this->logError($monitor, 'Invalid date format in WHOIS response', ['domain' => $domain, 'exception' => $e->getMessage()]);

                    return $this->errorResult('Invalid date format in WHOIS response');
                }
            }
        }

        $this->logError($monitor, 'Could not parse expiration date from WHOIS response', ['domain' => $domain]);

        return $this->errorResult('Could not parse expiration date from WHOIS response');
    }

    private function logError(Monitor $monitor, string $error, array $context = []): void
    {
        Log::channel('database')->error('Domain expiration check failed', array_merge([
            'category' => 'domain',
            'monitor_id' => $monitor->id,
            'monitor_name' => $monitor->name,
            'error' => $error,
        ], $context));
    }

    private function errorResult(string $message): array
    {
        return [
            'expires_at' => null,
            'days_until_expiration' => null,
            'error_message' => $message,
        ];
    }
}
