<?php

namespace Tests\Feature;

use Tests\TestCase;

class TelemetryListenerConfigurationTest extends TestCase
{
    public function test_the_listener_explains_itself_when_no_broker_is_set(): void
    {
        config()->set('coldtrace.mqtt.host', null);

        $this->artisan('coldtrace:mqtt-listen')
            ->expectsOutputToContain('HIVEMQ_HOST is not set')
            ->assertExitCode(1);
    }
}
