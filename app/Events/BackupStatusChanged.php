<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $siteId;
    public $hostName;
    public $oldStatus;
    public $newStatus;
    public $details;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(
        int $siteId,
        string $hostName,
        ?string $oldStatus,
        string $newStatus,
        array $details = []
    ) {
        $this->siteId = $siteId;
        $this->hostName = $hostName;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->details = $details;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('site.' . $this->siteId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->siteId,
            'host_name' => $this->hostName,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'details' => $this->details,
            'timestamp' => $this->timestamp,
        ];
    }
}
