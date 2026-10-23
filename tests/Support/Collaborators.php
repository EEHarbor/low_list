<?php

namespace LowListTests;

class Functions
{
    public $cleared = array();
    public $redirects = array();
    public $databaseCallsAtRedirect = array();

    /**
     * Record invalidation before installing a module row.
     *
     * @param string $mode
     * @return void
     */
    public function clear_caching($mode)
    {
        $this->cleared[] = $mode;
    }

    /**
     * Model EE's terminal redirect without terminating the PHPUnit process.
     *
     * @param string|object $url
     * @return void
     * @throws Redirected
     */
    public function redirect($url)
    {
        $target = is_object($url) ? $url->compile() : $url;
        $this->redirects[] = $target;
        $this->databaseCallsAtRedirect[] = ee()->db->calls;

        throw new Redirected($target);
    }
}

class Redirected extends \RuntimeException
{
}

class Input
{
    public $values = array();

    /**
     * Return query-string parameters with EE's absent-value false.
     *
     * @param string $name
     * @param bool $xss
     * @return mixed
     */
    public function get($name, $xss = false)
    {
        return isset($this->values[$name]) ? $this->values[$name] : false;
    }

    /**
     * Preserve legacy posted values, including zero and missing/null false values.
     *
     * @param string $name
     * @param bool $xss
     * @return mixed
     */
    public function post($name, $xss = false)
    {
        return isset($this->values[$name]) ? $this->values[$name] : false;
    }
}

class Uri
{
    public $path = '';

    /**
     * Return the current request path.
     *
     * @return string
     */
    public function uri_string()
    {
        return $this->path;
    }

    /**
     * Return the current URI segments for sidebar selection.
     *
     * @return array
     */
    public function segment_array()
    {
        return explode('/', $this->path);
    }
}

class Cache
{
    public $values = array();

    /**
     * Read a persistent cache entry.
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->values[$key]) ? $this->values[$key] : false;
    }

    /**
     * Persist a cache value.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function save($key, $value)
    {
        $this->values[$key] = $value;

        return true;
    }

    /**
     * Remove a persistent cache value.
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        unset($this->values[$key]);

        return true;
    }
}

class Session
{
    public $values = array();
    public $cache = array();
    public $flash = array();
    public $cookiesSet = false;

    /**
     * Record the facade's native session-installation side effect.
     *
     * @return void
     */
    public function setSessionCookies()
    {
        $this->cookiesSet = true;
    }

    /**
     * Store a legacy flash-message value using EE2's public API.
     *
     * @param string $type
     * @param string $message
     * @return void
     */
    public function set_flashdata($type, $message)
    {
        $this->flash[$type] = $message;
    }

    /**
     * Return an existing EE2 session identifier or the caller's default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function userdata($key, $default = false)
    {
        return isset($this->values[$key]) ? $this->values[$key] : $default;
    }

    /**
     * Store a request-local cache value.
     *
     * @param string $module
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function set_cache($module, $key, $value)
    {
        $this->values[$module . '/' . $key] = $value;

        return true;
    }

    /**
     * Read a request-local cache value.
     *
     * @param string $module
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function cache($module, $key, $default)
    {
        return isset($this->values[$module . '/' . $key]) ? $this->values[$module . '/' . $key] : $default;
    }
}

class Pagination
{
    public $calls = array();

    /**
     * Record the supported pagination service operations.
     *
     * @param string $method
     * @param array $arguments
     * @return self
     * @throws \LogicException
     */
    public function __call($method, $arguments)
    {
        if (!in_array($method, array('perPage', 'currentPage', 'displayPageLinks', 'queryStringVariable'), true)) {
            throw new \LogicException('Unexpected pagination method: ' . $method);
        }
        $this->calls[] = array($method, $arguments[0]);

        return $this;
    }

    /**
     * Require a CP URL object at the native renderer's typed boundary.
     *
     * @param Url $baseUrl
     * @return string
     */
    public function render(Url $baseUrl)
    {
        $this->calls[] = array('render', $baseUrl);

        return 'pagination';
    }
}

class VariableParser
{
    public $calls = array();

    /**
     * Record tag parameter parsing and its default values.
     *
     * @param string $string
     * @param array $defaults
     * @return array
     */
    public function parseTagParameters($string, $defaults)
    {
        $this->calls[] = array('parameters', $string, $defaults);

        return array('parameters');
    }

    /**
     * Record extraction of template variables.
     *
     * @param string $string
     * @return array
     */
    public function extractVariables($string)
    {
        $this->calls[] = array('variables', $string);

        return array('variables');
    }

    /**
     * Preserve the legacy EE3 tag parameter boundary.
     *
     * @param string $string
     * @param array $defaults
     * @return array
     */
    public function assign_parameters($string, $defaults)
    {
        return $this->parseTagParameters($string, $defaults);
    }

    /**
     * Preserve the legacy EE3 variable extraction boundary.
     *
     * @param string $string
     * @return array
     */
    public function assign_variables($string)
    {
        return $this->extractVariables($string);
    }
}

class AlertRecorder
{
    public $calls = array();

    /**
     * Select an inline alert through the legacy EE3 factory.
     *
     * @param string $title
     * @return self
     */
    public function make($title)
    {
        $this->calls[] = array('make', $title);

        return $this;
    }

