<?php

use App\Http\Controllers\MonitorController;
use App\Models\Monitor;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()) && Setting::isRegistrationAllowed(),
    ]);
})->name('home');

Route::get('dashboard', function () {
    $monitors = Monitor::where('user_id', auth()->id())
        ->with(['checks' => function ($query) {
            $query->latest('checked_at')->limit(1);
        }])
        ->latest()
        ->limit(6)
        ->get()
        ->map(function ($monitor) {
            $latestCheck = $monitor->checks->first();
            $isDown = $monitor->currentDowntime()->exists();

            return [
                'id' => $monitor->id,
                'name' => $monitor->name,
                'url' => $monitor->url,
                'status' => $latestCheck?->status ?? 'unknown',
                'is_down' => $isDown,
                'response_time' => $latestCheck?->response_time,
            ];
        });

    return Inertia::render('Dashboard', [
        'monitors' => $monitors,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('monitors', MonitorController::class);
    Route::get('logs', [\App\Http\Controllers\LogController::class, 'index'])->name('logs.index');
    Route::delete('logs', [\App\Http\Controllers\LogController::class, 'destroy'])->name('logs.destroy');
});

require __DIR__.'/settings.php';
