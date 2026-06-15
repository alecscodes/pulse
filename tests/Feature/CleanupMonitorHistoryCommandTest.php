<?php

use App\Models\MonitorCheck;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

test('monitor-history:cleanup deletes old records and keeps recent ones', function () {
    $old = MonitorCheck::factory()->create(['checked_at' => now()->subDays(31)]);
    $recent = MonitorCheck::factory()->create(['checked_at' => now()->subDays(5)]);

    Artisan::call('monitor-history:cleanup');

    expect(MonitorCheck::where('id', $old->id)->exists())->toBeFalse();
    expect(MonitorCheck::where('id', $recent->id)->exists())->toBeTrue();
});

test('monitor-history:cleanup respects retention setting', function () {
    Setting::set('monitor_check_retention_days', 7);

    MonitorCheck::factory()->create(['checked_at' => now()->subDays(10)]);

    Artisan::call('monitor-history:cleanup');

    expect(MonitorCheck::count())->toBe(0);
});
