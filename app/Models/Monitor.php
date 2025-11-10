<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $type
 * @property string $url
 * @property string $method
 * @property array|null $headers
 * @property array|null $parameters
 * @property bool $enable_content_validation
 * @property string|null $expected_title
 * @property string|null $expected_content
 * @property bool $is_active
 * @property int $check_interval
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection<int, MonitorCheck> $checks
 * @property \Illuminate\Database\Eloquent\Collection<int, MonitorDowntime> $downtimes
 * @property int|null $has_active_downtime
 */
class Monitor extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'url',
        'method',
        'headers',
        'parameters',
        'enable_content_validation',
        'expected_title',
        'expected_content',
        'is_active',
        'check_interval',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'parameters' => 'array',
            'enable_content_validation' => 'boolean',
            'is_active' => 'boolean',
            'check_interval' => 'integer',
        ];
    }

    /**
     * Get the user that owns the monitor.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the checks for the monitor.
     */
    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    /**
     * Get the downtimes for the monitor.
     */
    public function downtimes(): HasMany
    {
        return $this->hasMany(MonitorDowntime::class);
    }

    /**
     * Get the latest check for the monitor.
     */
    public function latestCheck(): HasMany
    {
        return $this->hasMany(MonitorCheck::class)->latest('checked_at');
    }

    /**
     * Get the current downtime if the monitor is down.
     */
    public function currentDowntime(): HasMany
    {
        return $this->hasMany(MonitorDowntime::class)->whereNull('ended_at');
    }
}
