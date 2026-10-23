<?php

namespace LowListTests;

class FieldtypeContextTest extends TestCase
{
    /**
     * Select the current channel through real settings/request/entry/create inputs.
     *
     * @dataProvider channelContexts
     * @param mixed $entryId
     * @param array $settings
     * @param array $get
     * @param array $post
     * @param string $path
     * @param array $entries
     * @param mixed $expected
     * @param bool $lookup
     * @return void
     */
    public function testChannelSelection($entryId, $settings, $get, $post, $path, $entries, $expected, $lookup): void
    {
        $field = new Fieldtype();
        $field->_init(array('content_id' => $entryId, 'settings' => $settings));
        ee()->input = new Input();
        ee()->input->getValues = $get;
        ee()->input->postValues = $post;
        ee()->uri = new Uri();
        ee()->uri->path = $path;
        $models = new FieldtypeModels();
        $models->entries = $entries;
        ee()->services['Model'] = $models;
        ee()->serviceCalls = array();
        try {
            $this->assertSame($expected, $field->getChannelId());
            $this->assertSame($entryId, $field->content_id());
            $this->assertSame($settings, $field->settings);
        } finally {
            $this->assertSame($lookup ? array(array('get', 'ChannelEntry'), array('filter', 'entry_id', $entryId), array('first')) : array(), $models->calls);
            $this->assertSame($lookup ? array(array('Model')) : array(), ee()->serviceCalls);
        }
    }

    /**
     * Exercise falsey storage, POST precedence, entry hydration and create routes.
     *
     * @return array
     */
    public static function channelContexts(): array
    {
        $entry = array(19 => (object) array('channel_id' => '7'));
        return array(
            'absent context' => array(null, array(), array(), array(), '', array(), false, false),
            'query' => array(null, array(), array('channel_id' => '8'), array(), '', array(), '8', false),
            'structured query channel is rejected' => array(null, array(), array('channel_id' => array('9')), array(), '', array(), false, false),
            'structured posted channel is rejected' => array(null, array(), array('channel_id' => '8'), array('channel_id' => array('9')), '', array(), false, false),
            'posted first' => array(null, array(), array('channel_id' => '8'), array('channel_id' => '9'), '', array(), '9', false),
            'null post falls back' => array(null, array(), array('channel_id' => '8'), array('channel_id' => null), '', array(), '8', false),
            'false posted wins' => array(null, array(), array('channel_id' => '8'), array('channel_id' => false), '', array(), false, false),
            'zero posted wins' => array(null, array(), array('channel_id' => '8'), array('channel_id' => 0), '', array(), 0, false),
            'string zero posted wins' => array(null, array(), array('channel_id' => '8'), array('channel_id' => '0'), '', array(), '0', false),
            'empty posted wins' => array(null, array(), array('channel_id' => '8'), array('channel_id' => ''), '', array(), '', false),
            'settings first' => array(19, array('field_channel_id' => '12'), array('channel_id' => '8'), array('channel_id' => '9'), '', $entry, '12', false),
            'null setting falls back' => array(null, array('field_channel_id' => null), array('channel_id' => 8), array(), '', array(), 8, false),
            'zero setting overrides request' => array(null, array('field_channel_id' => 0), array('channel_id' => 8), array(), '', array(), 0, false),
            'string zero setting' => array(null, array('field_channel_id' => '0'), array('channel_id' => 8), array(), '', array(), '0', false),
            'false setting' => array(null, array('field_channel_id' => false), array('channel_id' => 8), array(), '', array(), false, false),
            'empty setting' => array(null, array('field_channel_id' => ''), array('channel_id' => 8), array(), '', array(), '', false),
            'entry lookup' => array(19, array(), array(), array(), '', $entry, '7', true),
            'string entry identifier' => array('19', array(), array(), array(), '', $entry, '7', true),
            'entry overrides zero setting' => array(19, array('field_channel_id' => 0), array('channel_id' => 8), array(), '', $entry, '7', true),
            'null entry setting' => array(19, array('field_channel_id' => null), array(), array(), '', $entry, '7', true),
            'request avoids stale entry' => array(19, array(), array('channel_id' => 8), array(), '', array(), 8, false),
            'zero entry identifier' => array(0, array(), array(), array(), '', $entry, false, false),
            'string zero entry identifier' => array('0', array(), array(), array(), '', $entry, false, false),
            'create path' => array(null, array(), array(), array(), 'cp/publish/create/6', array(), 6, false),
            'zero create path' => array(null, array(), array(), array(), 'cp/publish/create/0', array(), 0, false),
            'request precedes create path' => array(null, array(), array('channel_id' => 8), array(), 'cp/publish/create/6', array(), 8, false),
            'falsey request permits create path' => array(null, array(), array('channel_id' => '0'), array(), 'cp/publish/create/6', array(), 6, false),
            'entry precedes create path' => array(19, array(), array(), array(), 'cp/publish/create/6', $entry, '7', true),
            'falsey hydrated channel permits create path' => array(19, array(), array(), array(), 'cp/publish/create/6', array(19 => (object) array('channel_id' => 0)), 6, true),
            'nullable hydrated channel' => array(19, array(), array(), array(), '', array(19 => (object) array('channel_id' => null)), null, true),
            'noncreate route' => array(null, array(), array(), array(), 'cp/publish/edit/19', array(), false, false),
            'deleted entry is safely absent' => array(19, array(), array(), array(), '', array(), false, true),
        );
    }

    /**
     * Reset singleton content state before selecting another field's channel.
     *
     * @return void
     */
    public function testReinitializationClearsThePreviousEntry(): void
    {
        $field = new Fieldtype();
        ee()->input = new Input();
        ee()->uri = new Uri();
        $models = new FieldtypeModels();
        $models->entries[19] = (object) array('channel_id' => 7);
        ee()->services['Model'] = $models;
        $field->_init(array('content_id' => 19, 'field_id' => 3, 'field_name' => 'first'));
        $this->assertSame(7, $field->getChannelId());
        $models->calls = array();
        ee()->serviceCalls = array();
        $field->_init(array('field_id' => 4, 'field_name' => 'second', 'settings' => array()));
        ee()->uri->path = 'cp/publish/create/6';
        $this->assertNull($field->content_id());
        $this->assertSame(4, $field->id());
        $this->assertSame('second', $field->name());
        $this->assertSame(6, $field->getChannelId());
        $this->assertSame(array(), $models->calls);
        $this->assertSame(array(), ee()->serviceCalls);
    }

    /**
     * Keep exact modern settings cells and falsey default package selection.
     *
     * @return void
     */
    public function testModernSettingsAliasesPreserveRowsAndDefaults(): void
    {
        $field = new Fieldtype();
        $field->displaySettingsRow('Title', null);
        $field->_display_settings_add_row('Other', 0, 'Description', true);
        $rows = array(
            array('title' => 'Title', 'desc' => '', 'wide' => false, 'fields' => array(array('type' => 'html', 'content' => null))),
            array('title' => 'Other', 'desc' => 'Description', 'wide' => true, 'fields' => array(array('type' => 'html', 'content' => 0))),
        );
        $this->assertSame($rows, $field->rows);
        $this->assertSame(array('field_options_low_list' => array('label' => 'field_options', 'group' => 'low_list', 'settings' => $rows)), $field->displaySettingsReturn('0', 0));
        $this->assertSame(array('custom' => array('label' => null, 'group' => 'group', 'settings' => $rows)), $field->_package_display_settings('custom', 'group', null));
        $this->assertSame($rows, $field->rows);
    }
}
