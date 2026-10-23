<?php

class Low_list_ext extends \LowList\FluxCapacitor\Base\Ext
{
    /**
     * Record the forwarded version while executing the original extension updater.
     *
     * @param mixed $current
     * @return bool
     */
    public function updateExtension($current = false)
    {
        $GLOBALS['low_list_update_calls'][] = $current;

        return parent::updateExtension($current);
    }
}