    /**
     * Select the named inline alert.
     *
     * @param string $title
     * @return self
     */
    public function makeInline($title)
    {
        $this->calls[] = array('makeInline', $title);

        return $this;
    }

    /**
     * Mark this alert as an issue.
     *
     * @return self
     */
    public function asIssue()
    {
        $this->calls[] = array('asIssue');

        return $this;
    }

    /**
     * Mark this alert as a warning.
     *
     * @return self
     */
    public function asWarning()
    {
        $this->calls[] = array('asWarning');

        return $this;
    }

    /**
     * Mark this alert as successful.
     *
     * @return self
     */
    public function asSuccess()
    {
        $this->calls[] = array('asSuccess');

        return $this;
    }

    /**
     * Set the alert title.
     *
     * @param string $title
     * @return self
     */
    public function withTitle($title)
    {
        $this->calls[] = array('withTitle', $title);

        return $this;
    }

    /**
     * Add alert body text.
     *
     * @param string $body
     * @return self
     */
    public function addToBody($body)
    {
        $this->calls[] = array('addToBody', $body);

        return $this;
    }

    /**
     * Enable closing the alert.
     *
     * @return self
     */
    public function canClose()
    {
        $this->calls[] = array('canClose');

        return $this;
    }

    /**
     * Disable closing the alert through the optional API.
     *
     * @return self
     */
    public function cannotClose()
    {
        $this->calls[] = array('cannotClose');

        return $this;
    }

    /**
     * Defer display until the next request.
     *
     * @return self
     */
    public function defer()
    {
        $this->calls[] = array('defer');

        return $this;
    }
}

class Sidebar
{
    public $headers = array();

    /**
     * Return the sidebar builder.
     *
     * @return self
     */
    public function make()
    {
        return $this;
    }

    /**
     * Add a named navigation header.
     *
     * @param string $title
     * @param mixed $url
     * @return Header
     */
    public function addHeader($title, $url = null)
    {
        $header = new Header($title, $url);
        $this->headers[] = $header;

        return $header;
    }
}

class Header
{
    public $text;
    public $url;
    public $button;
    public $class = '';

    /**
     * Preserve the native header's title and optional URL.
     *
     * @param string $title
     * @param mixed $url
     * @return void
     */
    public function __construct($title, $url = null)
    {
        $this->text = $title;
        if ($url) {
            $this->withUrl($url);
        }
    }

    /**
     * Set the navigation URL.
     *
     * @param mixed $url
     * @return self
     */
    public function withUrl($url)
    {
        $this->url = $url;
        if ($url instanceof Url && $url->isTheRequestedURI()) {
            $this->isActive();
        }

        return $this;
    }

    /**
     * Add a navigation button.
     *
     * @param string $text
     * @param mixed $url
     * @return self
     */
    public function withButton($text, $url)
    {
        $this->button = array('text' => $text, 'url' => $url);

        return $this;
    }

    /**
     * Mark the navigation header active.
     *
     * @return self
     */
    public function isActive()
    {
        $this->class .= 'active ';

        return $this;
    }
}

class ControlPanel
{
    public $footer = array();
    public $footer_item = array();
    public $rightNav = array();
    public $breadcrumbs = array();
    private $breadcrumbValues = array();

    /**
     * Preserve the legacy public footer alias for the same CP footer collection.
     *
     * @return void
     */
    public function __construct()
    {
        $this->footer_item = &$this->footer;
    }

    /**
     * Read the current CP footer blocks.
     *
     * @return array
     */
    public function get_foot()
    {
        return $this->footer;
    }

    /**
     * Append markup without executing scripts or requesting the ping URL.
     *
     * @param string $markup
     * @return void
     */
    public function add_to_foot($markup)
    {
        $this->footer[] = $markup;
    }

    /**
     * Set the control panel breadcrumb.
     *
     * @param mixed $url
     * @param mixed $title
     * @return void
     */
    public function set_breadcrumb($url, $title)
    {
        $this->breadcrumbs[] = array($url, $title);
        $link = is_object($url) ? $url->compile() : $url;
        $this->breadcrumbValues[$link] = $title;
        ee()->view->cp_breadcrumbs = $this->breadcrumbValues;
    }

    /**
     * Record legacy control-panel navigation labels and URLs.
     *
     * @param array $navigation
     * @return void
     */
    public function set_right_nav($navigation)
    {
        $this->rightNav = $navigation;
    }
}

class ViewFactory
{
    public $name;
    public $variables;

    /**
     * Select the named EE view.
     *
     * @param string $name
     * @return self
     */
    public function make($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Select the raw string rendering path.
     *
     * @param string $string
     * @return self
     */
    public function makeFromString($string)
    {
        $this->name = $string;

        return $this;
    }

    /**
     * Capture the exact variables passed to the rendering service.
     *
     * @param array $variables
     * @return string
     */
    public function render($variables)
    {
        $this->variables = $variables;

        return 'rendered-view';
    }
}

class Alert
{
    /**
     * Return the inline notices rendered by EE's alert service.
     *
     * @return string
     */
    public function getAllInlines()
    {
        return '<p>native-alerts</p>';
    }
}
