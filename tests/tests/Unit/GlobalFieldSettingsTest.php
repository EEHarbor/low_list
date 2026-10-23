<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\GlobalFieldSettings;

class GlobalFieldSettingsTest extends TestCase
{
    /**
     * Default the fieldtype name and read base64-serialized settings.
     *
     * @return void
     */
    public function testSettingsLifecycle(): void
    {
        $settings = new GlobalFieldSettings();
        $this->assertSame('low_list', $settings->fieldtype_name);
        $this->assertNull($settings->getSettings());
        $this->assertNull($settings->getSetting('missing'));
        $this->assertFalse($settings->updateSetting('key', 'value'));
        ee()->db->rows = array(array('settings' => base64_encode(serialize(array('key' => 'value', 'nullable' => null)))));
        $this->assertSame('value', $settings->getSetting('key'));
        $this->assertNull($settings->getSetting('nullable'));
        $this->assertTrue($settings->updateSetting('zero', 0));
        $this->assertSame(array('update', array('fieldtypes', array('settings' => base64_encode(serialize(array('key' => 'value', 'nullable' => null, 'zero' => 0)))))), end(ee()->db->calls));
    }

    /**
     * Preserve custom names and valid serialized empty settings.
     *
     * @return void
     */
    public function testCustomAndEmptySettings(): void
    {
        $settings = new GlobalFieldSettings('other_field');
        ee()->db->rows = array(array('settings' => base64_encode(serialize(array()))));

        $this->assertSame('other_field', $settings->fieldtype_name);
        $this->assertSame(array(), $settings->getSettings());
        $this->assertTrue($settings->updateSetting('enabled', false));
    }

    /**
     * A missing setting is an ordinary lookup and must not emit a diagnostic.
     *
     * @return void
     */
    public function testMissingKeyIsSafe(): void
    {
        ee()->db->rows = array(array('settings' => base64_encode(serialize(array('other' => true)))));

        $this->assertNull((new GlobalFieldSettings())->getSetting('missing'));
    }

    /**
     * Legacy malformed storage must be handled without suppressed diagnostics.
     *
     * @return void
     */
    public function testMalformedLegacySettingsAreSafe(): void
    {
        ee()->db->rows = array(array('settings' => base64_encode('malformed serialized data')));

        $this->assertSame(array(), (new GlobalFieldSettings())->getSettings());
    }
}
