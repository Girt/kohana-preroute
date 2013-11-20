<?php defined('SYSPATH') or die('No direct script access.');

class Request extends Kohana_Request {

    /**
     * Process a request to find a matching route
     *
     * @param   object  $request Request
     * @param   array   $routes  Route
     * @return  array
     */
    public static function process(Request $request, $routes = NULL)
    {
        // Get the URI from the Request
        $uri = trim($request->uri(), '/');

        $params = NULL;
        $uri = Route::preroute_exec($uri, $params);

        $request->uri($uri);

        // Load routes
        $routes = (empty($routes)) ? Route::all() : $routes;

        foreach ($routes as $name => $route)
        {
            // We found something suitable
            if ($return = $route->matches($request))
            {
                $params = Arr::merge($params, $return);
                return array(
                    'params' => $params,
                    'route' => $route,
                );
            }
        }

        return NULL;
    }

}

// End Request