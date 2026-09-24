<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use App\Models\User;
use App\Services\Api\ApiTokenService;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * API v1: Idempotenz für schreibende Aufrufe (Header "Idempotency-Key", UUID, optional).
 *
 * - Ohne Header: unverändertes Verhalten.
 * - Gleicher Key + gleiche Anfrage: gespeicherte Antwort unverändert zurück (Header Idempotent-Replayed: true).
 * - Gleicher Key + andere Anfrage: 422.
 * - Gleicher Key, während die erste Anfrage noch läuft: 409 (Cache-Lock).
 * - Gespeichert werden nur 2xx-Antworten. Routen unter auth/* sind ausgenommen.
 */
class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(private ApiTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        // Läuft vor Route-Model-Binding und den Rechte-Middlewares (siehe Kernel::$middlewarePriority):
        // Nur Benutzer mit App-Zugang erhalten gespeicherte Antworten, alle anderen laufen normal
        // durch die Pipeline (und scheitern dort mit 403).
        $user = $request->user();
        if ($key === null || !in_array($request->method(), self::METHODS, true)
            || $request->is('api/v1/auth/*') || !$user instanceof User || !$this->tokens->hasAppAccess($user)) {
            return $next($request);
        }

        if (!Str::isUuid($key)) {
            return response()->json([
                'message' => 'Die übermittelten Daten sind ungültig.',
                'errors' => ['Idempotency-Key' => ['Der Idempotency-Key muss eine UUID sein.']],
            ], 422);
        }

        $userId = $user->getKey();
        $hash = $this->requestHash($request);

        if ($stored = $this->find($userId, $key)) {
            return $this->replay($stored, $request, $hash);
        }

        $lock = Cache::lock('api_idempotency:' . $userId . ':' . $key, 60);
        if (!$lock->get()) {
            return response()->json([
                'message' => 'Eine Anfrage mit diesem Idempotency-Key wird bereits verarbeitet.',
            ], 409);
        }

        try {
            // Die erste Anfrage kann zwischen Prüfung und Lock abgeschlossen worden sein
            if ($stored = $this->find($userId, $key)) {
                return $this->replay($stored, $request, $hash);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                $this->store($userId, $key, $request, $hash, $response);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function find(int $userId, string $key): ?ApiIdempotencyKey
    {
        return ApiIdempotencyKey::where('user_id', $userId)->where('key', $key)->first();
    }

    private function replay(ApiIdempotencyKey $stored, Request $request, string $hash): Response
    {
        if ($stored->request_hash !== $hash
            || $stored->method !== $request->method()
            || $stored->path !== $request->path()) {
            return response()->json([
                'message' => 'Idempotency-Key wurde mit anderen Daten verwendet.',
                'errors' => ['Idempotency-Key' => ['Idempotency-Key wurde mit anderen Daten verwendet.']],
            ], 422);
        }

        $headers = ['Idempotent-Replayed' => 'true'];
        if ($stored->response_body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return response($stored->response_body ?? '', $stored->response_status, $headers);
    }

    private function store(int $userId, string $key, Request $request, string $hash, Response $response): void
    {
        $body = $response->getContent();

        try {
            ApiIdempotencyKey::create([
                'user_id' => $userId,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => $hash,
                'response_status' => $response->getStatusCode(),
                'response_body' => $body === '' || $body === false ? null : $body,
            ]);
        } catch (QueryException $e) {
            // Unique-Verletzung (paralleler Request trotz Lock, z.B. bei Cache-Ausfall) – Antwort trotzdem ausliefern
            report($e);
        }
    }

    /** sha256 des Bodys (JSON kanonisiert, damit die Reihenfolge der Schlüssel keine Rolle spielt) */
    private function requestHash(Request $request): string
    {
        $payload = $request->isJson() ? $request->json()->all() : $request->request->all();

        return hash('sha256', json_encode($this->sortRecursive($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->sortRecursive($v);
            }
        }
        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }
}
