<?php

namespace LowListTests;

use LowList\FluxCapacitor\Base\Ft;

class FieldtypeTest extends TestCase
{
    /**
     * Initialize fieldtype services and preserve supported content-type behavior.
     *
     * @return void
     */
    public function testFieldtypeDefaultsAndSettingsRows(): void
    {
        $field = new Fieldtype();
        $this->assertSame('2.2.0', $field->version);
        $this->assertTrue($field->update('old-version'));
        $this->assertTrue($field->accepts_content_type('channel'));
        $this->assertFalse($field->accepts_content_type('member'));
        $field->displaySettingsRow('Title', '<input>', 'Description', true);
        $field->_display_settings_add_row('Other', '<select>');
        $this->assertCount(2, $field->rows);
        $this->assertSame(array('label' => 'field_options', 'group' => 'low_list', 'settings' => $field->rows), $field->displaySettingsReturn()['field_options_low_list']);
        $this->assertSame(array('custom' => array('label' => 'Label', 'group' => 'Group', 'settings' => $field->rows)), $field->_package_display_settings('custom', 'Group', 'Label'));
    }

    /**
     * Prefer stored channel settings over the request when no entry exists.
     *
     * @return void
     */
    public function testChannelContextFromSettingsAndRequest(): void
    {
        $field = new Fieldtype();
        ee()->input = new FieldInput();
        ee()->input->channel = 9;
        $this->assertSame(9, $field->getChannelId());
        $field->settings = array('field_channel_id' => '12');
        $this->assertSame('12', $field->getChannelId());
    }
}

class Fieldtype extends Ft
{
}

class FieldInput
{
    public $channel;

    /**
     * Return the channel identifier from GET/POST.
     *
     * @param string $name
     * @return mixed
     */
    public function get_post($name)
    {
        return $this->channel;
    }
}
