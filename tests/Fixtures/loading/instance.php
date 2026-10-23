<?php

/**
 * Return the supplied controller by reference at the historical global boundary.
 *
 * @return object
 */
function &get_instance()
{
    $GLOBALS['low_list_instance_calls']++;

    return $GLOBALS['low_list_instance'];
}
