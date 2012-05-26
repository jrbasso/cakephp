<?php
/**
 * Parses the request URL into controller, action, and parameters.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Routing
 * @since         CakePHP(tm) v 0.2.9
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace Cake\Routing;

use Cake\Core\Configure;
use Cake\Network\Request;
use Cake\Network\Response;
use Cake\Routing\Route\Route;
use Cake\Routing\RouteCollection;
use Cake\Utility\Hash;
use Cake\Utility\Inflector;
use Cake\Error;

/**
 * Parses the request URL into controller, action, and parameters.  Uses the connected routes
 * to match the incoming url string to parameters that will allow the request to be dispatched.  Also
 * handles converting parameter lists into url strings, using the connected routes.  Routing allows you to decouple
 * the way the world interacts with your application (urls) and the implementation (controllers and actions).
 *
 * ### Connecting routes
 *
 * Connecting routes is done using Router::connect().  When parsing incoming requests or reverse matching
 * parameters, routes are enumerated in the order they were connected.  You can modify the order of connected
 * routes using Router::promote().  For more information on routes and how to connect them see Router::connect().
 *
 * @package       Cake.Routing
 */
class Router {

/**
 * RouteCollection object containing all the connected routes.
 *
 * @var Cake\Routing\RouteCollection
 */
	public static $_routes;

/**
 * List of action prefixes used in connected routes.
 * Includes admin prefix
 *
 * @var array
 */
	protected static $_prefixes = array();

/**
 * Directive for Router to parse out file extensions for mapping to Content-types.
 *
 * @var boolean
 */
	protected static $_parseExtensions = false;

/**
 * List of valid extensions to parse from a URL.  If null, any extension is allowed.
 *
 * @var array
 */
	protected static $_validExtensions = array();

/**
 * 'Constant' regular expression definitions for named route elements
 *
 */
	const ACTION = 'index|show|add|create|edit|update|remove|del|delete|view|item';
	const YEAR = '[12][0-9]{3}';
	const MONTH = '0[1-9]|1[012]';
	const DAY = '0[1-9]|[12][0-9]|3[01]';
	const ID = '[0-9]+';
	const UUID = '[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}';

/**
 * Named expressions
 *
 * @var array
 */
	protected static $_namedExpressions = array(
		'Action' => Router::ACTION,
		'Year' => Router::YEAR,
		'Month' => Router::MONTH,
		'Day' => Router::DAY,
		'ID' => Router::ID,
		'UUID' => Router::UUID
	);

/**
 * The route matching the URL of the current request
 *
 * @var array
 */
	protected static $_currentRoute = array();

/**
 * Default HTTP request method => controller action map.
 *
 * @var array
 */
	protected static $_resourceMap = array(
		array('action' => 'index',	'method' => 'GET',		'id' => false),
		array('action' => 'view',	'method' => 'GET',		'id' => true),
		array('action' => 'add',	'method' => 'POST',		'id' => false),
		array('action' => 'edit',	'method' => 'PUT', 		'id' => true),
		array('action' => 'delete',	'method' => 'DELETE',	'id' => true),
		array('action' => 'edit',	'method' => 'POST', 	'id' => true)
	);

/**
 * List of resource-mapped controllers
 *
 * @var array
 */
	protected static $_resourceMapped = array();

/**
 * Maintains the request object stack for the current request.
 * This will contain more than one request object when requestAction is used.
 *
 * @var array
 */
	protected static $_requests = array();

/**
 * Initial state is populated the first time reload() is called which is at the bottom
 * of this file.  This is a cheat as get_class_vars() returns the value of static vars even if they
 * have changed.
 *
 * @var array
 */
	protected static $_initialState = array();

/**
 * Default route class to use
 *
 * @var string
 */
	protected static $_routeClass = 'Cake\Routing\Route\Route';

/**
 * Set the default route class to use or return the current one
 *
 * @param string $routeClass to set as default
 * @return mixed void|string
 * @throws RouterException
 */
	public static function defaultRouteClass($routeClass = null) {
		if (is_null($routeClass)) {
			return self::$_routeClass;
		}
		self::$_routeClass = self::_validateRouteClass($routeClass);
	}

/**
 * Validates that the passed route class exists and is a subclass of Cake Route
 *
 * @param $routeClass
 * @return string
 * @throws RouterException
 */
	protected static function _validateRouteClass($routeClass) {
		if (!class_exists($routeClass) || !is_subclass_of($routeClass, 'Cake\Routing\Route\Route')) {
			throw new Error\RouterException(__d('cake_dev', 'Route classes must extend Cake\Routing\Route\Route'));
		}
		return $routeClass;
	}

/**
 * Sets the Routing prefixes.
 *
 * @return void
 */
	protected static function _setPrefixes() {
		$routing = Configure::read('Routing');
		if (!empty($routing['prefixes'])) {
			self::$_prefixes = array_merge(self::$_prefixes, (array)$routing['prefixes']);
		}
	}

/**
 * Gets the named route elements for use in app/Config/routes.php
 *
 * @return array Named route elements
 * @see Router::$_namedExpressions
 */
	public static function getNamedExpressions() {
		return self::$_namedExpressions;
	}

/**
 * Resource map getter & setter.
 *
 * @param array $resourceMap Resource map
 * @return mixed
 * @see Router::$_resourceMap
 */
	public static function resourceMap($resourceMap = null) {
		if ($resourceMap === null) {
			return self::$_resourceMap;
		}
		self::$_resourceMap = $resourceMap;
	}

/**
 * Connects a new Route in the router.
 *
 * Routes are a way of connecting request urls to objects in your application.  At their core routes
 * are a set or regular expressions that are used to match requests to destinations.
 *
 * Examples:
 *
 * `Router::connect('/:controller/:action/*');`
 *
 * The first parameter will be used as a controller name while the second is used as the action name.
 * the '/*' syntax makes this route greedy in that it will match requests like `/posts/index` as well as requests
 * like `/posts/edit/1/foo/bar`.
 *
 * `Router::connect('/home-page', array('controller' => 'pages', 'action' => 'display', 'home'));`
 *
 * The above shows the use of route parameter defaults. And providing routing parameters for a static route.
 *
 * {{{
 * Router::connect(
 *   '/:lang/:controller/:action/:id',
 *   array(),
 *   array('id' => '[0-9]+', 'lang' => '[a-z]{3}')
 * );
 * }}}
 *
 * Shows connecting a route with custom route parameters as well as providing patterns for those parameters.
 * Patterns for routing parameters do not need capturing groups, as one will be added for each route params.
 *
 * $options offers several 'special' keys that have special meaning in the $options array.
 *
 * - `pass` is used to define which of the routed parameters should be shifted into the pass array.  Adding a
 *   parameter to pass will remove it from the regular route array. Ex. `'pass' => array('slug')`
 * - `persist` is used to define which route parameters should be automatically included when generating
 *   new urls. You can override persistent parameters by redefining them in a url or remove them by
 *   setting the parameter to `false`.  Ex. `'persist' => array('lang')`
 * - `routeClass` is used to extend and change how individual routes parse requests and handle reverse routing,
 *   via a custom routing class. Ex. `'routeClass' => 'SlugRoute'`
 * - `_name` Used to define a specific name for routes.  This can be used to optimize reverse routing lookups.
 *   If undefined a name will be generated for each connected route.
 *
 * @param string $route A string describing the template of the route
 * @param array $defaults An array describing the default route parameters. These parameters will be used by default
 *   and can supply routing parameters that are not dynamic. See above.
 * @param array $options An array matching the named elements in the route to regular expressions which that
 *   element should match.  Also contains additional parameters such as which routed parameters should be
 *   shifted into the passed arguments, supplying patterns for routing parameters and supplying the name of a
 *   custom routing class.
 * @see routes
 * @return void
 * @throws RouterException
 */
	public static function connect($route, $defaults = array(), $options = array()) {
		foreach (self::$_prefixes as $prefix) {
			if (isset($defaults[$prefix])) {
				if ($defaults[$prefix]) {
					$defaults['prefix'] = $prefix;
				} else {
					unset($defaults[$prefix]);
				}
				break;
			}
		}
		if (isset($defaults['prefix'])) {
			self::$_prefixes[] = $defaults['prefix'];
			self::$_prefixes = array_keys(array_flip(self::$_prefixes));
		}
		$defaults += array('plugin' => null);
		if (empty($options['action'])) {
			$defaults += array('action' => 'index');
		}
		$routeClass = self::$_routeClass;
		if (isset($options['routeClass'])) {
			$routeClass = self::_validateRouteClass($options['routeClass']);
			unset($options['routeClass']);
		}
		if ($routeClass === 'Cake\Routing\Route\RedirectRoute' && isset($defaults['redirect'])) {
			$defaults = $defaults['redirect'];
		}
		self::$_routes->add(new $routeClass($route, $defaults, $options));
	}

/**
 * Connects a new redirection Route in the router.
 *
 * Redirection routes are different from normal routes as they perform an actual
 * header redirection if a match is found. The redirection can occur within your
 * application or redirect to an outside location.
 *
 * Examples:
 *
 * `Router::redirect('/home/*', array('controller' => 'posts', 'action' => 'view', array('persist' => true)));`
 *
 * Redirects /home/* to /posts/view and passes the parameters to /posts/view.  Using an array as the
 * redirect destination allows you to use other routes to define where a url string should be redirected to.
 *
 * `Router::redirect('/posts/*', 'http://google.com', array('status' => 302));`
 *
 * Redirects /posts/* to http://google.com with a HTTP status of 302
 *
 * ### Options:
 *
 * - `status` Sets the HTTP status (default 301)
 * - `persist` Passes the params to the redirected route, if it can.  This is useful with greedy routes,
 *   routes that end in `*` are greedy.  As you can remap urls and not loose any passed args.
 *
 * @param string $route A string describing the template of the route
 * @param array $url A url to redirect to. Can be a string or a Cake array-based url
 * @param array $options An array matching the named elements in the route to regular expressions which that
 *   element should match.  Also contains additional parameters such as which routed parameters should be
 *   shifted into the passed arguments. As well as supplying patterns for routing parameters.
 * @see routes
 * @return array Array of routes
 */
	public static function redirect($route, $url, $options = array()) {
		$options['routeClass'] = 'Cake\Routing\Route\RedirectRoute';
		if (is_string($url)) {
			$url = array('redirect' => $url);
		}
		return self::connect($route, $url, $options);
	}

/**
 * Creates REST resource routes for the given controller(s).  When creating resource routes
 * for a plugin, by default the prefix will be changed to the lower_underscore version of the plugin
 * name.  By providing a prefix you can override this behavior.
 *
 * ### Options:
 *
 * - 'id' - The regular expression fragment to use when matching IDs.  By default, matches
 *    integer values and UUIDs.
 * - 'prefix' - URL prefix to use for the generated routes.  Defaults to '/'.
 *
 * @param string|array $controller A controller name or array of controller names (i.e. "Posts" or "ListItems")
 * @param array $options Options to use when generating REST routes
 * @return array Array of mapped resources
 */
	public static function mapResources($controller, $options = array()) {
		$hasPrefix = isset($options['prefix']);
		$options = array_merge(array(
			'prefix' => '/',
			'id' => self::ID . '|' . self::UUID
		), $options);

		$prefix = $options['prefix'];

		foreach ((array)$controller as $name) {
			list($plugin, $name) = pluginSplit($name);
			$urlName = Inflector::underscore($name);
			$plugin = Inflector::underscore($plugin);
			if ($plugin && !$hasPrefix) {
				$prefix = '/' . $plugin . '/';
			}

			foreach (self::$_resourceMap as $params) {
				$url = $prefix . $urlName . (($params['id']) ? '/:id' : '');

				Router::connect($url,
					array(
						'plugin' => $plugin,
						'controller' => $urlName,
						'action' => $params['action'],
						'[method]' => $params['method']
					),
					array('id' => $options['id'], 'pass' => array('id'))
				);
			}
			self::$_resourceMapped[] = $urlName;
		}
		return self::$_resourceMapped;
	}

/**
 * Returns the list of prefixes used in connected routes
 *
 * @return array A list of prefixes used in connected routes
 */
	public static function prefixes() {
		return self::$_prefixes;
	}

/**
 * Parses given URL string.  Returns 'routing' parameters for that url.
 *
 * @param string $url URL to be parsed
 * @return array Parsed elements from URL
 */
	public static function parse($url) {
		$ext = null;
		$out = array();

		if ($url && strpos($url, '/') !== 0) {
			$url = '/' . $url;
		}
		if (strpos($url, '?') !== false) {
			$url = substr($url, 0, strpos($url, '?'));
		}

		extract(self::_parseExtension($url));

		$out = self::$_routes->parse($url);

		if (isset($out['prefix'])) {
			$out['action'] = $out['prefix'] . '_' . $out['action'];
		}

		if (!empty($ext) && !isset($out['ext'])) {
			$out['ext'] = $ext;
		}
		return $out;
	}

/**
 * Parses a file extension out of a URL, if Router::parseExtensions() is enabled.
 *
 * @param string $url
 * @return array Returns an array containing the altered URL and the parsed extension.
 */
	protected static function _parseExtension($url) {
		$ext = null;

		if (self::$_parseExtensions) {
			if (preg_match('/\.[0-9a-zA-Z]*$/', $url, $match) === 1) {
				$match = substr($match[0], 1);
				if (empty(self::$_validExtensions)) {
					$url = substr($url, 0, strpos($url, '.' . $match));
					$ext = $match;
				} else {
					foreach (self::$_validExtensions as $name) {
						if (strcasecmp($name, $match) === 0) {
							$url = substr($url, 0, strpos($url, '.' . $name));
							$ext = $match;
							break;
						}
					}
				}
			}
		}
		return compact('ext', 'url');
	}

/**
 * Takes parameter and path information back from the Dispatcher, sets these
 * parameters as the current request parameters that are merged with url arrays
 * created later in the request.
 *
 * Nested requests will create a stack of requests.  You can remove requests using
 * Router::popRequest().  This is done automatically when using Object::requestAction().
 *
 * Will accept either a Cake\Network\Request object or an array of arrays. Support for
 * accepting arrays may be removed in the future.
 *
 * @param Cake\Network\Request|array $request Parameters and path information or a Cake\Network\Request object.
 * @return void
 */
	public static function setRequestInfo($request) {
		if ($request instanceof Request) {
			self::$_requests[] = $request;
		} else {
			$requestData = $request;
			$requestData += array(array(), array());
			$requestData[0] += array(
				'controller' => false,
				'action' => false,
				'plugin' => null
			);
			$request = new Request();
			$request->addParams($requestData[0])->addPaths($requestData[1]);
			self::$_requests[] = $request;
		}
	}

/**
 * Set the route collection object Router should use.
 *
 * @param Cake\Routing\RouteCollection $routes
 * @return void
 */
	public static function setRouteCollection(RouteCollection $routes) {
		self::$_routes = $routes;
	}

/**
 * Pops a request off of the request stack.  Used when doing requestAction
 *
 * @return Cake\Network\Request The request removed from the stack.
 * @see Router::setRequestInfo()
 * @see Object::requestAction()
 */
	public static function popRequest() {
		return array_pop(self::$_requests);
	}

/**
 * Get the either the current request object, or the first one.
 *
 * @param boolean $current Whether you want the request from the top of the stack or the first one.
 * @return Cake\Network\Request or null.
 */
	public static function getRequest($current = false) {
		if ($current) {
			$i = count(self::$_requests) - 1;
			return isset(self::$_requests[$i]) ? self::$_requests[$i] : null;
		}
		return isset(self::$_requests[0]) ? self::$_requests[0] : null;
	}

/**
 * Gets parameter information
 *
 * @param boolean $current Get current request parameter, useful when using requestAction
 * @return array Parameter information
 */
	public static function getParams($current = false) {
		if ($current) {
			return self::$_requests[count(self::$_requests) - 1]->params;
		}
		if (isset(self::$_requests[0])) {
			return self::$_requests[0]->params;
		}
		return array();
	}

/**
 * Gets URL parameter by name
 *
 * @param string $name Parameter name
 * @param boolean $current Current parameter, useful when using requestAction
 * @return string Parameter value
 */
	public static function getParam($name = 'controller', $current = false) {
		$params = Router::getParams($current);
		if (isset($params[$name])) {
			return $params[$name];
		}
		return null;
	}

/**
 * Gets path information
 *
 * @param boolean $current Current parameter, useful when using requestAction
 * @return array
 */
	public static function getPaths($current = false) {
		if ($current) {
			return self::$_requests[count(self::$_requests) - 1];
		}
		if (!isset(self::$_requests[0])) {
			return array('base' => null);
		}
		return array('base' => self::$_requests[0]->base);
	}

/**
 * Reloads default Router settings.  Resets all class variables and
 * removes all connected routes.
 *
 * @return void
 */
	public static function reload() {
		if (empty(self::$_initialState)) {
			self::$_initialState = get_class_vars(get_called_class());
			self::_setPrefixes();
			self::$_routes = new RouteCollection();
			return;
		}
		foreach (self::$_initialState as $key => $val) {
			if ($key != '_initialState') {
				self::${$key} = $val;
			}
		}
		self::_setPrefixes();
		self::$_routes = new RouteCollection();
	}

/**
 * Promote a route (by default, the last one added) to the beginning of the list
 *
 * @param integer $which A zero-based array index representing the route to move. For example,
 *    if 3 routes have been added, the last route would be 2.
 * @return boolean Returns false if no route exists at the position specified by $which.
 */
	public static function promote($which = null) {
		return self::$_routes->promote($which);
	}

/**
 * Finds URL for specified action.
 *
 * Returns an URL pointing to a combination of controller and action. Param
 * $url can be:
 *
 * - Empty - the method will find address to actual controller/action.
 * - '/' - the method will find base URL of application.
 * - A combination of controller/action - the method will find url for it.
 *
 * There are a few 'special' parameters that can change the final URL string that is generated
 *
 * - `base` - Set to false to remove the base path from the generated url. If your application
 *   is not in the root directory, this can be used to generate urls that are 'cake relative'.
 *   cake relative urls are required when using requestAction.
 * - `?` - Takes an array of query string parameters
 * - `#` - Allows you to set url hash fragments.
 * - `full_base` - If true the `FULL_BASE_URL` constant will be prepended to generated urls.
 *
 * @param string|array $url Cake-relative URL, like "/products/edit/92" or "/presidents/elect/4"
 *   or an array specifying any of the following: 'controller', 'action',
 *   and/or 'plugin'.
 *   and standard URL arguments (indexed array elements)
 * @param bool|array $full If (bool) true, the full base URL will be prepended to the result.
 *   If an array accepts the following keys
 *    - escape - used when making urls embedded in html escapes query string '&'
 *    - full - if true the full base URL will be prepended.
 * @return string Full translated URL with base path.
 */
	public static function url($url = null, $full = false) {
		if (is_bool($full)) {
			$escape = false;
		} else {
			extract($full + array('escape' => false, 'full' => false));
		}

		// TODO refactor so there is less overhead
		// incurred on each URL generated.
		$request = self::getRequest(true);
		if ($request) {
			$params = $request->params;
			$requestContext = array(
				'_base' => $request->base,
				'_port' => $request->port(),
				'_scheme' => $request->scheme(),
				'_host' => $request->host()
			);
			$here = $request->here;
		} else {
			$params = array(
				'plugin' => null,
				'controller' => null,
				'action' => 'index'
			);
			$requestContext = array(
				'_base' => '',
				'_port' => 80,
				'_scheme' => 'http',
				'_host' => 'localhost',
			);
			$here = null;
		}

		$extension = $output = $q = $frag = null;

		if (empty($url)) {
			$output = isset($here) ? $here : '/';
			if ($full && defined('FULL_BASE_URL')) {
				$output = FULL_BASE_URL . $output;
			}
			return $output;
		} elseif (is_array($url)) {
			if (isset($url['_full']) && $url['_full'] === true) {
				$full = true;
				unset($url['_full']);
			}
			if (isset($url['?'])) {
				$q = $url['?'];
				unset($url['?']);
			}
			if (isset($url['#'])) {
				$frag = '#' . $url['#'];
				unset($url['#']);
			}
			if (isset($url['ext'])) {
				$extension = '.' . $url['ext'];
				unset($url['ext']);
			}

			// Copy the current action if the controller is the current one.
			if (empty($url['action'])) {
				if (empty($url['controller']) || $params['controller'] === $url['controller']) {
					$url['action'] = $params['action'];
				} else {
					$url['action'] = 'index';
				}
			}

			$prefixExists = (array_intersect_key($url, array_flip(self::$_prefixes)));
			foreach (self::$_prefixes as $prefix) {
				if (!empty($params[$prefix]) && !$prefixExists) {
					$url[$prefix] = true;
				} elseif (isset($url[$prefix]) && !$url[$prefix]) {
					unset($url[$prefix]);
				}
				if (isset($url[$prefix]) && strpos($url['action'], $prefix . '_') === 0) {
					$url['action'] = substr($url['action'], strlen($prefix) + 1);
				}
			}

			$url += array(
				'controller' => $params['controller'],
				'plugin' => $params['plugin']
			);
			$output = self::$_routes->match($url, $params, $requestContext);
		} else {
			// String urls.
			if (
				(strpos($url, '://') !== false ||
				(strpos($url, 'javascript:') === 0) ||
				(strpos($url, 'mailto:') === 0)) ||
				(!strncmp($url, '#', 1))
			) {
				return $url;
			}
			if (substr($url, 0, 1) === '/') {
				$output = substr($url, 1);
			} else {
				foreach (self::$_prefixes as $prefix) {
					if (isset($params[$prefix])) {
						$output .= $prefix . '/';
						break;
					}
				}
				if (!empty($params['plugin']) && $params['plugin'] !== $params['controller']) {
					$output .= Inflector::underscore($params['plugin']) . '/';
				}
				$output .= Inflector::underscore($params['controller']) . '/' . $url;
			}
		}
		$protocol = preg_match('#^[a-z][a-z0-9+-.]*\://#i', $output);
		if ($protocol === 0) {
			$output = str_replace('//', '/', '/' . $output);
			if ($full && defined('FULL_BASE_URL')) {
				$output = FULL_BASE_URL . $output;
			}
			if (!empty($extension)) {
				$output = rtrim($output, '/');
			}
		}
		return $output . $extension . self::queryString($q, array(), $escape) . $frag;
	}

/**
 * Generates a well-formed querystring from $q
 *
 * @param string|array $q Query string Either a string of already compiled query string arguments or
 *    an array of arguments to convert into a query string.
 * @param array $extra Extra querystring parameters.
 * @param boolean $escape Whether or not to use escaped &
 * @return array
 */
	public static function queryString($q, $extra = array(), $escape = false) {
		if (empty($q) && empty($extra)) {
			return null;
		}
		$join = '&';
		if ($escape === true) {
			$join = '&amp;';
		}
		$out = '';

		if (is_array($q)) {
			$q = array_merge($q, $extra);
		} else {
			$out = $q;
			$q = $extra;
		}
		$addition = http_build_query($q, null, $join);

		if ($out && $addition && substr($out, strlen($join) * -1, strlen($join)) != $join) {
			$out .= $join;
		}

		$out .= $addition;

		if (isset($out[0]) && $out[0] != '?') {
			$out = '?' . $out;
		}
		return $out;
	}

/**
 * Reverses a parsed parameter array into a string. Works similarly to Router::url(), but
 * Since parsed URL's contain additional 'pass' as well as 'url.url' keys.
 * Those keys need to be specially handled in order to reverse a params array into a string url.
 *
 * This will strip out 'autoRender', 'bare', 'requested', and 'return' param names as those
 * are used for CakePHP internals and should not normally be part of an output url.
 *
 * @param Cake\Network\Request|array $params The params array or Cake\Network\Request object that needs to be reversed.
 * @param boolean $full Set to true to include the full url including the protocol when reversing
 *     the url.
 * @return string The string that is the reversed result of the array
 */
	public static function reverse($params, $full = false) {
		if ($params instanceof Request) {
			$url = $params->query;
			$params = $params->params;
		} else {
			$url = $params['url'];
		}
		$pass = isset($params['pass']) ? $params['pass'] : array();

		unset(
			$params['pass'], $params['paging'], $params['models'], $params['url'], $url['url'],
			$params['autoRender'], $params['bare'], $params['requested'], $params['return'],
			$params['_Token']
		);
		$params = array_merge($params, $pass);
		if (!empty($url)) {
			$params['?'] = $url;
		}
		return Router::url($params, $full);
	}

/**
 * Normalizes a URL for purposes of comparison.  Will strip the base path off
 * and replace any double /'s.  It will not unify the casing and underscoring
 * of the input value.
 *
 * @param array|string $url URL to normalize Either an array or a string url.
 * @return string Normalized URL
 */
	public static function normalize($url = '/') {
		if (is_array($url)) {
			$url = Router::url($url);
		}
		if (preg_match('/^[a-z\-]+:\/\//', $url)) {
			return $url;
		}
		$request = Router::getRequest();

		if (!empty($request->base) && stristr($url, $request->base)) {
			$url = preg_replace('/^' . preg_quote($request->base, '/') . '/', '', $url, 1);
		}
		$url = '/' . $url;

		while (strpos($url, '//') !== false) {
			$url = str_replace('//', '/', $url);
		}
		$url = preg_replace('/(?:(\/$))/', '', $url);

		if (empty($url)) {
			return '/';
		}
		return $url;
	}

/**
 * Returns the route matching the current request URL.
 *
 * @return Cake\Routing\Route\Route Matching route object.
 * @todo Remove? Not really that useful.
 */
	public static function &requestRoute() {
		return self::$_currentRoute[0];
	}

/**
 * Returns the route matching the current request (useful for requestAction traces)
 *
 * @return Cake\Routing\Route\Route Matching route object.
 * @todo Remove? Not really that useful.
 */
	public static function &currentRoute() {
		return self::$_currentRoute[count(self::$_currentRoute) - 1];
	}

/**
 * Removes the plugin name from the base URL.
 *
 * @param string $base Base URL
 * @param string $plugin Plugin name
 * @return string base url with plugin name removed if present
 * @todo Remove? Not really that useful.
 */
	public static function stripPlugin($base, $plugin = null) {
		if ($plugin != null) {
			$base = preg_replace('/(?:' . $plugin . ')/', '', $base);
			$base = str_replace('//', '', $base);
			$pos1 = strrpos($base, '/');
			$char = strlen($base) - 1;

			if ($pos1 === $char) {
				$base = substr($base, 0, $char);
			}
		}
		return $base;
	}

/**
 * Instructs the router to parse out file extensions from the URL. For example,
 * http://example.com/posts.rss would yield an file extension of "rss".
 * The file extension itself is made available in the controller as
 * `$this->params['ext']`, and is used by the RequestHandler component to
 * automatically switch to alternate layouts and templates, and load helpers
 * corresponding to the given content, i.e. RssHelper. Switching layouts and helpers
 * requires that the chosen extension has a defined mime type in `Cake\Network\Response`
 *
 * A list of valid extension can be passed to this method, i.e. Router::parseExtensions('rss', 'xml');
 * If no parameters are given, anything after the first . (dot) after the last / in the URL will be
 * parsed, excluding querystring parameters (i.e. ?q=...).
 *
 * @return void
 * @see RequestHandler::startup()
 */
	public static function parseExtensions() {
		self::$_parseExtensions = true;
		if (func_num_args() > 0) {
			self::$_validExtensions = func_get_args();
		}
	}

/**
 * Get the list of extensions that can be parsed by Router.  To add more
 * extensions use Router::parseExtensions()
 *
 * @return array Array of extensions Router is configured to parse.
 */
	public static function extensions() {
		return self::$_validExtensions;
	}

}

//Save the initial state
Router::reload();
