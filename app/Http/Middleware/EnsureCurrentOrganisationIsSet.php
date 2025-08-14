<?php

namespace App\Http\Middleware;

use App\Actions\CurrentOrganisationHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentOrganisationIsSet
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Laravel automatically gets the {organisation} from the route
        $organisation = $request->route('organisation');

        if (! $organisation || ! CurrentOrganisationHelper::getInstance()->set($organisation->id)) {
            // If the organisation can't be set, redirect.
            // The controller method will never even be called.
            return Redirect::route('dashboard.view');
        }

        // If the check passes, continue to the controller.
        return $next($request);
    }
}
