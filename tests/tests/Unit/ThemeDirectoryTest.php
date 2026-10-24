<?php

namespace LowListTests;

class ThemeDirectoryTest extends TestCase
{
    use NativeProbe;

    /**
     * Prefer the configured host constant through each original bridge selection.
     *
     * @dataProvider bridgeVersions
     * @param string $mode
     * @param string $version
     * @param string $parent
     * @param string $themeUrl
     * @return void
     * @throws \RuntimeException
     */
    public function testConfiguredThemeDirectory($mode, $version, $parent, $themeUrl): void
    {
        $hadConstant = defined('URL_THIRD_THEMES');
        try {
            list($status, $output, $errors) = $this->probe(array('legacy-bridge', $mode));
            $this->assertSame(0, $status, $errors);
            $this->assertSame('', $errors);
            $result = json_decode($output, true);
            $this->assertIsArray($result, $output);
            $this->assertSame(array(
                'version' => $version,
                'bridge_parent' => $parent,
                'theme_constant' => $themeUrl,
                'urls' => array($themeUrl . 'low_list/', $themeUrl . 'low_list/'),
                'fallback_after' => 'https://fallback.example.test/second/',
                'database_calls' => array(),
                'service_calls' => array(),
            ), $result);
        } finally {
            $this->assertSame($hadConstant, defined('URL_THIRD_THEMES'));
        }
    }

    /**
     * Supply normal host URLs for the four implementations and current inheritance.
     *
     * @return array
     */
    public static function bridgeVersions(): array
    {
        return array(
            'EE2' => array('themes-ee2', '2.10.1', 'LowList\\FluxCapacitor\\Bridge\\EE2', 'https://assets.example.test/themes/third_party/'),
            'EE3' => array('themes-ee3', '3.5.11', 'LowList\\FluxCapacitor\\Bridge\\EE3', 'https://assets.example.test/themes/user/'),
            'EE4' => array('themes-ee4', '4.3.8', 'LowList\\FluxCapacitor\\Bridge\\EE4', 'https://assets.example.test/themes/user/'),
            'EE5' => array('themes-ee5', '5.4.3', 'LowList\\FluxCapacitor\\Bridge\\EE5', 'https://assets.example.test/themes/user/'),
            'current EE6 bridge' => array('themes-current', '7.5.0', 'LowList\\FluxCapacitor\\Bridge\\EE6', 'https://assets.example.test/themes/user/'),
        );
    }
}
