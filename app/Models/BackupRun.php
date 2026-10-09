<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'host_name',
        'backup_num',
        'type',
        'started_at',
        'completed_at',
        'duration_seconds',
        'backup_size',
        'error_count',
        'error_message',
        'status',
        'raw_data',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'backup_size' => 'integer',
        'duration_seconds' => 'integer',
        'error_count' => 'integer',
        'raw_data' => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Scope query to a specific date range (based on completed_at)
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        return $query->whereBetween('completed_at', [$start, $end]);
    }

    /**
     * Scope query to filter for specific sites
     */
    public function scopeForSites($query, $siteIds)
    {
        if (is_array($siteIds) || $siteIds instanceof \Illuminate\Support\Collection) {
            return $query->whereIn('site_id', $siteIds);
        }
        return $query->where('site_id', $siteIds);
    }

    /**
     * Scope for successful backup runs
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed/warning backup runs
     */
    public function scopeWithIssues($query)
    {
        return $query->whereIn('status', ['warning', 'failed']);
    }

    /**
     * Format byte size into human readable string
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->backup_size ?? 0;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    /**
     * Format duration into human readable string
     */
    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration_seconds ?? 0;
        if ($seconds <= 0) {
            return 'N/A';
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        }
        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        }
        return sprintf('%ds', $secs);
    }
}
