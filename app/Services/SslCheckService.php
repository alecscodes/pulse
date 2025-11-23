<?php

namespace App\Services;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Carbon\Carbon;

class SslCheckService
{
    private const int TIMEOUT = 10;

    private const int EXPIRATION_WARNING_DAYS = 30;

    /**
     * Check SSL certificate for a monitor.
     *
     * @return array{valid: bool, issuer: string|null, valid_from: Carbon|null, valid_to: Carbon|null, days_until_expiration: int|null, error_message: string|null}
     */
    public function checkSslCertificate(Monitor $monitor): array
    {
        if (! str_starts_with(strtolower($monitor->url), 'https://')) {
            return $this->errorResult('URL is not HTTPS');
        }

        $parsed = parse_url($monitor->url);
        $host = $parsed['host'] ?? '';
        $port = $parsed['port'] ?? 443;

        if (empty($host)) {
            return $this->errorResult('Invalid URL format');
        }

        try {
            $socket = $this->connectToSsl($host, $port);

            if (! $socket) {
                return $this->errorResult('Connection failed');
            }

            $cert = $this->getCertificate($socket);
            fclose($socket);

            if (! $cert) {
                return $this->errorResult('Could not retrieve certificate');
            }

            return $this->parseCertificate($cert);
        } catch (\Exception $e) {
            return $this->errorResult($e->getMessage());
        }
    }

    /**
     * Update the latest monitor check with SSL information.
     */
    public function updateMonitorCheckWithSsl(Monitor $monitor, array $sslResult): void
    {
        MonitorCheck::where('monitor_id', $monitor->id)
            ->latest('checked_at')
            ->first()?->update([
                'ssl_valid' => $sslResult['valid'],
                'ssl_issuer' => $sslResult['issuer'],
                'ssl_valid_from' => $sslResult['valid_from'],
                'ssl_valid_to' => $sslResult['valid_to'],
                'ssl_days_until_expiration' => $sslResult['days_until_expiration'],
                'ssl_error_message' => $sslResult['error_message'],
            ]);
    }

    /**
     * Check if SSL certificate is expiring soon.
     */
    public function isExpiringSoon(?int $daysUntilExpiration): bool
    {
        return $daysUntilExpiration !== null && $daysUntilExpiration <= self::EXPIRATION_WARNING_DAYS;
    }

    /**
     * Connect to SSL server.
     */
    private function connectToSsl(string $host, int $port): mixed
    {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        return @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            self::TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        ) ?: null;
    }

    /**
     * Get certificate from socket.
     */
    private function getCertificate(mixed $socket): mixed
    {
        $params = stream_context_get_params($socket);

        return $params['options']['ssl']['peer_certificate'] ?? null;
    }

    /**
     * Parse certificate information.
     */
    private function parseCertificate(mixed $cert): array
    {
        $certInfo = openssl_x509_parse($cert);

        if (! $certInfo) {
            return $this->errorResult('Could not parse certificate');
        }

        $validFrom = Carbon::createFromTimestamp($certInfo['validFrom_time_t']);
        $validTo = Carbon::createFromTimestamp($certInfo['validTo_time_t']);
        $daysUntilExpiration = max(0, (int) now()->diffInDays($validTo, false));
        $isValid = now()->isBefore($validTo);
        $issuer = $certInfo['issuer']['CN'] ?? ($certInfo['issuer']['O'] ?? 'Unknown');

        return [
            'valid' => $isValid,
            'issuer' => $issuer,
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'days_until_expiration' => $daysUntilExpiration,
            'error_message' => $isValid ? null : 'Certificate has expired',
        ];
    }

    /**
     * Create error result array.
     */
    private function errorResult(string $message): array
    {
        return [
            'valid' => false,
            'issuer' => null,
            'valid_from' => null,
            'valid_to' => null,
            'days_until_expiration' => null,
            'error_message' => $message,
        ];
    }
}
