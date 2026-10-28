<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Road-network distances via an OSRM-compatible routing backend.
 *
 * Returns null when the backend is disabled or unreachable so callers can
 * fall back to straight-line distance — matching the frontend behaviour of
 * drawing a straight line when OSRM fails.
 */
class RoutingService
{
    /**
     * Max destinations per table request — keeps requests under the public
     * demo server's table-size limit and self-hosted defaults.
     */
    private const CHUNK_SIZE = 90;

    /**
     * Road distances/durations from one origin to many destinations.
     *
     * @param  array<int, array{lat: float, lng: float}>  $destinations
     * @return array<int, array{distance_km: float, duration_min: float|null}|null>|null
     *         One entry per destination (a null entry means that destination
     *         is unroutable), or null when the backend is unavailable.
     */
    public function distancesFrom(float $originLat, float $originLng, array $destinations): ?array
    {
        $destinations = array_values($destinations);
        if ($destinations === []) {
            return [];
        }
        if ($this->baseUrl() === '') {
            return null;
        }

        $results = [];
        foreach (array_chunk($destinations, self::CHUNK_SIZE) as $chunkIndex => $chunk) {
            $matrix = $this->fetchTable($originLat, $originLng, $chunk);
            if ($matrix === null) {
                return null;
            }
            foreach ($chunk as $i => $_) {
                $results[$chunkIndex * self::CHUNK_SIZE + $i] = $matrix[$i] ?? null;
            }
        }

        return $results;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $destinations
     * @return array<int, array{distance_km: float, duration_min: float|null}|null>|null
     */
    private function fetchTable(float $lat, float $lng, array $destinations): ?array
    {
        $coords = "{$lng},{$lat}";
        foreach ($destinations as $d) {
            $coords .= ";{$d['lng']},{$d['lat']}";
        }
        $destIndexes = implode(';', range(1, count($destinations)));

        $ttl = (int) config('services.osrm.cache_ttl', 300);
        $cacheKey = 'osrm_table:' . md5(config('services.osrm.profile', 'driving') . '|' . $coords);

        if ($ttl > 0 && ($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $table = $this->requestTable($coords, $destIndexes);
        if ($table !== null && $ttl > 0) {
            Cache::put($cacheKey, $table, $ttl);
        }

        return $table;
    }

    /**
     * @return array<int, array{distance_km: float, duration_min: float|null}|null>|null
     */
    private function requestTable(string $coords, string $destIndexes): ?array
    {
        $profile = config('services.osrm.profile', 'driving');

        try {
            $response = Http::timeout((float) config('services.osrm.timeout', 4))
                ->get("{$this->baseUrl()}/table/v1/{$profile}/{$coords}", [
                    'sources' => '0',
                    'destinations' => $destIndexes,
                    'annotations' => 'distance,duration',
                    'skip_waypoints' => 'true',
                ]);
        } catch (\Throwable $e) {
            Log::warning('RoutingService: request failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (! $response->ok() || $response->json('code') !== 'Ok') {
            Log::warning('RoutingService: bad response', [
                'status' => $response->status(),
                'code' => $response->json('code'),
            ]);
            return null;
        }

        $distances = $response->json('distances.0') ?? [];
        $durations = $response->json('durations.0') ?? [];

        return collect($distances)->map(fn ($meters, $i) => $meters === null ? null : [
            'distance_km' => $meters / 1000,
            'duration_min' => isset($durations[$i]) && $durations[$i] !== null ? $durations[$i] / 60 : null,
        ])->all();
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.osrm.base_url', ''), '/');
    }
}
