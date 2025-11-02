<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $monitor_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property Carbon|null $last_notification_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MonitorDowntime extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'monitor_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'last_notification_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'last_notification_at' => 'datetime',
        ];
    }

    /**
     * Get the monitor that owns the downtime.
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    /**
     * Calculate and set the duration in seconds.
     */
    public function calculateDuration(): void
    {
        if ($this->ended_at !== null) {
            $this->duration_seconds = (int) $this->ended_at->diffInSeconds($this->started_at);
        }
    }
}
