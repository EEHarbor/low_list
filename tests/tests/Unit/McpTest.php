<?php

namespace LowListTests;

use LowList\FluxCapacitor\Base\Mcp;

class McpTest extends TestCase
{
    /**
     * Render the native license form context for absent, active and ignored rows.
     *
     * @dataProvider licenseRows
     * @param array $rows
     * @param string $key
     * @param mixed $ignored
     * @return void
     */
    public function testLicenseViewContext(array $rows, $key, $ignored): void
    {
        $mcp = new Mcp();
        ee()->db->rows = $rows;
        ee()->view = (object) array('cp_page_title' => 'License');
        ee()->services['CP/URL'] = new Url();
        ee()->services['View'] = new ViewFactory();
        $result = $mcp->license();
        $vars = ee()->services['View']->variables;

        $this->assertSame('License', $result['heading']);
        $this->assertSame('rendered-view', $result['body']);
        $this->assertSame('low_list:license', ee()->services['View']->name);
        $this->assertSame('7', $vars['ee_ver']);
        $this->assertSame($key, $vars['license_key']);
        $this->assertSame($ignored, $vars['ignore_site']);
    }

    /**
     * Supply database null, ordinary and ignored license states.
     *
     * @return array
     */
    public static function licenseRows(): array
    {
        return array(array(array(), '', false), array(array(array('license_key' => 'KEY', 'ignore_site' => '0')), 'KEY', 0), array(array(array('license_key' => 'ignored', 'ignore_site' => '1')), '', 1));
    }

    /**
     * Preserve both explicit and default view variables and raw string rendering.
     *
     * @return void
     */
    public function testBridgeViewRendering(): void
    {
        $bridge = new \LowList\FluxCapacitor\FluxCapacitor();
        ee()->view = (object) array('cp_page_title' => 'Heading');
        ee()->services['CP/URL'] = new Url();
        ee()->services['View'] = new ViewFactory();
        $result = $bridge->view('license', array('base_url' => '/custom', 'cp_page_title' => 'Custom'), true, '<h1>raw</h1>');

        $this->assertSame('rendered-view', $result['body']);
        $this->assertSame('<h1>raw</h1>', ee()->services['View']->name);
        $this->assertSame('/custom', ee()->services['View']->variables['base_url']);
        $this->assertSame('Custom', ee()->services['View']->variables['cp_page_title']);
    }
}
