<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ColdChain\TelemetryIngestionService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;
use Throwable;

/**
 * Bridges the ESP32 fleet into the database.
 *
 * The trucks publish to HiveMQ over MQTT, but everything ColdTrace calculates —
 * mean kinetic temperature, remaining shelf life, alerts, the monitoring map —
 * reads from `telemetry_logs`. Without this listener the readings reach the
 * broker and go no further.
 */
class ListenForTelemetry extends Command
{
    protected $signature = 'coldtrace:mqtt-listen
        {--once : Handle a single reading and exit, for testing a connection}
        {--timeout=0 : Stop after this many seconds, 0 to run until interrupted}';

    protected $description = 'Subscribe to the ESP32 telemetry topic and record every reading';

    /**
     * Readings the devices have already sent, keyed by device code. A retained
     * MQTT message is redelivered on every reconnect, so without this the same
     * reading would be stored again each time the bridge restarts.
     *
     * @var array<string, string>
     */
    private array $lastSeenReading = [];

    private int $recorded = 0;

    private int $skipped = 0;

    public function handle(TelemetryIngestionService $ingestion): int
    {
        $host = (string) config('coldtrace.mqtt.host');

        if ($host === '') {
            $this->components->error('HIVEMQ_HOST is not set, so there is no broker to listen to.');
            $this->line('  Add your HiveMQ cluster address to .env, for example:');
            $this->line('  HIVEMQ_HOST=9d73804b71954f829dd579d791128b63.s1.eu.hivemq.cloud');

            return self::FAILURE;
        }

        $port = (int) config('coldtrace.mqtt.port', 8883);
        $topic = (string) config('coldtrace.telemetry.subscription_topic');
        $qos = (int) config('coldtrace.mqtt.qos', 1);

        try {
            $client = new MqttClient(
                $host,
                $port,
                $this->clientId(),
                MqttClient::MQTT_3_1_1
            );

            $client->connect($this->connectionSettings(), true);
        } catch (MqttClientException $exception) {
            $this->components->error('Could not connect to the broker: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Connected to {$host}:{$port}");
        $this->components->twoColumnDetail('Subscribed topic', $topic);
        $this->components->twoColumnDetail('Devices accepted', (string) count(config('coldtrace.devices', [])));
        $this->newLine();
        $this->line('  Waiting for readings. Press Ctrl+C to stop.');
        $this->newLine();

        $client->subscribe(
            $topic,
            function (string $topic, string $message) use ($ingestion, $client): void {
                $this->handleMessage($ingestion, $topic, $message);

                if ($this->option('once')) {
                    $client->interrupt();
                }
            },
            $qos
        );

        $timeout = (int) $this->option('timeout');

        if ($timeout > 0) {
            // A deadline keeps supervised restarts predictable and lets the
            // test suite drive the loop without hanging.
            $client->registerLoopEventHandler(
                function (MqttClient $client, float $elapsed) use ($timeout): void {
                    if ($elapsed >= $timeout) {
                        $client->interrupt();
                    }
                }
            );
        }

        try {
            $client->loop(true);
        } catch (MqttClientException $exception) {
            $this->components->error('The broker connection dropped: '.$exception->getMessage());
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
                // The socket is already gone; nothing useful to report.
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail('Readings recorded', (string) $this->recorded);
        $this->components->twoColumnDetail('Readings skipped', (string) $this->skipped);

        return self::SUCCESS;
    }

    private function handleMessage(
        TelemetryIngestionService $ingestion,
        string $topic,
        string $message
    ): void {
        try {
            $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->reportSkip($topic, 'payload is not valid JSON');

            return;
        }

        if (! is_array($payload)) {
            $this->reportSkip($topic, 'payload is not a JSON object');

            return;
        }

        $deviceCode = (string) ($payload['device_code'] ?? '');

        if ($this->isRepeatOfLastReading($deviceCode, $message)) {
            $this->skipped++;
            $this->line("  <fg=gray>· {$deviceCode} retained message already recorded</>");

            return;
        }

        try {
            $telemetryLog = $ingestion->ingest($payload);
        } catch (ValidationException $exception) {
            $this->reportSkip(
                $topic,
                implode(' ', $exception->validator->errors()->all())
            );

            return;
        } catch (InvalidArgumentException $exception) {
            // Raised when the device is unknown, unpaired, or its truck has no
            // active trip. Expected during normal operation, so it is reported
            // plainly rather than as a crash.
            $this->reportSkip($topic, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->reportSkip($topic, $exception->getMessage());

            return;
        }

        $this->lastSeenReading[$deviceCode] = $message;
        $this->recorded++;

        $temperature = $telemetryLog->temperature === null
            ? 'no probe reading'
            : number_format((float) $telemetryLog->temperature, 2).' °C';

        $position = $telemetryLog->latitude === null || $telemetryLog->longitude === null
            ? 'no fix'
            : number_format((float) $telemetryLog->latitude, 5).', '
                .number_format((float) $telemetryLog->longitude, 5);

        $this->line(
            "  <fg=green>✓</> {$deviceCode}  "
            ."<fg=gray>trip</> {$telemetryLog->trip_id}  "
            ."{$temperature}  "
            ."<fg=gray>{$position}</>"
        );
    }

    /**
     * A retained message is redelivered whenever the bridge reconnects. The
     * device sends `uptime_ms`, so an identical payload is the same reading
     * rather than a genuinely new one.
     */
    private function isRepeatOfLastReading(string $deviceCode, string $message): bool
    {
        return $deviceCode !== ''
            && ($this->lastSeenReading[$deviceCode] ?? null) === $message;
    }

    private function reportSkip(string $topic, string $reason): void
    {
        $this->skipped++;
        $this->line("  <fg=yellow>!</> <fg=gray>{$topic}</> {$reason}");
    }

    private function connectionSettings(): ConnectionSettings
    {
        $settings = (new ConnectionSettings)
            ->setUsername(config('coldtrace.mqtt.username'))
            ->setPassword(config('coldtrace.mqtt.password'))
            ->setKeepAliveInterval((int) config('coldtrace.mqtt.keep_alive', 60))
            ->setUseTls((bool) config('coldtrace.mqtt.tls', true))
            ->setTlsVerifyPeer((bool) config('coldtrace.mqtt.verify_peer', true))
            ->setTlsVerifyPeerName((bool) config('coldtrace.mqtt.verify_peer', true));

        $caFile = config('coldtrace.mqtt.ca_file');

        if (is_string($caFile) && $caFile !== '') {
            $settings = $settings->setTlsCertificateAuthorityFile($caFile);
        }

        return $settings;
    }

    /**
     * Each connection needs its own client id, or the broker disconnects the
     * previous session holding that id.
     */
    private function clientId(): string
    {
        return (string) config('coldtrace.mqtt.client_id', 'coldtrace-laravel-bridge')
            .'-'.substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
