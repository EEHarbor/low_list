<?php

namespace LowList\FluxCapacitor\Conduit;

/**
 * Observe the source's three-argument encryption call while using real OpenSSL.
 *
 * @param string $data
 * @param string|null $encryptedData
 * @param mixed $publicKey
 * @return bool
 */
function openssl_public_encrypt($data, &$encryptedData, $publicKey)
{
    $success = \openssl_public_encrypt($data, $encryptedData, $publicKey);
    if (isset($GLOBALS['low_list_encryptions'])) {
        $GLOBALS['low_list_encryptions'][] = array('data' => $data, 'success' => $success);
    }

    return $success;
}
