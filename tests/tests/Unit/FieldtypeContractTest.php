<?php

namespace LowListTests;

class FieldtypeContractTest extends TestCase
{
    /**
     * Mirror native singleton reinitialization without carrying an old content ID.
     *
     * @return void
     */
    public function testFieldtypeInitializationContract(): void
    {
        $field = new Fieldtype();
        $configs = array(
            array(),
            array('field_id' => 7, 'field_name' => 'old', 'content_id' => 19, 'settings' => array('field_channel_id' => 9)),
            array('id' => 8, 'field_id' => 88, 'name' => 'preferred', 'field_name' => 'ignored', 'content_id' => 0),
            array('settings' => array()),
            array('id' => null, 'field_id' => 9, 'name' => null, 'field_name' => 'fallback', 'content_id' => '0'),
            array('content_id' => null),
            array('content_id' => false),
            array('content_type' => 'member', 'row' => array('value' => null)),
        );
        $expected = array(
            array(null, null, null, 'channel', array()),
            array(7, 'old', 19, 'channel', array('field_channel_id' => 9)),
            array(8, 'preferred', 0, 'channel', array('field_channel_id' => 9)),
            array(8, 'preferred', null, 'channel', array()),
            array(9, 'fallback', '0', 'channel', array()),
            array(9, 'fallback', null, 'channel', array()),
            array(9, 'fallback', false, 'channel', array()),
            array(9, 'fallback', null, 'member', array()),
        );
        foreach ($configs as $index => $config) {
            $field->_init($config);
            $this->assertSame($expected[$index], array($field->id(), $field->name(), $field->content_id(), $field->content_type(), $field->settings));
            $this->assertSame($field->id(), $field->field_id);
            $this->assertSame($field->name(), $field->field_name);
        }
        $this->assertSame(array('value' => null), $field->row);
    }

    /**
     * Keep query and posted values independent and preserve native null fallback.
     *
     * @dataProvider postedValues
     * @param mixed $value
     * @param mixed $expected
     * @return void
     */
    public function testRequestCollections($value, $expected): void
    {
        $input = new Input();
        $input->getValues = array('channel_id' => 'query');
        $input->postValues = array('channel_id' => $value);
        $this->assertSame('query', $input->get('channel_id'));
        $this->assertSame($value === null ? false : $value, $input->post('channel_id'));
        $this->assertSame($expected, $input->get_post('channel_id'));
        $this->assertFalse($input->get_post('missing'));
        $input->postValues = array();
        $this->assertSame('query', $input->get_post('channel_id'));
    }

    /**
     * Cover supported posted scalar and structured request values.
     *
     * @return array
     */
    public static function postedValues(): array
    {
        return array(
            'text' => array('posted', 'posted'),
            'null' => array(null, 'query'),
            'false' => array(false, false),
            'zero' => array(0, 0),
            'string zero' => array('0', '0'),
            'empty' => array('', ''),
            'structured' => array(array('nested'), array('nested')),
        );
    }

    /**
     * Delegate to XSS cleaning only for the native strict-true flag.
     *
     * @return void
     */
    public function testStrictXssSelection(): void
    {
        $input = new Input();
        $input->getValues = array('channel_id' => 'query');
        $xss = new FieldtypeXss();
        ee()->services['Security/XSS'] = $xss;
        $this->assertSame('query', $input->get_post('channel_id', 1));
        $this->assertSame(array(), $xss->calls);
        $this->assertSame(array('cleaned' => 'query'), $input->get_post('channel_id', true));
        $this->assertSame(array('query'), $xss->calls);
    }

    /**
     * Preserve the external EE Input null-offset diagnostic on PHP 8.5.
     *
     * @return void
     */
    public function testNativeNullIndexBoundary(): void
    {
        $input = new Input();
        if (PHP_VERSION_ID >= 80500) {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Using null as an array offset is deprecated');
        }
        $this->assertFalse($input->post(null));
    }

    /**
     * Preserve canonical URI strings and native one-based segment/default values.
     *
     * @return void
     */
    public function testUriSegmentsAndGlobalHelper(): void
    {
        ee()->uri = new Uri();
        $this->assertSame('', uri_string());
        $this->assertSame(array(), ee()->uri->segment_array());
        $this->assertFalse(ee()->uri->segment(1));
        ee()->uri->path = 'cp/publish/create/9';
        $this->assertSame('cp/publish/create/9', uri_string());
        $this->assertSame(array(1 => 'cp', 2 => 'publish', 3 => 'create', 4 => '9'), ee()->uri->segment_array());
        $this->assertSame('cp', ee()->uri->segment(1));
        $this->assertFalse(ee()->uri->segment(0));
        $this->assertSame('default', ee()->uri->segment(99, 'default'));
    }

    /**
     * Return hydrated object identity and isolate independent entry selections.
     *
     * @return void
     */
    public function testBoundedModelSelection(): void
    {
        $models = new FieldtypeModels();
        $entry = (object) array('channel_id' => 7);
        $models->entries[19] = $entry;
        $first = $models->get('ChannelEntry')->filter('entry_id', 19);
        $second = $models->get('ChannelEntry')->filter('entry_id', 20);
        $this->assertNotSame($first, $second);
        $this->assertSame($entry, $first->first());
        $this->assertNull($second->first());
        $this->assertSame($entry, $first->first());
    }
}
