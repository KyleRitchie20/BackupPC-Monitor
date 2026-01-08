<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupData extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'host_name',
        'state',
        'last_backup_time',
        'last_backup_size',
        'full_backup_count',
        'incremental_backup_count',
        'error_message',
        'raw_data'
    ];

    protected $casts = [
        'last_backup_time' => 'datetime',
        'raw_data' => 'array'
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}