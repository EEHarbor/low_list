<?php

namespace LowListTests;

use LowList\FluxCapacitor\Base\Ext;
use LowList\FluxCapacitor\Base\Mod;
use LowList\FluxCapacitor\Base\Pi;
use LowList\FluxCapacitor\Base\Tab;
use LowList\FluxCapacitor\Base\Upd;
use LowList\FluxCapacitor\FluxCapacitor;

class BaseTest extends TestCase
{
    /**
     * Read real setup metadata through the shipped bridge constructor.
     *
     * @return void
     */
    public function testConstructors(): void
    {
        foreach (array(Pi::class, Mod::class, Tab::class, Ext::class) as $class) {
            $object = new $class();
            $this->assertInstanceOf(FluxCapacitor::class, $object->flux);
            $this->assertSame('2.2.0', $object->version);
        }
        $this->assertSame('Low List', (new Ext())->name);
    }

    /**
     * The plugin information API must be callable statically.
     *
     * @return void
     */
    public function testPluginInfoCanBeRead(): void
    {
        $this->assertIsArray(Pi::info());
    }

    /**
     * Normalize whitespace entities and retain an explicit untrimmed value.
     *
     * @return void
     */
    public function testModuleParameterFallbacks(): void
    {
        $module = new Mod();
        ee()->TMPL->tagparams = array('value' => '&nbsp; hello &#32;');
        $this->assertSame('hello', $this->invoke($module, 'getParameter', array('value', 'fallback')));
        $this->assertSame('&nbsp; hello &#32;', $this->invoke($module, 'getParameter', array('value', 'fallback', false)));
        $this->assertSame('fallback', $this->invoke($module, 'getParameter', array('missing', 'fallback')));
        ee()->TMPL->tagparams['value'] = '';
        $this->assertSame('fallback', $this->invoke($module, 'getParameter', array('value', 'fallback')));
    }

    /**
     * Forward tab APIs and extract entry context from modern and legacy inputs.
     *
     * @return void
     */
    public function testTabContext(): void
    {
        $tab = new Tab();
        $this->assertSame(array(), $tab->display(1, 2));
        $this->assertTrue($tab->save((object) array('entry_id' => 1), array()));
        $this->assertSame(array('entry_id' => 1, 'channel_id' => 2, 'site_id' => 3), $tab->getEntryVariables((object) array('entry_id' => 1, 'channel_id' => 2, 'site_id' => 3), array()));
        $this->assertSame(array('entry_id' => '4', 'channel_id' => '5', 'site_id' => '6'), $tab->getEntryVariables(null, array('entry_id' => '4', 'meta' => array('channel_id' => '5', 'site_id' => '6'))));
        $this->assertSame(array('entry_id' => 0, 'channel_id' => 0, 'site_id' => 0), $tab->getEntryVariables(null, array()));
    }

    /**
     * Register unique hooks and normalize non-array serialized legacy settings.
     *
     * @return void
     */
    public function testExtensionRegistrationAndWrappers(): void
    {
        $extension = new Ext();
        $extension->settings = 'legacy';
        $this->assertTrue($extension->registerExtension('after_save', 'entry_save', 5, 'n'));
        $data = end(ee()->db->calls);
        $this->assertSame(array('insert', array('extensions', array('class' => Ext::class, 'method' => 'after_save', 'hook' => 'entry_save', 'settings' => serialize(array()), 'priority' => 5, 'version' => '2.2.0', 'enabled' => 'n'))), $data);
        ee()->db->rows = array(array('extension_id' => 1));
        $before = count(ee()->db->calls);
        $this->assertTrue($extension->registerExtension('after_save'));
        $this->assertCount($before + 1, ee()->db->calls);
        ee()->db->rows = array();
        $extension->hooks = array('hook_one', 'hook_two');
        $this->assertTrue($extension->activate_extension());
        $this->assertTrue($extension->update_extension('1.0.0'));
        $this->assertTrue($extension->disable_extension());
        $this->assertTrue($this->invoke($extension, 'update_version'));
        $this->assertTrue($this->invoke($extension, 'unregisterExtension', array('hook_one', 'custom')));
        $this->assertTrue($this->invoke($extension, 'unregister_extension', array('hook_two')));
        $this->assertTrue($extension->cp_custom_menu(null));
    }

    /**
     * Compatibility aliases must forward hook, priority and enabled arguments.
     *
     * @return void
     */
    public function testExtensionAliasForwardsArguments(): void
    {
        $extension = new Ext();
        $this->invoke($extension, 'register_extension', array('method', 'different_hook', 3, 'n'));
        $insert = end(ee()->db->calls);

        $this->assertSame('different_hook', $insert[1][1]['hook']);
        $this->assertSame(3, $insert[1][1]['priority']);
        $this->assertSame('n', $insert[1][1]['enabled']);
    }

    /**
     * Install and uninstall using the expected Active Record side-effect order.
     *
     * @return void
     */
    public function testUpdaterDatabaseOperations(): void
    {
        $updater = $this->withoutConstructor(Upd::class);
        $updater->flux = new FluxCapacitor();
        $updater->module_name = 'Low_list';
        $updater->version = '2.2.0';
        $updater->ext_settings = 'y';
        $this->assertTrue($updater->install());
        $this->assertSame(array(), ee()->db->calls);
        $updater->ext_settings = 'n';
        ee()->functions = new Functions();
        $this->assertTrue($updater->install());
        $this->assertSame(array('db'), ee()->functions->cleared);
        $this->assertFalse($updater->update(''));
        $this->assertFalse($updater->update('2.2.0'));
        ee()->db->rows = array(array('module_id' => 12));
        $this->assertTrue($updater->uninstall());
        $this->assertSame(array('delete', array('menu_items')), end(ee()->db->calls));
    }

    /**
     * Initialize the updater and perform its version-pixel side effect locally.
     *
     * @return void
     */
    public function testUpdaterConstructor(): void
    {
        ee()->db->tables = array('eeharbor_licenses');
        ee()->cp = new ControlPanel();
        $updater = new Upd();

        $this->assertSame('2.2.0', $updater->version);
        $this->assertSame(Upd::class, $updater->module_name);
        $this->assertCount(1, ee()->cp->footer);
    }
}
