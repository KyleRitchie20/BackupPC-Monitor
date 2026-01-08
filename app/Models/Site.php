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
        'agent_token',
        'agent_version',
        'last_agent_contact',
        'backuppc_username',
        'backuppc_password',
        'polling_interval',
        'is_active'
    ];

    protected $hidden = [
        'ssh_password',
        'api_key',
        'backuppc_password',
        'agent_token'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_agent_contact' => 'datetime'
    ];

    /**
     * Generate a new agent token for this site
     */
    public function generateAgentToken(): string
    {
        $this->agent_token = hash('sha256', $this->id . $this->name . now()->toIso8601String());
        $this->save();
        return $this->agent_token;
    }

    /**
     * Update last agent contact timestamp
     */
    public function updateAgentContact(): void
    {
        $this->last_agent_contact = now();
        $this->save();
    }

    /**
     * Check if site has an active agent connection
     */
    public function hasActiveAgent(): bool
    {
        return !is_null($this->agent_token) &&
               !is_null($this->last_agent_contact) &&
               $this->last_agent_contact->gt(now()->subMinutes(10));
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function backupData()
    {
        return $this->hasMany(BackupData::class);
    }
}