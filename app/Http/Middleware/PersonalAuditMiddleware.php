<?php

namespace App\Http\Middleware;

use App\Models\personal\PersonalAccessLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonalAuditMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);

        // Nur erfolgreiche GET-Requests loggen (kein 4xx/5xx)
        if ($request->isMethod('GET') && $response->getStatusCode() < 400) {
            $this->log($request);
        }

        return $response;
    }

    private function log(Request $request): void
    {
        if (!auth()->check()) return;

        [$resourceType, $resourceId] = $this->extractResource($request);

        PersonalAccessLog::create([
            'user_id'       => auth()->id(),
            'action'        => 'view',
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'route'         => $request->route()?->getName() ?? $request->path(),
            'ip_address'    => $request->ip(),
            'metadata'      => $this->extractMetadata($request),
        ]);
    }

    private function extractResource(Request $request): array
    {
        $route = $request->route();
        if (!$route) return ['unknown', null];

        foreach (['employe', 'user', 'document', 'review', 'bem_case', 'employment'] as $param) {
            if ($value = $route->parameter($param)) {
                $model = is_object($value) ? get_class($value) : 'App\\Models\\User';
                $id    = is_object($value) ? $value->id : (int)$value;
                return [$model, $id];
            }
        }

        return ['route', null];
    }

    private function extractMetadata(Request $request): ?array
    {
        $meta = [];
        foreach (['export', 'format', 'year', 'month'] as $param) {
            if ($request->has($param)) {
                $meta[$param] = $request->input($param);
            }
        }
        return empty($meta) ? null : $meta;
    }
}

