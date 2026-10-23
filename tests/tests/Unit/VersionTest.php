<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\StaticCache;
use LowList\FluxCapacitor\Conduit\Version;

class VersionTest extends TestCase
{
    /**
     * Verify the primary key, clear stale metadata and reuse request results.
     *
     * @return void
     */
    public function testLicenseTableVerification(): void
    {
        ee()->db->tables = array('eeharbor_licenses');
        ee()->db->rows = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
        ee()->db->data_cache['table_names'] = array('stale');
        $version = new Version();

        $this->assertArrayNotHasKey('table_names', ee()->db->data_cache);
        $this->assertFalse($version->verifyLicenseTable());
        StaticCache::clear();
        ee()->db->rows = array();
        $this->assertTrue((new Version())->verifyLicenseTable());
        StaticCache::clear();
        ee()->db->rows = array(array('Column_name' => 'wrong_column', 'Non_unique' => '0'));
        $this->assertTrue((new Version())->verifyLicenseTable());
        StaticCache::clear();
        ee()->db->rows = array(array('Column_name' => 'license_key', 'Non_unique' => '1'));
        $this->assertTrue((new Version())->verifyLicenseTable());
    }

    /**
     * Read, sanitize, insert, update and delete site-scoped license records.
     *
     * @return void
     */
    public function testLicenseDatabaseLifecycle(): void
    {
        $version = $this->withoutConstructor(Version::class);
        $this->assertFalse($version->keyExistsForAddon());
        $this->assertFalse($version->keyExistsForAddon());
        StaticCache::clear();
        ee()->db->count = 1;
        $this->assertTrue($version->keyExistsForAddon());
        $this->assertTrue($version->keyExistsForAddon());
        $version->saveLicenseKey(array('license_key' => 'KEY'));
        $this->assertSame(array('update', array('eeharbor_licenses', array('license_key' => 'KEY', 'site_id' => 1, 'addon' => 'LowList'))), end(ee()->db->calls));
        StaticCache::clear();
        ee()->db->count = 0;
        $version->saveLicenseKey(array('license_key' => 'KEY', 'site_id' => 2, 'addon' => 'Other'), 'Other');
        $this->assertSame(array('insert', array('eeharbor_licenses', array('license_key' => 'KEY', 'site_id' => 2, 'addon' => 'Other'))), end(ee()->db->calls));
        $this->assertSame('', $version->getLicenseKey());
        $this->assertFalse($version->getIgnoreSite());
        ee()->db->rows = array(array('license_key' => ' key <>&_-123 ', 'ignore_site' => '1'));
        $this->assertSame('key-123', $version->getLicenseKey());
        $this->assertSame('1', $version->getIgnoreSite());
        $version->deleteLicenseKey();
        $this->assertSame(array('delete', array('eeharbor_licenses')), end(ee()->db->calls));
    }

    /**
     * Generate encrypted update markup once without making an HTTP request.
     *
     * @dataProvider updateModes
     * @param bool $local
     * @return void
     */
    public function testUpdatePixelIsEncryptedAndIdempotent($local): void
    {
        $before = $_GET;
        try {
            $_GET = $local ? array('ldev' => 1) : array();
            ee()->db->tables = array('eeharbor_licenses');
            ee()->cp = new ControlPanel();
            $version = new Version();
            $this->assertNull($version->check());
            $this->assertCount(1, ee()->cp->footer);
            $this->assertStringContainsString('<div id="low_list__up" style="', ee()->cp->footer[0]);
            $this->assertMatchesRegularExpression('/\?[0-9a-f]{1024}/', ee()->cp->footer[0]);
            $this->assertStringNotContainsString('example.test', ee()->cp->footer[0]);
            $this->assertTrue($version->check());
            $this->assertCount(1, ee()->cp->footer);
        } finally {
            $_GET = $before;
        }
    }

    /**
     * Exercise production and local-development ping destinations.
     *
     * @return array
     */
    public static function updateModes(): array
    {
        return array(array(false), array(true));
    }

    /**
     * Database null values must not produce a PHP deprecation during sanitization.
     *
     * @return void
     */
    public function testNullLicenseIsSafe(): void
    {
        ee()->db->rows = array(array('license_key' => null));

        $this->assertSame('', $this->withoutConstructor(Version::class)->getLicenseKey());
    }
}
