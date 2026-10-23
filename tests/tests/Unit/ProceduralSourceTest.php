<?php

namespace LowListTests;

class ProceduralSourceTest extends TestCase
{
    /**
     * Read addon setup metadata directly from the shipped JSON.
     *
     * @return void
     */
    public function testSetupMetadata(): void
    {
        $setup = require PATH_THIRD . 'low_list/addon.setup.php';
        $this->assertSame(array('name' => 'Low List', 'description' => 'Create loops and lists', 'version' => '2.2.0', 'namespace' => 'LowList', 'author' => 'EEHarbor', 'author_url' => 'http://eeharbor.com/low_list', 'docs_url' => 'http://eeharbor.com/low_list/documentation', 'settings_exist' => false), $setup);
    }

    /**
     * Leave foreign namespaces alone and report missing foundation classes clearly.
     *
     * @return void
     */
    public function testAutoloaderNamespaceAndMissingClass(): void
    {
        $this->assertFalse(class_exists('Unrelated\\NotAnAddonClass'));
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('FluxCapacitor/MissingClass.php not found.');
        class_exists('LowList\\FluxCapacitor\\MissingClass');
    }

    /**
     * Render both shipped views through their actual form-helper boundaries.
     *
     * @dataProvider viewVectors
     * @param string $view
     * @param int $version
     * @param string $key
     * @param bool $ignore
     * @param string $expectedKey
     * @return void
     */
    public function testLicenseViews($view, $version, $key, $ignore, $expectedKey): void
    {
        $GLOBALS['low_list_forms'] = array();
        ee()->services['CP/Alert'] = new Alert();
        $ee_ver = $version;
        $action_url = '/license/save';
        $license_key = $key;
        $ignore_site = $ignore;
        ob_start();
        try {
            include $view;
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertSame(array(array('open', '/license/save', array('class' => 'settings panel-body')), array('input', 'license_key', $expectedKey), array('checkbox', 'ignore_site', '1', $ignore)), $GLOBALS['low_list_forms']);
        $this->assertStringContainsString('class="box ee' . $version . ' addon-license panel"', $output);
        $this->assertSame($version > 2, strpos($output, 'native-alerts') !== false);
        $this->assertStringContainsString('Save License Key', $output);
        unset($GLOBALS['low_list_forms']);
    }

    /**
     * Exercise legacy/current contexts and ignored/active license values.
     *
     * @return array
     */
    public static function viewVectors(): array
    {
        $vectors = array();
        foreach (array(PATH_THIRD . 'low_list/views/license.php', 'phar://' . PATH_THIRD . 'low_list/low_list.phar/Views/license.php') as $path) {
            $vectors[] = array($path, 2, '', false, '');
            $vectors[] = array($path, 7, 'valid-key', false, 'valid-key');
            $vectors[] = array($path, 7, 'low-list-ignore-site-1', true, '');
        }

        return $vectors;
    }
}
