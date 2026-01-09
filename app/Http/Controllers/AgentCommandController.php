<?php

namespace App\Http\Controllers;

use App\Events\AgentCommandEvent;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AgentCommandController extends Controller
{
    /**
     * Send a command to an agent via WebSocket + Cache
     */
    public function sendCommand(Request $request, int $siteId)
    {
        $request->validate([
            'command' => 'required|string|in:refresh,status,restart,stop',
        ]);

        $site = Site::find($siteId);

        if (!$site) {
            return response()->json(['error' => 'Site not found'], 404);
        }

        if (!$site->agent_token) {
            return response()->json(['error' => 'Site does not have an agent configured'], 400);
        }

        $command = $request->command;
        $commandData = [
            'command' => $command,
            'payload' => $request->all(),
            'timestamp' => now()->toIso8601String(),
        ];

        Log::info("Sending {$command} command to agent for site: {$site->name}");

        // Store command in cache for polling agents
        $commandKey = "agent_command:{$siteId}";
        Cache::put($commandKey, $commandData, now()->addMinutes(5));

        // Also broadcast via WebSocket/Reverb for real-time delivery
        $payload = [
            'site_id' => $siteId,
            'agent_token' => $site->agent_token,
            'command' => $command,
            'timestamp' => $commandData['timestamp'],
        ];
        AgentCommandEvent::dispatch($payload);

        return response()->json([
            'success' => true,
            'message' => "{$command} command sent to agent",
            'site_id' => $siteId,
            'command' => $command,
            'timestamp' => $commandData['timestamp'],
        ]);
    }

    /**
     * Send refresh command to multiple agents (bulk)
     */
    public function bulkRefresh(Request $request)
    {
        $request->validate([
            'site_ids' => 'required|array',
            'site_ids.*' => 'exists:sites,id',
        ]);

        $commands = [];
        foreach ($request->site_ids as $siteId) {
            $site = Site::find($siteId);
            if ($site && $site->agent_token) {
                $commandData = [
                    'command' => 'refresh',
                    'payload' => [],
                    'timestamp' => now()->toIso8601String(),
                ];

                // Store in cache
                $commandKey = "agent_command:{$siteId}";
                Cache::put($commandKey, $commandData, now()->addMinutes(5));

                // Broadcast via WebSocket
                $payload = [
                    'site_id' => $siteId,
                    'agent_token' => $site->agent_token,
                    'command' => 'refresh',
                    'timestamp' => $commandData['timestamp'],
                ];
                AgentCommandEvent::dispatch($payload);

                $commands[] = $siteId;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Refresh commands sent to ' . count($commands) . ' agents',
            'site_ids' => $commands,
        ]);
    }
}
