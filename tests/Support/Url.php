<?php

namespace LowListTests;

class Url
{
    public $last = array();
    public $requested = false;

    /**
     * Capture route construction and preserve the returned URL object.
     *
     * @param string $path
     * @param array $variables
     * @return self
     */
    public function make($path, $variables = array())
    {
        $this->last = array($path, $variables);

        return $this;
    }

    /**
     * Compile the captured CP route.
     *
     * @return string
     */
    public function compile()
    {
        return '/cp/' . $this->last[0];
    }

    /**
     * Expose the current-request match used by native sidebar headers.
     *
     * @return bool
     */
    public function isTheRequestedURI()
    {
        return $this->requested;
    }
}

class UrlFactory
{
    public $urls = array();
    private $requestedUri;

    /**
     * Capture the request URI at the native dependency-factory boundary.
     *
     * @param string $requestedUri
     * @return void
     */
    public function __construct($requestedUri = '')
    {
        $this->requestedUri = $requestedUri;
    }

    /**
     * Return a distinct route object for each dependency-factory invocation.
     *
     * @param string $path
     * @param array $variables
     * @return Url
     */
    public function make($path, $variables = array())
    {
        $url = new Url();
        $url->make($path, $variables);
        $url->requested = 'cp/' . $path === $this->requestedUri;
        $this->urls[] = $url;

        return $url;
    }
}
