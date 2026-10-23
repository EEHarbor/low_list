<?php

namespace LowListTests;

class SettingsInputTest extends TestCase
{
    /**
     * Clear configured metadata on the source's builder while preserving defaults.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testConfiguredMetadataRefreshAndWrites($class): void
    {
        $metadata = array('table_names' => array('exp_stale'), 'unrelated' => 'keep');
        $GLOBALS['low_list_ee'] = new Environment($metadata);
        ee()->db->tables = array('low_list_settings');
        ee()->db->results['low_list_settings'] = array(array('custom' => 'database', 'site_id' => 1));
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));

        $this->assertSame('database', $bridge->getConfig('custom'));
        $this->assertSame($metadata, ee()->db->data_cache);
        $this->assertSame($metadata, ee('db')->data_cache);
        $bridge->setConfig('custom', 'inserted');
        $this->assertSame(array('insert', array('low_list_settings', array('site_id' => 1, 'custom' => 'inserted'))), end(ee()->db->calls));
        $this->assertSame('inserted', $bridge->getConfig('custom'));
        ee()->db->count = 1;
        $bridge->setConfig('custom', 'updated');
        $this->assertSame(array(
            array('where', array('site_id', 1)),
            array('update', array('low_list_settings', array('site_id' => 1, 'custom' => 'updated'))),
        ), array_slice(ee()->db->calls, -2));
        $this->assertSame('updated', $bridge->getConfig('custom'));
        $this->assertSame($metadata, ee()->db->data_cache);
        $this->assertSame($metadata, ee('db')->data_cache);
    }

    /**
     * Map multiple structure rows and retain authoritative add-on metadata.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testStructureSettingsMapEveryRow($class): void
    {
        ee()->db->tables = array('structure_settings');
        ee()->db->results['structure_settings'] = array(
            array('var' => 'custom', 'var_value' => 'configured'),
            array('var' => 'zero', 'var_value' => '0'),
            array('var' => 'empty', 'var_value' => ''),
            array('var' => 'name', 'var_value' => 'Database label'),
        );
        $bridge = new $class(array('module' => 'structure', 'module_name' => 'Structure'));

        $settings = $bridge->getSettings(true);

        $this->assertSame('configured', $settings['custom']);
        $this->assertSame('0', $settings['zero']);
        $this->assertSame('', $settings['empty']);
        $this->assertSame('Low List', $settings['name']);
        $this->assertSame('2.2.0', $settings['version']);
        $this->assertContains(array('get_where', array('structure_settings', array('structure_settings.site_id' => 1))), ee()->db->calls);
        $calls = ee()->db->calls;
        $this->assertSame($settings, $bridge->getSettings(true));
        $this->assertSame($calls, ee()->db->calls);
    }

    /**
     * Preserve setup metadata for empty structure rows and store its first value.
     *
     * @dataProvider bridges
     * @param string $class
     * @return void
     */
    public function testEmptyStructureRowsAcceptFirstSetting($class): void
    {
        ee()->db->tables = array('structure_settings');
        ee()->db->results['structure_settings'] = array();
        $bridge = new $class(array('module' => 'structure', 'module_name' => 'Structure'));

        $this->assertSame(require PATH_THIRD . 'low_list/addon.setup.php', $bridge->getSettings(true));
        $bridge->setConfig('custom', 0);
        $this->assertSame(array('insert', array('structure_settings', array('site_id' => 1, 'custom' => 0))), end(ee()->db->calls));
        $this->assertSame(0, $bridge->getConfig('custom'));
        $this->assertSame(array(), ee()->db->data_cache);
    }

    /**
     * Normalize stored empty settings and retain strict diagnostics for bad shapes.
     *
     * @dataProvider fieldtypeCells
     * @param string $class
     * @param mixed $cell
     * @return void
     */
    public function testFieldtypeSettingsCellIsSafe($class, $cell): void
    {
        ee()->db->results['fieldtypes'] = array(array('settings' => $cell));
        $bridge = new $class(array('module' => 'low_list', 'module_name' => 'Low List'));

        $settings = $bridge->getSettings(true);

        $this->assertSame('Low List', $settings['name']);
        $this->assertSame('2.2.0', $settings['version']);
    }

    /**
     * Supply actual TEXT cells used by empty, nullable and corrupt stored settings.
     *
     * @return array
     */
    public static function fieldtypeCells(): array
    {
        $cells = array(
            'serialized-empty-array' => base64_encode(serialize(array())),
            'serialized-false' => base64_encode(serialize(false)),
            'serialized-null' => base64_encode(serialize(null)),
            'serialized-zero' => base64_encode(serialize(0)),
            'serialized-empty-string' => base64_encode(serialize('')),
            'empty-text' => '',
            'invalid-base64-empty' => '%%',
            'sql-null' => null,
            'malformed-serialized-text' => base64_encode('not serialized'),
            'serialized-nonempty-string' => base64_encode(serialize('wrong shape')),
        );
        $cases = array();
        foreach (self::bridges() as $bridge) {
            foreach ($cells as $name => $cell) {
                $cases[substr($bridge[0], -3) . '-' . $name] = array($bridge[0], $cell);
            }
        }

        return $cases;
    }

    /**
     * Include each concrete bridge and the inherited EE6 compatibility surface.
     *
     * @return array
     */
    public static function bridges(): array
    {
        return array(
            array('LowList\\FluxCapacitor\\Bridge\\EE2'),
            array('LowList\\FluxCapacitor\\Bridge\\EE3'),
            array('LowList\\FluxCapacitor\\Bridge\\EE4'),
            array('LowList\\FluxCapacitor\\Bridge\\EE5'),
            array('LowList\\FluxCapacitor\\Bridge\\EE6'),
        );
    }
}
