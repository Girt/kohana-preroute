<?php defined('SYSPATH') or die('No direct script access.');

class Route extends Kohana_Route {


    /**
     * Tests if the route matches a given URI. A successful match will return
     * all of the routed parameters as an array. A failed match will return
     * boolean FALSE.
     *
     *     // Params: controller = users, action = edit, id = 10
     *     $params = $route->matches('users/edit/10');
     *
     * This method should almost always be used within an if/else block:
     *
     *     if ($params = $route->matches($uri))
     *     {
     *         // Parse the parameters
     *     }
     *
     * @param   string  $uri    URI to match
     * @return  array   on success
     * @return  FALSE   on failure
     */
    public function matches(Request $request)
    {
        // Get the URI from the Request
        $uri = trim($request->uri(), '/');

        if (!preg_match($this->_route_regex, $uri, $matches))
            return FALSE;

        $params = array();
        foreach ($matches as $key => $value)
        {
            if (is_int($key))
            {
                // Skip all unnamed keys
                continue;
            }

            // Set the value for all matched keys
            $params[$key] = $value;
        }

        foreach ($this->_defaults as $key => $value)
        {
            if (!isset($params[$key]) OR $params[$key] === '')
            {
                // Set default values for any key that was not matched
                $params[$key] = $value;
            }
        }

        if (!empty($params['controller']))
        {
            // PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
            $params['controller'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['controller'])));
            $params['controller'] = str_replace(' ', '/', ucwords(str_replace('/', ' ', $params['controller'])));
        }

        if (!empty($params['directory']))
        {
            // PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
            $params['directory'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['directory'])));
            $params['directory'] = str_replace(' ', '/', ucwords(str_replace('/', ' ', $params['directory'])));
        }

        if ($this->_filters)
        {
            foreach ($this->_filters as $callback)
            {
                // Execute the filter giving it the route, params, and request
                $return = call_user_func($callback, $this, $params, $request);

                if ($return === FALSE)
                {
                    // Filter has aborted the match
                    return FALSE;
                }
                elseif (is_array($return))
                {
                    // Filter has modified the parameters
                    $params = $return;
                }
            }
        }

        return $params;
    }

    /**
     * Preroute function:
     *
     * @throws  Kohana_Exception
     * @param   array   $segment   URI-segment pattern for processing with preroute
     * @param   array   $regex   regex patterns for preroute keys
     * @param   array   $callback   callback string, array, or closure
     * @param   array   $defaults   default values for keys when they are not present
     * @return  $this
     */
    public static function preroute($segment, $regex, $callback, array $defaults = NULL)
    {
        if (!empty($segment))
        {
            self::$_preroute['segment'] = $segment;
        }

        if (!empty($regex))
        {
            self::$_preroute['regex'] = $regex;
        }

        if (!is_callable($callback))
        {
            throw new Kohana_Exception('Invalid preroute::callback specified');
        }

        self::$_preroute['callback'] = $callback;

        self::$_preroute['default'] = $defaults;

        // Store the compiled regex locally
        self::$_preroute['route_regex'] = Route::compile($segment, $regex);
        self::$_preroute['route_regex'] = str_replace('$', '', self::$_preroute['route_regex']);
    }

    /**
     * Preroute processing function
     *
     * @throws  Kohana_Exception
	   * @param   array   $uri     URI pattern
	   * @param   array   $params     URI parameters
     * @return  $uri
     */
    public static function preroute_exec($uri, &$params)
    {
        if (empty(self::$_preroute))
        {
            return $uri;
        }
        $params = self::$_preroute['default'];
        if (!preg_match(self::$_preroute['route_regex'], $uri, $matches))
            return $uri;

        foreach ($matches as $key => $value)
        {
            if (is_int($key))
            {
                // Skip all unnamed keys
                continue;
            }

            // Set the value for all matched keys
            $params[$key] = $value;
        }

        call_user_func(self::$_preroute['callback'], $params);

        if (mb_strlen($uri) == mb_strlen($matches[0]))
        {
            $uri = '';
        }
        else
        {
            $uri = mb_substr($uri, mb_strlen($matches[0]));
        }

        return $uri;
    }

    /**
     * @var  array  route filters
     */
    protected static $_preroute = array();

}