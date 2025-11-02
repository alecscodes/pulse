<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $monitor_id
 * @property string $status
 * @property int|null $response_time
 * @property int|null $status_code
 * @property string|null $response_body
 * @property string|null $error_message
 * @property bool|null $content_valid
 * @property Carbon $checked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class MonitorCheck extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'monitor_id',
        'status',
        'response_time',
        'status_code',
        'response_body',
        'error_message',
        'content_valid',
        'checked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_time' => 'integer',
            'status_code' => 'integer',
            'content_valid' => 'boolean',
            'checked_at' => 'datetime',
        ];
    }

    /**
     * Get the monitor that owns the check.
     */
    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
