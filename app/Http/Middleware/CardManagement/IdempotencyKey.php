<?php

namespace App\Http\Middleware\CardManagement;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 3: Idempotency-Key middleware (Target §4.1).
 *
 * Prevents double-billing on network retries. If a request with the same
 * Idempotency-Key has already been processed, return the cached response
 * instead of re-executing the operation.
 *
 * Strategy:
 * 1. Fast path — Redis (Cache facade), sub-millisecond lookup.
 * 2. Durable path — `idempotency_keys` table.
 * 3. Atomic reservation — the (key, workstation, path) unique index is
 *    claimed with insertOrIgnore BEFORE the request executes, so two
 *    concurrent requests with the same key can never both run.
 * 4. Payload guard — a reused key must carry an identical request hash,
 *    otherwise the reuse is rejected instead of silently replaying.
 * 5. Non-2xx responses release the reservation so the client may fix the
 *    request and retry; the reservation also releases if the handler throws.
 */
final class IdempotencyKey
{
    private const CACHE_TTL_HOURS = 24;
    private const CACHE_PREFIX = 'idem:';

    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to mutating requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if (!$key) {
            return $next($request);
        }

        $workstationId = $request->attributes->get('cm_workstation_id', 'validator');
        $path = $request->path();
        $cacheKey = self::CACHE_PREFIX . "{$workstationId}:{$path}:{$key}";
        $requestHash = hash('sha256', $request->method() . '|' . $path . '|' . $request->getContent());

        // 1. Fast path — cached response in Redis
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            $mismatch = $this->hashMismatchResponse($cached['requestHash'] ?? null, $requestHash);
            return $mismatch ?? $this->replayResponse($cached);
        }

        // 2. Atomic reservation — the unique index serializes concurrent
        //    requests; losers fall through and see the winner's reservation.
        $reserved = DB::table('idempotency_keys')->insertOrIgnore([
            'idempotency_key' => $key,
            'workstation_id' => $workstationId,
            'path' => $path,
            'request_hash' => $requestHash,
            'response_payload' => 'null',
            'response_status' => 0,
            'created_at' => now(),
        ]);

        if ($reserved === 0) {
            $existing = DB::table('idempotency_keys')
                ->where('idempotency_key', $key)
                ->where('workstation_id', $workstationId)
                ->where('path', $path)
                ->first();

            return $this->respondToExisting($existing, $cacheKey, $requestHash);
        }

        // 3. We hold the reservation — execute the request.
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->releaseReservation($key, $workstationId, $path);
            throw $e;
        }

        // 4. Cache only successful responses; failures release the key so
        //    the client may correct the payload and retry.
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->storeResponse($cacheKey, $key, $workstationId, $path, $requestHash, $response);
        } else {
            $this->releaseReservation($key, $workstationId, $path);
        }

        return $response;
    }

    private function respondToExisting(?object $existing, string $cacheKey, string $requestHash): Response
    {
        if (!$existing) {
            // Reserved between our checks but already gone — treat as still
            // in flight rather than executing a duplicate operation.
            return $this->conflict('IDEMPOTENCY_IN_PROGRESS', 'A request with this Idempotency-Key is still being processed.');
        }

        if ($existing->request_hash !== null && $existing->request_hash !== $requestHash) {
            return $this->conflict('IDEMPOTENCY_KEY_MISMATCH', 'Idempotency-Key was already used with a different request payload.');
        }

        if ((int) $existing->response_status === 0) {
            return $this->conflict('IDEMPOTENCY_IN_PROGRESS', 'A request with this Idempotency-Key is still being processed.');
        }

        $data = [
            'body' => json_decode($existing->response_payload, true),
            'status' => $existing->response_status,
            'requestHash' => $existing->request_hash,
        ];
        $this->populateCache($cacheKey, $data);

        return $this->replayResponse($data);
    }

    private function hashMismatchResponse(?string $cachedHash, string $requestHash): ?Response
    {
        if ($cachedHash !== null && $cachedHash !== $requestHash) {
            return $this->conflict('IDEMPOTENCY_KEY_MISMATCH', 'Idempotency-Key was already used with a different request payload.');
        }
        return null;
    }

    private function conflict(string $code, string $message): Response
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], 409);
    }

    private function releaseReservation(string $key, string $workstationId, string $path): void
    {
        DB::table('idempotency_keys')
            ->where('idempotency_key', $key)
            ->where('workstation_id', $workstationId)
            ->where('path', $path)
            ->where('response_status', 0)
            ->delete();
    }

    private function getFromCache(string $cacheKey): ?array
    {
        try {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $_) {
            // Redis unavailable — fall through to DB
        }
        return null;
    }

    private function populateCache(string $cacheKey, array $data): void
    {
        try {
            Cache::put($cacheKey, json_encode($data), now()->addHours(self::CACHE_TTL_HOURS));
        } catch (\Exception $_) {
            // Redis unavailable — skip cache population
        }
    }

    private function storeResponse(
        string $cacheKey,
        string $key,
        string $workstationId,
        string $path,
        string $requestHash,
        Response $response,
    ): void {
        $body = $response->getContent();
        $data = [
            'body' => json_decode($body, true),
            'status' => $response->getStatusCode(),
            'requestHash' => $requestHash,
        ];

        // Store in Redis (fast path for future retries)
        try {
            Cache::put($cacheKey, json_encode($data), now()->addHours(self::CACHE_TTL_HOURS));
        } catch (\Exception $_) {
            // Redis unavailable — DB is the durable fallback
        }

        // Fill in the reservation row created before execution
        DB::table('idempotency_keys')
            ->where('idempotency_key', $key)
            ->where('workstation_id', $workstationId)
            ->where('path', $path)
            ->update([
                'request_hash' => $requestHash,
                'response_payload' => $body,
                'response_status' => $response->getStatusCode(),
            ]);
    }

    private function replayResponse(array $data): Response
    {
        return response()->json($data['body'], $data['status'], [
            'Content-Type' => 'application/json',
            'X-Idempotent-Replay' => 'true',
        ]);
    }
}
