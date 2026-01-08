<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BackupDataUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $siteId;
    public $data;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(int $siteId, array $data)
    {
        $this->siteId = $siteId;
        $this->data = $data;
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
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }
}
