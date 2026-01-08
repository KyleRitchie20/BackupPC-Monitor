<?php

namespace App\Http\Controllers;

use App\Events\BackupDataUpdated;
use App\Events\BackupStatusChanged;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller
{
    /**
     * Handle incoming backup data from agent
     */
    public function receiveData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_id' => 'required|exists:sites,id',
            'agent_token' => 'required|exists:sites,agent_token',
            'data' => 'required|array',
            'event_type' => 'nullable|string|in:full_update,status_change,heartbeat',
        ]);

        if ($validator->fails()) {
            Log::warning('Agent data validation failed', $validator->errors()->toArray());
            return response()->json(['error' => 'Invalid data', 'messages' => $validator->errors()], 422);
        }

        $siteId = $request->site_id;
        $site = Site::find($siteId);

        // Verify agent token
        if (!$site || !$site->agent_token || $site->agent_token !== $request->agent_token) {
            Log::warning('Agent authentication failed for site: ' . $siteId);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $eventType = $request->get('event_type', 'full_update');

        Log::info("Agent data received for site {$site->name}", [
            'site_id' => $siteId,
            'event_type' => $eventType,
        ]);

        // Store the data
        $this->storeBackupData($site, $request->data);

        // Broadcast event based on type
        switch ($eventType) {
            case 'status_change':
                if (isset($request->data['host_name'])) {
                    $oldStatus = $request->get('old_status');
                    BackupStatusChanged::dispatch(
                        $siteId,
                        $request->data['host_name'],
                        $oldStatus,
                        $request->data['state'] ?? 'unknown',
                        $request->data
                    );
                }
                break;

            case 'heartbeat':
                // Just log heartbeat, no broadcast needed
                break;

            default:
                // Full update - broadcast to all listeners
                BackupDataUpdated::dispatch($siteId, $request->data);
                break;
        }

        return response()->json([
            'success' => true,
            'site_id' => $siteId,
            'message' => 'Data received and processed',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle agent registration/heartbeat
     */
    public function registerAgent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_id' => 'required|exists:sites,id',
            'agent_token' => 'required|exists:sites,agent_token',
            'agent_version' => 'nullable|string',
            'hostname' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid data'], 422);
        }

        $site = Site::find($request->site_id);

        if (!$site || $site->agent_token !== $request->agent_token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        Log::info("Agent registered for site: {$site->name}", [
            'agent_version' => $request->get('agent_version'),
            'hostname' => $request->get('hostname'),
        ]);

        return response()->json([
            'success' => true,
            'site_id' => $site->id,
            'site_name' => $site->name,
            'dashboard_url' => config('app.url'),
            'ws_channel' => 'site.' . $site->id,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get site configuration for agent
     */
    public function getSiteConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'site_id' => 'required|exists:sites,id',
            'agent_token' => 'required|exists:sites,agent_token',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid data'], 422);
        }

        $site = Site::find($request->site_id);

        if (!$site || $site->agent_token !== $request->agent_token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'site_id' => $site->id,
            'backuppc_url' => $site->backuppc_url,
            'polling_interval' => $site->polling_interval,
            'backuppc_username' => $site->backuppc_username,
            'auth_type' => $site->api_key ? 'api_key' : 'basic',
            'dashboard_ws_url' => config('app.url') . '/reverb',
        ]);
    }

    /**
     * Store backup data in database
     */
    protected function storeBackupData(Site $site, array $data)
    {
        $backupDataModel = \App\Models\BackupData::class;

        // Clear old data for this site
        $backupDataModel::where('site_id', $site->id)->delete();

        // Store server data
        if (isset($data['server'])) {
            $backupDataModel::create([
                'site_id' => $site->id,
                'host_name' => 'server',
                'state' => 'server',
                'last_backup_time' => now(),
                'raw_data' => $data['server'],
            ]);
        }

        // Store host data
        if (isset($data['hosts']) && is_array($data['hosts'])) {
            foreach ($data['hosts'] as $hostName => $hostData) {
                $backupDataModel::create([
                    'site_id' => $site->id,
                    'host_name' => $hostName,
                    'state' => $hostData['state'] ?? 'unknown',
                    'last_backup_time' => isset($hostData['full_age']) && $hostData['full_age'] > 0
                        ? date('Y-m-d H:i:s', $hostData['full_age'])
                        : null,
                    'last_backup_size' => $hostData['full_size'] ?? null,
                    'full_backup_count' => $hostData['full_count'] ?? 0,
                    'incremental_backup_count' => $hostData['incr_count'] ?? 0,
                    'error_message' => $hostData['error'] ?? null,
                    'disabled' => $hostData['disabled'] ?? 0,
                    'raw_data' => $hostData,
                ]);
            }
        }

        // Store disk and pool data
        if (isset($data['disk'])) {
            $backupDataModel::create([
                'site_id' => $site->id,
                'host_name' => 'disk_usage',
                'state' => 'disk',
                'raw_data' => $data['disk'],
            ]);
        }

        if (isset($data['cpool'])) {
            $backupDataModel::create([
                'site_id' => $site->id,
                'host_name' => 'cpool',
                'state' => 'pool',
                'raw_data' => $data['cpool'],
            ]);
        }
    }
}
