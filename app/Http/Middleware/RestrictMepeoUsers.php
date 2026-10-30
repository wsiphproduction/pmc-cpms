<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the MEPEO roles to the MEPEO page. They work EMS Survey Reports and
 * nothing else, so every other page — the dashboard included — sends them
 * back there, and anything that would change data elsewhere is refused.
 * Their own account, notifications, manual and signing out stay open to them.
 */
class RestrictMepeoUsers
{
    /** Route names, or prefixes ending in a dot, a MEPEO user may reach. */
    private const ALLOWED = [
        'mepeo.',
        'ems.',
        'notifications.',
        'manual.',
        'account.',
        'password.',
        'verification.',
        'tour.seen',
        'impersonate.leave',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isMepeo() || $this->allowed($request->route()?->getName())) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('mepeo.index');
        }

        abort(403);
    }

    private function allowed(?string $name): bool
    {
        // Unnamed routes are the public pages (About, the health check).
        if ($name === null) {
            return true;
        }

        foreach (self::ALLOWED as $allowed) {
            if ($name === $allowed || (str_ends_with($allowed, '.') && str_starts_with($name, $allowed))) {
                return true;
            }
        }

        return false;
    }
}
