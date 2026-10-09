<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'site_id',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time',
        'date_range_type',
        'recipients',
        'format',
        'include_healthy',
        'include_warnings',
        'is_active',
        'last_run_at',
        'created_by',
    ];

    protected $casts = [
        'recipients' => 'array',
        'include_healthy' => 'boolean',
        'include_warnings' => 'boolean',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Compute the start and end dates based on date_range_type
     *
     * @return array [Carbon $startDate, Carbon $endDate]
     */
    public function resolveDateRange(): array
    {
        $now = now();

        switch ($this->date_range_type) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];

            case 'yesterday':
                $yesterday = $now->copy()->subDay();
                return [$yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()];

            case 'last_14_days':
                return [$now->copy()->subDays(14)->startOfDay(), $now->copy()->endOfDay()];

            case 'last_30_days':
                return [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()];

            case 'this_month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];

            case 'last_month':
                $lastMonth = $now->copy()->subMonthNoOverflow();
                return [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()];

            case 'last_7_days':
            default:
                return [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()];
        }
    }

    /**
     * Check if schedule is due to run at the given timestamp
     */
    public function isDue(Carbon $now = null): bool
    {
        $now = $now ?: now();

        if (!$this->is_active) {
            return false;
        }

        // Avoid running multiple times in the same day if last_run_at was today
        if ($this->last_run_at && $this->last_run_at->isSameDay($now)) {
            return false;
        }

        // Parse time (e.g. "08:00")
        [$hour, $minute] = explode(':', $this->time ?: '08:00');
        if ((int)$now->format('H') < (int)$hour) {
            return false;
        }

        switch ($this->frequency) {
            case 'daily':
                return true;

            case 'weekly':
                return (int)$now->dayOfWeek === (int)$this->day_of_week;

            case 'monthly':
                return (int)$now->day === (int)$this->day_of_month;

            default:
                return false;
        }
    }
}
