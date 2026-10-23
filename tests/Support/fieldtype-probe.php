<?php

namespace LowListTests;

final class FieldtypeProbe
{
    /**
     * Load the original fieldtype with a fresh legacy bridge and bounded services.
     *
     * @param string $root
     * @param string $mode
     * @return array
     * @throws \RuntimeException
     */
    public static function run($root, $mode)
    {
        if (!in_array($mode, array('legacy', 'legacy-structured'), true)) {
            throw new \RuntimeException('Unknown fieldtype request.');
        }
        require_once __DIR__ . '/Environment.php';
        require_once __DIR__ . '/Collaborators.php';
        require_once __DIR__ . '/FieldtypeCollaborators.php';
        define('BASEPATH', __DIR__);
        define('PATH_THIRD', $root . '/');
        define('APP_VER', '2.5.5');
        define('REQ', 'PAGE');
        define('SYSPATH', dirname(__DIR__) . '/Fixtures/');
        define('APPPATH', SYSPATH . 'ee/legacy/');
        $GLOBALS['low_list_ee'] = new Environment();
        ee()->input = new Input();
        ee()->table = new FieldtypeTable();
        require $root . '/low_list/autoload.php';

        $field = new class extends \LowList\FluxCapacitor\Base\Ft {
            /**
             * Supply the concrete fieldtype rendering boundary.
             *
             * @param mixed $data
             * @return mixed
             */
            public function display_field($data)
            {
                return $data;
            }
        };
        $field->_init(array('content_id' => 19, 'settings' => array('field_channel_id' => 12)));
        ee()->input->getValues = array('channel_id' => 'query');
        $channels = array();
        $values = $mode === 'legacy' ? array('posted', null, false, 0, '0', '') : array(array('9'));
        foreach ($values as $value) {
            ee()->input->postValues = array('channel_id' => $value);
            $channels[] = $field->getChannelId();
        }
        ee()->input->getValues = array();
        ee()->input->postValues = array();
        $channels[] = $field->getChannelId();
        $field->displaySettingsRow('Title', '<input>', 'ignored');
        $field->_display_settings_add_row('Wide', '<select>', 'ignored', true);

        return array(
            'channels' => $channels,
            'rows' => ee()->table->calls,
            'modern_rows' => $field->rows,
            'returns' => array($field->displaySettingsReturn(), $field->_package_display_settings('custom', 'group', 'label')),
            'content_id' => $field->content_id(),
            'model_calls' => array_values(array_filter(ee()->serviceCalls, static function ($call) { return $call[0] === 'Model'; })),
        );
    }
}
