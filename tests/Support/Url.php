<?php

namespace LowListTests;

class Url
{
    public $last = array();

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
}
