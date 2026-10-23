<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\Version;

class LicenseSchemaTest extends TestCase
{
    /**
     * Create every required license column and index before verifying its metadata.
     *
     * @return void
     */
    public function testMissingTableIsCreatedAndVerified(): void
    {
        ee()->db->data_cache = array('table_names' => array('stale'), 'unrelated' => 'keep');
        $version = new Version();

        $this->assertFalse($version->verifyLicenseTable());
        $this->assertSame(array('eeharbor_licenses'), ee()->db->tables);
        $this->assertSame(array('site_id', 'addon', 'license_key', 'ignore_site'), ee()->db->fields['eeharbor_licenses']);
        $this->assertSame('keep', ee()->db->data_cache['unrelated']);
        $this->assertSame(array(), ee()->db->data_cache['table_names']);
        $this->assertSame(array('exp_eeharbor_licenses'), ee()->dbforge->db->data_cache['table_names']);
        $this->assertSame(array('site_id', 'addon', 'license_key', 'ignore_site'), ee()->db->data_cache['field_names']['eeharbor_licenses']);
        $fields = array(
            'site_id' => array('type' => 'int(11)', 'unsigned' => true, 'null' => false, 'default' => '0'),
            'addon' => array('type' => 'varchar(30)', 'null' => false, 'default' => ''),
            'license_key' => array('type' => 'varchar(50)', 'null' => false, 'default' => ''),
            'ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0'),
        );
        $this->assertSame(array(array('add_field', $fields), array('add_key', 'license_key', true), array('add_key', array('site_id', 'addon'), false), array('create_table', 'eeharbor_licenses')), ee()->dbforge->calls);
        $this->assertSame(array(array('table_exists', array('eeharbor_licenses')), array('field_exists', array('ignore_site', 'eeharbor_licenses')), array('query', array("SHOW KEYS FROM exp_eeharbor_licenses WHERE Key_name = 'PRIMARY'"))), ee()->db->calls);
        $this->assertSame(array(array('dbforge')), ee()->load->calls);
    }

    /**
     * Add only the missing legacy column and preserve unrelated metadata.
     *
     * @return void
     */
    public function testLegacyIgnoreColumnIsAdded(): void
    {
        ee()->db->tables = array('eeharbor_licenses');
        ee()->db->fields['eeharbor_licenses'] = array('site_id', 'addon', 'license_key');
        ee()->db->rows = array(array('Column_name' => 'license_key', 'Non_unique' => '0'));
        ee()->db->data_cache['field_names'] = array('eeharbor_licenses' => array('stale'), 'unrelated' => array('keep'));
        $version = new Version();

        $this->assertFalse($version->verifyLicenseTable());
        $this->assertSame(array(array('add_column', 'eeharbor_licenses', array('ignore_site' => array('type' => 'int(1)', 'unsigned' => true, 'null' => false, 'default' => '0')))), ee()->dbforge->calls);
        $this->assertSame(array('site_id', 'addon', 'license_key', 'ignore_site'), ee()->db->fields['eeharbor_licenses']);
        $this->assertSame(array('eeharbor_licenses' => array('stale'), 'unrelated' => array('keep')), ee()->db->data_cache['field_names']);
        $this->assertFalse(isset(ee()->dbforge->db->data_cache['field_names']['eeharbor_licenses']));
        $this->assertSame(array(array('dbforge')), ee()->load->calls);
        $calls = ee()->db->calls;
        $this->assertFalse($version->verifyLicenseTable());
        $this->assertSame($calls, ee()->db->calls);
        $this->assertCount(1, ee()->dbforge->calls);
    }

    /**
     * Return valid, absent, composite, and malformed primary-key metadata.
     *
     * @return array
     */
    public static function primaryKeys(): array
    {
        return array(
            'valid' => array(array(array('Column_name' => 'license_key', 'Non_unique' => '0')), false),
            'missing' => array(array(), true),
            'composite' => array(array(array('Column_name' => 'site_id', 'Non_unique' => '0'), array('Column_name' => 'addon', 'Non_unique' => '0')), true),
            'wrong-column' => array(array(array('Column_name' => 'site_id', 'Non_unique' => '0')), true),
            'non-unique' => array(array(array('Column_name' => 'license_key', 'Non_unique' => '1')), true),
            'null-column' => array(array(array('Column_name' => null, 'Non_unique' => '0')), true),
        );
    }

    /**
     * Preserve the verified corruption result without repeating schema work.
     *
     * @dataProvider primaryKeys
     * @param array $rows
     * @param bool $corrupt
     * @return void
     */
    public function testPrimaryKeyVerificationAndCache(array $rows, $corrupt): void
    {
        ee()->db->tables = array('eeharbor_licenses');
        ee()->db->rows = $rows;
        $version = new Version();
        $calls = ee()->db->calls;

        $this->assertSame($corrupt, $version->verifyLicenseTable());
        ee()->db->rows = array();
        $this->assertSame($corrupt, (new Version())->verifyLicenseTable());
        $this->assertSame($calls, ee()->db->calls);
        $this->assertSame(array(), ee()->load->calls);
        $this->assertFalse(ee()->has('dbforge'));
    }

    /**
     * Keep an already installed native schema builder when the loader is reused.
     *
     * @return void
     */
    public function testSchemaLoaderRetainsLoadedBuilder(): void
    {
        ee()->load->dbforge();
        $forge = ee()->dbforge;
        ee()->load->dbforge();

        $this->assertInstanceOf(SchemaForge::class, $forge);
        $this->assertNotSame(ee()->db, $forge->db);
        $this->assertSame($forge, ee()->dbforge);
        $this->assertSame($forge, ee()->get('dbforge'));
        $this->assertSame(array(), $forge->calls);
        $this->assertSame(array(array('dbforge'), array('dbforge')), ee()->load->calls);
    }

    /**
     * Match native metadata caching and schema-builder invalidation boundaries.
     *
     * @return void
     */
    public function testDatabaseMetadataCacheAndColumnInvalidation(): void
    {
        ee()->db->tables = array('eeharbor_licenses');
        ee()->db->fields['eeharbor_licenses'] = array('license_key');
        ee()->db->data_cache['table_names'] = array('exp_stale');
        $this->assertFalse(ee()->db->table_exists('eeharbor_licenses'));
        unset(ee()->db->data_cache['table_names']);
        $this->assertTrue(ee()->db->table_exists('eeharbor_licenses'));
        $this->assertSame(array('exp_eeharbor_licenses'), ee()->db->data_cache['table_names']);
        $this->assertFalse(ee()->db->field_exists('ignore_site', 'eeharbor_licenses'));
        $this->assertSame(array('license_key'), ee()->db->data_cache['field_names']['eeharbor_licenses']);
        ee()->load->dbforge();
        ee()->dbforge->db->data_cache['field_names']['eeharbor_licenses'] = array('license_key');
        ee()->dbforge->add_column('eeharbor_licenses', array('ignore_site' => array('type' => 'int(1)')));
        $this->assertFalse(isset(ee()->dbforge->db->data_cache['field_names']['eeharbor_licenses']));
        $this->assertFalse(ee()->db->field_exists('ignore_site', 'eeharbor_licenses'));
        unset(ee()->db->data_cache['field_names']['eeharbor_licenses']);
        $this->assertTrue(ee()->db->field_exists('ignore_site', 'eeharbor_licenses'));
        $this->assertSame(array('license_key', 'ignore_site'), ee()->db->data_cache['field_names']['eeharbor_licenses']);
    }

    /**
     * Share live schema changes without sharing query-builder metadata caches.
     *
     * @return void
     */
    public function testExistingQueryObservesNewSchema(): void
    {
        $query = ee('db');
        ee()->load->dbforge();
        ee()->dbforge->add_field(array('id' => array('type' => 'int(11)')));
        ee()->dbforge->add_key('id', true);
        $this->assertTrue(ee()->dbforge->create_table('records'));

        $this->assertTrue($query->table_exists('records'));
        $this->assertTrue($query->field_exists('id', 'records'));
        $this->assertSame(array('exp_records'), $query->data_cache['table_names']);
        $query->data_cache['table_names'] = array('exp_stale');
        $this->assertFalse($query->table_exists('records'));
        $this->assertFalse(isset(ee()->db->data_cache['table_names']));
        $this->assertSame(array('exp_records'), ee()->dbforge->db->data_cache['table_names']);
        unset($query->data_cache['table_names']);
        $this->assertTrue($query->table_exists('records'));
    }
}
