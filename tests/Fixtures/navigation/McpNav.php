<?php

namespace LowList\Conduit;

class McpNav extends \LowList\FluxCapacitor\Conduit\McpNav
{
}

class ConfiguredNav extends McpNav
{
    public $events = array();

    /**
     * Supply ordinary extension-provider navigation configuration.
     *
     * @return array|false|null
     */
    protected function defaultItems()
    {
        return $GLOBALS['low_list_navigation_options']['items'];
    }

    /**
     * Supply the provider's item buttons.
     *
     * @return array|false|null
     */
    protected function defaultButtons()
    {
        return $GLOBALS['low_list_navigation_options']['buttons'];
    }

    /**
     * Supply public controller aliases for the provider's items.
     *
     * @return array|false|null
     */
    protected function defaultActiveMap()
    {
        return $GLOBALS['low_list_navigation_options']['map'];
    }

    /**
     * Permit the extension to defer generation until its controller is ready.
     *
     * @return bool
     */
    public function deferGenerate()
    {
        return $GLOBALS['low_list_navigation_options']['defer'];
    }

    /**
     * Observe the supported before-generation hook.
     *
     * @return void
     */
    public function preGenerateNav()
    {
        $this->events[] = array('pre', isset($this->sidebar));
    }

    /**
     * Observe generation completion without editing private source state.
     *
     * @return void
     */
    public function postGenerateNav()
    {
        $this->events[] = array('post', isset($this->sidebar));
    }
}
