<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InjectLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Context::add([
            'request_id' => substr((string) Str::ulid(), -10),
            'ip'         => $request->ip(),
            'path'       => $request->path(),
        ]);

        if ($user = $request->user()) {
            Context::add([
                'user_id'   => $user->id,
                'user_name' => $user->name,
            ]);
        }

        if (function_exists('tenancy') && tenancy()->initialized && tenancy()->tenant) {
            Context::add('tenant_id', tenancy()->tenant->getTenantKey());
        }

        return $next($request);
    }
}