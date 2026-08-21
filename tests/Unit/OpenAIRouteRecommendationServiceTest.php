<?php

namespace Tests\Unit;

use App\Services\OpenAIRouteRecommendationService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIRouteRecommendationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.key', 'test-key');
        config()->set('services.openai.model', 'test-model');
    }

    public function test_it_accepts_only_a_verified_route_and_clamps_confidence(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiPayload([
                'recommended_route_id' => 'route_1',
                'risk_level' => 'safe',
                'driver_action' => 'Take route one.',
                'reason' => 'It best protects the shipment.',
                'cold_chain_warning' => 'Monitor the cargo.',
                'confidence' => 4.5,
            ])),
        ]);

        $result = (new OpenAIRouteRecommendationService)->recommend($this->serverPayload());

        $this->assertSame('route_1', $result['recommended_route_id']);
        $this->assertSame('critical', $result['risk_level']);
        $this->assertSame(1.0, $result['confidence']);
        $this->assertSame('openai', $result['decision_source']);
        $this->assertSame(0.8, $result['route_score']);
    }

    public function test_it_rejects_an_unknown_ai_route_and_uses_the_server_fallback(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiPayload([
                'recommended_route_id' => 'invented_route',
                'risk_level' => 'safe',
                'driver_action' => 'Take the invented route.',
                'reason' => 'Invented reason.',
                'cold_chain_warning' => 'Invented warning.',
                'confidence' => 0.9,
            ])),
        ]);

        $result = (new OpenAIRouteRecommendationService)->recommend($this->serverPayload());

        $this->assertSame('route_2', $result['recommended_route_id']);
        $this->assertSame('deterministic_fallback', $result['decision_source']);
        $this->assertSame(0.4, $result['route_score']);
    }

    public function test_it_handles_connection_exceptions_with_the_server_fallback(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $result = (new OpenAIRouteRecommendationService)->recommend($this->serverPayload());

        $this->assertSame('route_2', $result['recommended_route_id']);
        $this->assertSame('deterministic_fallback', $result['decision_source']);
        $this->assertStringContainsString('timed out', $result['fallback_reason']);
    }

    /** @return array<string, mixed> */
    private function serverPayload(): array
    {
        return [
            'route_options' => [
                [
                    'route_id' => 'route_2',
                    'route_score' => 0.4,
                    'risk_level' => 'safe',
                ],
                [
                    'route_id' => 'route_1',
                    'route_score' => 0.8,
                    'risk_level' => 'critical',
                ],
            ],
            'lowest_score_route_id' => 'route_2',
        ];
    }

    /** @return array<string, mixed> */
    private function openAiPayload(array $recommendation): array
    {
        return [
            'output' => [[
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($recommendation, JSON_THROW_ON_ERROR),
                ]],
            ]],
        ];
    }
}
