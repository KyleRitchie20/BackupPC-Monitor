<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'backuppc_url',
        'connection_method',
        'ssh_host',
        'ssh_port',
        'ssh_username',
        'ssh_password',
        'api_key',
        'backuppc_username',
        'backuppc_password',
        'polling_interval',
        'is_active'
    ];

    protected $hidden = [
        'ssh_password',
        'api_key',
        'backuppc_password'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function backupData()
    {
        return $this->hasMany(BackupData::class);
    }
}