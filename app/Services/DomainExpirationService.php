<?php

namespace App\Services;

use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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
    ];

    /**
     * Get domain expiration date from WHOIS.
     *
     * @return array{expires_at: Carbon|null, days_until_expiration: int|null, error_message: string|null}
     */
    public function getDomainExpiration(Monitor $monitor): array
    {
        $domain = $this->extractDomain($monitor->url);

        if (! $domain) {
            \Illuminate\Support\Facades\Log::channel('database')->error('Domain extraction failed', [
                'category' => 'domain',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'url' => $monitor->url,
                'error' => 'Could not extract domain from URL',
            ]);

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

        $host = explode(':', $host)[0];

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function queryWhois(string $domain, Monitor $monitor): array
    {
        $whoisServer = $this->getWhoisServer($domain);

        if (! $whoisServer) {
            \Illuminate\Support\Facades\Log::channel('database')->error('WHOIS server determination failed', [
                'category' => 'domain',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'domain' => $domain,
                'error' => 'Could not determine WHOIS server for domain',
            ]);

            return $this->errorResult('Could not determine WHOIS server for domain');
        }

        try {
            $socket = @fsockopen($whoisServer, 43, $errno, $errstr, self::TIMEOUT);

            if (! $socket) {
                \Illuminate\Support\Facades\Log::channel('database')->error('WHOIS connection failed', [
                    'category' => 'domain',
                    'monitor_id' => $monitor->id,
                    'monitor_name' => $monitor->name,
                    'domain' => $domain,
                    'whois_server' => $whoisServer,
                    'error' => "Connection failed: {$errstr}",
                ]);

                return $this->errorResult("Connection failed: {$errstr}");
            }

            fwrite($socket, "{$domain}\r\n");

            $response = stream_get_contents($socket);
            fclose($socket);

            return $this->parseWhoisResponse($response, $domain, $monitor);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::channel('database')->error('Domain expiration check failed', [
                'category' => 'domain',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResult($e->getMessage());
        }
    }

    private function getWhoisServer(string $domain): ?string
    {
        $parts = explode('.', $domain);
        $tld = strtolower(end($parts));

        return self::WHOIS_SERVERS[$tld] ?? 'whois.iana.org';
    }

    private function parseWhoisResponse(string $response, string $domain, Monitor $monitor): array
    {
        if (! preg_match('/expir[^:]*:\s*(\d{4}[-.\/]\d{2}[-.\/]\d{2})/i', $response, $matches)) {
            \Illuminate\Support\Facades\Log::channel('database')->error('WHOIS response parsing failed', [
                'category' => 'domain',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'domain' => $domain,
                'error' => 'Could not parse expiration date from WHOIS response',
            ]);

            return $this->errorResult('Could not parse expiration date from WHOIS response');
        }

        try {
            $dateString = str_replace(['/', '.'], '-', $matches[1]);
            $expiresAt = Carbon::parse($dateString);
            $daysUntilExpiration = max(0, (int) now()->diffInDays($expiresAt, false));

            if ($daysUntilExpiration <= 30) {
                \Illuminate\Support\Facades\Log::channel('database')->warning('Domain expiring soon', [
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
            \Illuminate\Support\Facades\Log::channel('database')->error('Invalid date format in WHOIS response', [
                'category' => 'domain',
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
                'domain' => $domain,
                'error' => 'Invalid date format in WHOIS response',
                'exception' => $e->getMessage(),
            ]);

            return $this->errorResult('Invalid date format in WHOIS response');
        }
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
