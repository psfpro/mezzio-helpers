<?php
/**
 * @see       http://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Helper;

use InvalidArgumentException;
use Zend\Expressive\Router\Exception\RuntimeException as RouterException;
use Zend\Expressive\Router\RouteResult;
use Zend\Expressive\Router\RouterInterface;

class UrlHelper
{
    /**
     * @var string
     */
    private $basePath = '/';

    /**
     * @var RouteResult
     */
    private $result;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Generate a URL based on a given route.
     *
     * @param string $routeName
     * @param array  $routeParams
     * @param array  $queryParams
     * @param string $fragmentIdentifier
     * @param array  $options       Can have the following keys:
     *                              - router (array): contains options to be passed to the router
     *                              - reuse_result_params (bool): indicates if the current RouteResult
     *                              parameters will be used, defaults to true
     *
     * @return string
     * @throws \Zend\Expressive\Helper\Exception\RuntimeException
     */
    public function __invoke(
        $routeName = null,
        array $routeParams = [],
        array $queryParams = [],
        $fragmentIdentifier = '',
        array $options = []
    ) {
        $result = $this->getRouteResult();
        if ($routeName === null && $result === null) {
            throw new Exception\RuntimeException(
                'Attempting to use matched result when none was injected; aborting'
            );
        }

        $basePath = $this->getBasePath();
        if ($basePath === '/') {
            $basePath = '';
        }

        // Get the options to be passed to the router
        $routerOptions = array_key_exists('router', $options) ? $options['router'] : [];

        if ($routeName === null) {
            return $basePath . $this->generateUriFromResult($routeParams, $result, $routerOptions);
        }

        $reuseResultParams = !isset($options['reuse_result_params']) || (bool) $options['reuse_result_params'];

        if ($result && $reuseResultParams) {
            // Merge RouteResult with the route parameters
            $routeParams = $this->mergeParams($routeName, $result, $routeParams);
        }

        // Generate the route
        $path = $basePath . $this->router->generateUri($routeName, $routeParams, $routerOptions);

        // Append query parameters if there are any
        if (count($queryParams) > 0) {
            $path .= '?' . http_build_query($queryParams);
        }

        // Append the fragment identifier
        if (!empty($fragmentIdentifier) || (string) $fragmentIdentifier === '0') {
            $path .= '#' . $fragmentIdentifier;
        }

        return $path;
    }

    /**
     * Generate a URL based on a given route.
     *
     * Proxies to __invoke().
     *
     * @see UrlHelper::__invoke()
     */
    public function generate(
        $routeName = null,
        array $routeParams = [],
        array $queryParams = [],
        $fragmentIdentifier = '',
        array $options = []
    ) {
        return $this($routeName, $routeParams, $queryParams, $fragmentIdentifier, $options);
    }

    /**
     * Inject a route result.
     *
     * When the route result is injected, the helper will use it to seed default
     * parameters if the URL being generated is for the route that was matched.
     *
     * @param RouteResult $result
     */
    public function setRouteResult(RouteResult $result)
    {
        $this->result = $result;
    }

    /**
     * Set the base path to prepend to a generated URI
     */
    public function setBasePath($path)
    {
        if (! is_string($path)) {
            throw new InvalidArgumentException(sprintf(
                'Base path must be a string; received %s',
                (is_object($path) ? get_class($path) : gettype($path))
            ));
        }

        $this->basePath = '/' . ltrim($path, '/');
    }

    /**
     * Internal accessor for retrieving the route result.
     *
     * @return null|RouteResult
     */
    protected function getRouteResult()
    {
        return $this->result;
    }

    /**
     * Internal accessor for retrieving the base path.
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * @param array $params
     * @param RouteResult $result
     * @param array $routerOptions
     * @return string
     * @throws RenderingException if current result is a routing failure.
     */
    private function generateUriFromResult(array $params, RouteResult $result, array $routerOptions)
    {
        if ($result->isFailure()) {
            throw new Exception\RuntimeException(
                'Attempting to use matched result when routing failed; aborting'
            );
        }

        $name   = $result->getMatchedRouteName();
        $params = array_merge($result->getMatchedParams(), $params);
        return $this->router->generateUri($name, $params, $routerOptions);
    }

    /**
     * Merge route result params and provided parameters.
     *
     * If the route result represents a routing failure, returns the params
     * verbatim.
     *
     * If the route result does not represent the same route name requested,
     * returns the params verbatim.
     *
     * Otherwise, merges the route result params with those provided at
     * invocation, with the latter having precedence.
     *
     * @param string $route Route name.
     * @param RouteResult $result
     * @param array $params Parameters provided at invocation.
     * @return array
     */
    private function mergeParams($route, RouteResult $result, array $params)
    {
        if ($result->isFailure()) {
            return $params;
        }

        if ($result->getMatchedRouteName() !== $route) {
            return $params;
        }

        return array_merge($result->getMatchedParams(), $params);
    }
}
