<?php defined('SYSPATH') or die('No direct script access.');

class Route extends Kohana_Route {

    /**
     * @var  array
     */
    protected $_defaults = array(
        'action' => 'index',
        'lang'   => 'ru-ru',
        'host'   => FALSE
    );

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
            // PSR-0: Replace slashes with spaces, run ucwords, then replace slashes
            $params['controller'] = str_replace(' ', '/', ucwords(str_replace('/', ' ', $params['controller'])));
        }

        if (!empty($params['directory']))
        {
            // PSR-0: Replace underscores with spaces, run ucwords, then replace underscore
            $params['directory'] = str_replace(' ', '_', ucwords(str_replace('_', ' ', $params['directory'])));
            // PSR-0: Replace slashes with spaces, run ucwords, then replace slashes
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
     * Provides default values for keys when they are not present. The default
     * action will always be "index" unless it is overloaded here.
     *
     *     $route->defaults(array(
     *         'controller' => 'welcome',
     *         'action'     => 'index'
     *     ));
     *
     * If no parameter is passed, this method will act as a getter.
     *
     * @param   array   $defaults   key values
     * @return  $this or array
     */
    public function defaults(array $defaults = NULL)
    {
        if ($defaults === NULL)
        {
            return $this->_defaults;
        }

        $this->_defaults = $defaults;

        return $this;
    }

    /**
     * Preroute to be run before route matching:
     *
     * @throws  Kohana_Exception
     * @param   string  $name           route name
     * @param   string  $uri            URI pattern
     * @param   array   $regex          regex patterns for route keys
     * @param   array   $callback   callback string, array, or closure
     * @return  $this
     */
    public static function preroute($name, $segment, $regex, $callback, array $defaults = NULL)
    {
        $preroute         = array();
        $preroute['name'] = $name;
        if (!empty($segment))
        {
            $preroute['segment'] = $segment;
        }

        if (!empty($regex))
        {
            $preroute['regex'] = $regex;
        }

        if (!is_callable($callback))
        {
            throw new Kohana_Exception('Invalid preroute::callback specified');
        }

        $preroute['callback'] = $callback;

        $preroute['defaults'] = $defaults;

        // Store the compiled regex locally
        $preroute['route_regex'] = Route::compile($segment, $regex);
        $preroute['route_regex'] = str_replace('$', '', $preroute['route_regex']);

        self::$_preroutes[$name] = $preroute;
    }

    /**
     * Preroute matching to be run before route matching:
     *
     * @throws  Kohana_Exception
     * @param   array   $callback   callback string, array, or closure
     * @return  $this
     */
    public static function preroute_exec($uri, &$params)
    {
        if (count(self::$_preroutes) == 0)
        {
            return $uri;
        }
        
        $original_uri = $uri;
        
        foreach (self::$_preroutes as $preroute)
        {
            if (!preg_match($preroute['route_regex'], $uri, $matches))
            {
                continue;
            }

            $params = $preroute['defaults'];

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

            call_user_func($preroute['callback'], $params);

            if (mb_strlen($uri) == mb_strlen($matches[0]))
            {
                $uri = '';
            }
            else
            {
                $uri = mb_substr($uri, mb_strlen($matches[0]));
            }
        }

        if ($uri != $original_uri && mb_substr($uri, 0, 1) != '/' ){
            return FALSE;
        }
        
        return $uri;
    }

    /**
     * @var  array  route filters
     */
    protected static $_preroutes = array();

    /**
     * Generates a URI for the current route based on the parameters given.
     *
     *     // Using the "default" route: "users/profile/10"
     *     $route->uri(array(
     *         'controller' => 'users',
     *         'action'     => 'profile',
     *         'id'         => '10'
     *     ));
     *
     * @param   array   $params URI parameters
     * @return  string
     * @throws  Kohana_Exception
     * @uses    Route::REGEX_GROUP
     * @uses    Route::REGEX_KEY
     */
    public function uri(array $params = NULL)
    {
        $uri = parent::uri($params);

        /**
         * Recursively compiles a portion of a URI specification by replacing
         * the specified parameters and any optional parameters that are needed.
         *
         * @param   string  $portion    Part of the URI specification
         * @param   boolean $required   Whether or not parameters are required (initially)
         * @return  array   Tuple of the compiled portion and whether or not it contained specified parameters
         */
        $compile = function ($portion, $required, $defaults) use (&$compile, $params) {
            $missing = array();

            $pattern = '#(?:' . Route::REGEX_KEY . '|' . Route::REGEX_GROUP . ')#';
            $result  = preg_replace_callback($pattern, function ($matches) use (&$compile, $defaults, &$missing, $params, &$required) {
                if ($matches[0][0] === '<')
                {
                    // Parameter, unwrapped
                    $param = $matches[1];

                    if (isset($params[$param]))
                    {
                        // This portion is required when a specified
                        // parameter does not match the default
                        $required = ($required OR !isset($defaults[$param]) OR $params[$param] !== $defaults[$param]);

                        // Add specified parameter to this result
                        return $params[$param];
                    }

                    // Add default parameter to this result
                    if (isset($defaults[$param]))
                        return $defaults[$param];

                    // This portion is missing a parameter
                    $missing[] = $param;
                }
                else
                {
                    // Group, unwrapped
                    $result = $compile($matches[2], FALSE, $defaults);

                    if ($result[1])
                    {
                        // This portion is required when it contains a group
                        // that is required
                        $required = TRUE;

                        // Add required groups to this result
                        return $result[0];
                    }

                    // Do not add optional groups to this result
                }
            }, $portion);

            if ($required AND $missing)
            {
                throw new Kohana_Exception(
                'Required route parameter not passed: :param', array(':param' => reset($missing))
                );
            }

            return array($result, $required);
        };

        $segments = array();
        foreach (self::$_preroutes as $preroute)
        {

            list($segment) = $compile($preroute['segment'], TRUE, $preroute['defaults']);
            
            $segments[] = $segment;
        }
        
        $segment = implode('', $segments);
        
        return $segment . $uri;
        
    }

}
