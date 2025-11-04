<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IpBanService
{
    /**
     * @return array<int, string>
     */
    public function getAllIps(Request $request): array
    {
        $sources = [
            $request->ip(),
            $request->header('X-Forwarded-For'),
            $request->header('X-Real-Ip'),
            $request->header('CF-Connecting-IP'),
            $request->header('X-Client-Ip'),
            $request->server('REMOTE_ADDR'),
        ];

        $validIps = [];
        foreach ($sources as $source) {
            if (! $source) {
                continue;
            }

            foreach (explode(',', (string) $source) as $ip) {
                $ip = trim($ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    $validIps[] = $ip;
                }
            }
        }

        return array_values(array_unique($validIps));
    }

    public function isBanned(Request $request): bool
    {
        $cacheKey = 'banned_ip_'.$request->ip();

        return Cache::remember($cacheKey, 3600, function () use ($request) {
            $ips = $this->getAllIps($request);

            return DB::table('banned_ips')
                ->whereIn('ip', $ips)
                ->exists();
        });
    }

    public function ban(Request $request, ?string $reason = null): void
    {
        $ips = $this->getAllIps($request);

        foreach ($ips as $ip) {
            DB::table('banned_ips')->insertOrIgnore([
                'ip' => $ip,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Cache::forget('banned_ip_'.$ip);
        }
    }

    public function recordFailedLogin(Request $request): void
    {
        $key = 'failed_login_'.$request->ip();
        Cache::add($key, 0, 3600);
        $count = Cache::increment($key);

        if ($count >= 2) {
            $this->ban($request, 'Failed login attempts');
            Cache::forget($key);
        }
    }

    public function unban(string $ip): bool
    {
        $deleted = DB::table('banned_ips')->where('ip', $ip)->delete() > 0;

        if ($deleted) {
            Cache::forget('banned_ip_'.$ip);
        }

        return $deleted;
    }

    public function unbanAll(): int
    {
        $ips = DB::table('banned_ips')->pluck('ip')->toArray();
        $count = DB::table('banned_ips')->delete();

        foreach ($ips as $ip) {
            Cache::forget('banned_ip_'.$ip);
        }

        return $count;
    }
}
