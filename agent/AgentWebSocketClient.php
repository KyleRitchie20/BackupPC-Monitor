<?php
/**
 * Agent WebSocket Client (Command Polling)
 *
 * This handles receiving commands from the dashboard.
 * Uses long-polling to simulate real-time command reception.
 */

declare(strict_types=1);

class AgentWebSocketClient
{
    private string $dashboardUrl;
    private int $siteId;
    private string $agentToken;
    private $logger;
    private int $pollInterval = 30; // seconds
    private bool $running = true;
    private int $commandCount = 0;

    public function __construct(array $config, $logger)
    {
        $this->dashboardUrl = rtrim($config['dashboard_url'], '/');
        $this->siteId = $config['site_id'];
        $this->agentToken = $config['agent_token'];
        $this->logger = $logger;

        if (isset($config['ws_poll_interval'])) {
            $this->pollInterval = (int)$config['ws_poll_interval'];
        }
    }

    /**
     * Start listening for commands
     */
    public function start(callable $commandHandler): void
    {
        $this->logger->info("Starting command listener", ['poll_interval' => $this->pollInterval]);

        while ($this->running) {
            $command = $this->fetchCommand();

            if ($command !== null) {
                $this->commandCount++;
                $this->logger->info("Received command: " . ($command['command'] ?? 'unknown'));

                // Execute command handler
                $result = $commandHandler($command);

                // Acknowledge command
                $this->acknowledgeCommand($command);
            }

            // Wait before next poll
            $this->sleep($this->pollInterval);
        }

        $this->logger->info("Command listener stopped", ['total_commands' => $this->commandCount]);
    }

    /**
     * Stop the listener
     */
    public function stop(): void
    {
        $this->running = false;
        $this->logger->info("Stopping command listener...");
    }

    /**
     * Fetch pending command from dashboard
     */
    private function fetchCommand(): ?array
    {
        $url = "{$this->dashboardUrl}/api/agent/command/poll";
        $data = [
            'site_id' => $this->siteId,
            'agent_token' => $this->agentToken,
            'last_command_at' => null, // Could track last command time
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 35, // Slightly longer than poll interval
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->debug("Command poll error: {$error}");
            return null;
        }

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($result['command'])) {
                $this->logger->debug("Polled command: {$result['command']}");
                return $result;
            }
        }

        return null;
    }

    /**
     * Acknowledge command was received and processed
     */
    private function acknowledgeCommand(array $command): void
    {
        $url = "{$this->dashboardUrl}/api/agent/command/ack";
        $data = [
            'site_id' => $this->siteId,
            'agent_token' => $this->agentToken,
            'command_id' => $command['command_id'] ?? null,
            'status' => 'executed',
            'result' => 'success',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Sleep with interrupt support
     */
    private function sleep(int $seconds): void
    {
        $checkInterval = 1; // Check every second
        $elapsed = 0;

        while ($elapsed < $seconds && $this->running) {
            usleep(1000000); // 1 second
            $elapsed++;
        }
    }

    /**
     * Get command count
     */
    public function getCommandCount(): int
    {
        return $this->commandCount;
    }
}
