<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

final class OpenAIRouteRecommendationService
{
    /** @return array<string, mixed> */
    public function recommend(array $tripData): array
    {
        $fallback = $this->fallback($tripData, 'OpenAI is unavailable.');
        $apiKey = config('services.openai.key');
        $model = config('services.openai.model');

        if (! is_string($apiKey) || trim($apiKey) === '' || ! is_string($model) || trim($model) === '') {
            Log::warning('OpenAI route recommendation skipped because its configuration is incomplete.');

            return $fallback;
        }

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(5)
                ->timeout(30)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => $model,
                    'input' => [
                        [
                            'role' => 'system',
                            'content' => 'You are ColdTrace AI, an assistant for cold-chain delivery route recommendation. Every order, telemetry, cargo-risk, and route-score field supplied to you was verified and computed by the server. Recommend only a route_id present in route_options. Do not recalculate or invent data.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode($tripData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT),
                        ],
                    ],
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'coldtrace_route_recommendation',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'recommended_route_id' => [
                                        'type' => 'string',
                                    ],
                                    'risk_level' => [
                                        'type' => 'string',
                                        'enum' => ['safe', 'warning', 'critical'],
                                    ],
                                    'driver_action' => [
                                        'type' => 'string',
                                    ],
                                    'reason' => [
                                        'type' => 'string',
                                    ],
                                    'cold_chain_warning' => [
                                        'type' => 'string',
                                    ],
                                    'confidence' => [
                                        'type' => 'number',
                                    ],
                                ],
                                'required' => [
                                    'recommended_route_id',
                                    'risk_level',
                                    'driver_action',
                                    'reason',
                                    'cold_chain_warning',
                                    'confidence',
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::error('OpenAI route recommendation failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->fallback(
                    $tripData,
                    "OpenAI returned HTTP {$response->status()}."
                );
            }

            $outputText = $this->extractOutputText($response->json());
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($decoded)) {
                throw new JsonException('OpenAI returned a non-object recommendation.');
            }

            return $this->validateRecommendation($decoded, $tripData);
        } catch (Throwable $exception) {
            Log::error('OpenAI route recommendation exception.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->fallback($tripData, $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function validateRecommendation(array $recommendation, array $tripData): array
    {
        $routes = collect($tripData['route_options'] ?? [])->keyBy('route_id');
        $routeId = $recommendation['recommended_route_id'] ?? null;
        $riskLevel = $recommendation['risk_level'] ?? null;
        $confidence = $recommendation['confidence'] ?? null;

        if (! is_string($routeId) || ! $routes->has($routeId)) {
            throw new JsonException('OpenAI recommended a route that was not provided by the server.');
        }

        if (! is_string($riskLevel) || ! in_array($riskLevel, ['safe', 'warning', 'critical'], true)) {
            throw new JsonException('OpenAI returned an invalid risk level.');
        }

        foreach (['driver_action', 'reason', 'cold_chain_warning'] as $field) {
            if (! isset($recommendation[$field]) || ! is_string($recommendation[$field]) || trim($recommendation[$field]) === '') {
                throw new JsonException("OpenAI returned an invalid {$field} value.");
            }
        }

        if (! is_numeric($confidence)) {
            throw new JsonException('OpenAI returned an invalid confidence value.');
        }

        $selectedRoute = $routes->get($routeId);

        return [
            'recommended_route_id' => $routeId,
            // Cold-chain risk is authoritative server data. The model must
            // provide the field for schema compliance but cannot override it.
            'risk_level' => $selectedRoute['risk_level'],
            'driver_action' => trim($recommendation['driver_action']),
            'reason' => trim($recommendation['reason']),
            'cold_chain_warning' => trim($recommendation['cold_chain_warning']),
            'confidence' => round(max(0, min(1, (float) $confidence)), 2),
            'route_score' => $selectedRoute['route_score'],
            'decision_source' => 'openai',
        ];
    }

    /** @return array<string, mixed> */
    private function fallback(array $tripData, string $reason): array
    {
        $routes = collect($tripData['route_options'] ?? [])->keyBy('route_id');
        $routeId = $tripData['lowest_score_route_id'] ?? $routes->keys()->first();
        $route = is_string($routeId) ? $routes->get($routeId) : null;

        return [
            'recommended_route_id' => $routeId,
            'risk_level' => $route['risk_level'] ?? 'warning',
            'driver_action' => $routeId
                ? "Use {$routeId}, which has the lowest server-computed ColdTrace score."
                : 'No verified route is available.',
            'reason' => 'The deterministic server recommendation was used because the AI explanation was unavailable.',
            'cold_chain_warning' => 'Continue monitoring cargo temperature and remaining shelf life.',
            'confidence' => 0.50,
            'route_score' => $route['route_score'] ?? null,
            'decision_source' => 'deterministic_fallback',
            'fallback_reason' => $reason,
        ];
    }

    private function extractOutputText(array $payload): string
    {
        foreach ($payload['output'] ?? [] as $outputItem) {
            foreach ($outputItem['content'] ?? [] as $contentItem) {
                if (($contentItem['type'] ?? null) === 'output_text') {
                    return (string) ($contentItem['text'] ?? '');
                }
            }
        }

        return (string) ($payload['output_text'] ?? '');
    }
}
