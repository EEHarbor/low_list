<?php

namespace LowListTests;

class LegacyApi
{
    public $calls = array();

    /**
     * Record initialization of a legacy API by its exact name.
     *
     * @param string $name
     * @return void
     */
    public function instantiate($name)
    {
        $this->calls[] = $name;
    }
}

class Security
{
    public $calls = array();
    public $result = 'cleaned-result';

    /**
     * Record the modern XSS service's original input and preserve its result.
     *
     * @param mixed $input
     * @return mixed
     */
    public function clean($input)
    {
        $this->calls[] = $input;

        return $this->result;
    }

    /**
     * Expose the same boundary through EE2's security object.
     *
     * @param mixed $input
     * @return mixed
     */
    public function xss_clean($input)
    {
        return $this->clean($input);
    }
}

class Formatter
{
    public $calls = array();

    /**
     * Record the format kind and text selected by the add-on.
     *
     * @param string $kind
     * @param string $text
     * @return self
     */
    public function make($kind, $text)
    {
        $this->calls[] = array('make', $kind, $text);

        return $this;
    }

    /**
     * Record the URL-slug formatter selection.
     *
     * @return self
     */
    public function urlSlug()
    {
        $this->calls[] = array('urlSlug');

        return $this;
    }

    /**
     * Return the prepared URL from EE's formatter.
     *
     * @return string
     */
    public function url()
    {
        $this->calls[] = array('url');

        return 'prepared-result';
    }

    /**
     * Return the compiled slug from EE's formatter.
     *
     * @return string
     */
    public function compile()
    {
        $this->calls[] = array('compile');

        return 'slug-result';
    }
}

class AddonsModel
{
    public $calls = array();
    public $installed = false;

    /**
     * Return the configured installed-module result.
     *
     * @param string $module
     * @return bool
     */
    public function module_installed($module)
    {
        $this->calls[] = $module;

        return $this->installed;
    }
}

class LegacyPagination
{
    public $options = array();

    /**
     * Retain the complete options supplied to EE2 pagination.
     *
     * @param array $options
     * @return void
     */
    public function initialize($options)
    {
        $this->options = $options;
    }

    /**
     * Return rendered links through the native legacy boundary.
     *
     * @return string
     */
    public function create_links()
    {
        return 'legacy-pagination';
    }
}
