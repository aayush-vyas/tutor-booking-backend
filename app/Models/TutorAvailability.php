<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorAvailability extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tutor_id',
        'start_time',
        'end_time',
        'is_available',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_available' => 'boolean',
    ];

    /**
     * Get the tutor that owns this availability.
     */
    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    /**
     * Scope a query to only include available slots.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope a query to only include blocked slots.
     */
    public function scopeBlocked($query)
    {
        return $query->where('is_available', false);
    }

    /**
     * Scope a query to include slots within a date range.
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_time', [$startDate, $endDate])
                ->orWhereBetween('end_time', [$startDate, $endDate])
                ->orWhere(function ($q) use ($startDate, $endDate) {
                    $q->where('start_time', '<=', $startDate)
                        ->where('end_time', '>=', $endDate);
                });
        });
    }

    /**
     * Check if this slot overlaps with given time range.
     */
    public function overlaps(Carbon $start, Carbon $end): bool
    {
        return $this->start_time < $end && $this->end_time > $start;
    }
}
