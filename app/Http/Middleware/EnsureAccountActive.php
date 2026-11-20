<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    /**
     * Banned accounts can still read their profile, log out, see notifications
     * and submit the one appeal the code of conduct allows.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            $allowed = $request->routeIs([
                'auth.me',
                'auth.logout',
                'appeals.store',
                'appeals.index',
                'moderation.mine',
                'notifications.index',
                'notifications.read',
            ]);

            if (! $allowed) {
                abort(403, 'This account is banned. You may submit one appeal.');
            }
        }

        return $next($request);
    }
}
