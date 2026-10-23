<?php

namespace LowListTests;

use LowList\FluxCapacitor\Base\Ft;

class Fieldtype extends Ft
{
    /**
     * Supply the native fieldtype's required concrete rendering entry point.
     *
     * @param mixed $data
     * @return mixed
     */
    public function display_field($data)
    {
        return $data;
    }
}
