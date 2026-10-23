<?php

namespace LowListTests;

class LegacyRuntimeTest extends TestCase
{
    use NativeProbe;

    /**
     * Preserve session selection and existing query values on the healthy EE2 path.
     *
     * @return void
     */
    public function testLegacyUrlsAndSessionModes(): void
    {
        $result = $this->legacy('urls');
        $prefix = 'https://admin.example.test/index.php?&amp;C=addons_modules&amp;M=show_module_cp&amp;module=low_list';
        $plain = $prefix . AMP . 'D=cp';
        $session = $prefix . AMP . 'S=session' . AMP . 'D=cp';
        $this->assertSame(array(
            'default' => $plain,
            'slash' => $plain,
            'method-extra' => $prefix . AMP . 'method=license' . AMP . 'filter=active' . AMP . 'D=cp',
            'session-missing' => $plain,
            'session-id' => $session,
            'session-zero' => $plain,
            'session-string-zero' => $plain,
            'session-null' => $plain,
            'fingerprint' => $prefix . AMP . 'S=fingerprint' . AMP . 'D=cp',
            'fingerprint-empty' => $plain,
            'cookie-only' => $plain,
            'unset-type' => $plain,
            'existing' => $prefix . AMP . 'S=existing' . AMP . 'D=cp',
            'empty-existing' => $prefix . AMP . 'S=' . AMP . 'D=' . AMP . 'S=session' . AMP . 'D=cp',
            'zero-existing' => $prefix . AMP . 'S=0' . AMP . 'D=0' . AMP . 'S=session' . AMP . 'D=cp',
            'directive-only' => $prefix . AMP . 'D=cp' . AMP . 'S=session',
            'session-only' => $prefix . AMP . 'S=existing' . AMP . 'D=cp',
        ), $result['base_urls']);
        $this->assertSame(array(
            'default' => $plain . AMP . 'method=index',
            'null' => $plain . AMP . 'method=license',
            'empty' => $plain . AMP . 'method=license',
            'multiple' => $plain . AMP . 'method=license' . AMP . 'id=3' . AMP . 'zero=0' . AMP . 'empty=' . AMP . 'nullable=' . AMP . 'flag=',
        ), $result['module_urls']);
    }

    /**
     * Preserve the original EE2.5 URL contract without loading the later string helper.
     *
     * @return void
     */
    public function testLegacySlashCleanup(): void
    {
        $result = $this->legacy('slashes');

        $this->assertSame('2.5.4', $result['version']);
        $this->assertSame(array('', '0', '/', '/', '/', 'path/to/file',
            'https://example.test/path/', 'http://example.test',
            '/example.test/path', 'scheme://path', "binary\0/path",
            'https://example.test/?next=http://other.test/path'), $result['values']);
        $this->assertSame(array(), $result['loader_calls']);
        $this->assertSame(array(), $result['helper_calls']);
    }

    /**
     * Delegate later EE2 cleanup to the native string-helper boundary on each call.
     *
     * @return void
     */
    public function testLaterLegacySlashCleanup(): void
    {
        $result = $this->legacy('slashes-helper');
        $inputs = array('', '0', '/', '//', '///', 'path//to///file',
            'https://example.test//path///', 'http://example.test',
            '//example.test///path', 'scheme:////path', "binary\0//path",
            'https://example.test/?next=http://other.test//path');

        $this->assertSame('2.10.1', $result['version']);
        $this->assertSame(array('', '0', '/', '//', '///', 'path/to/file',
            'https://example.test/path/', 'http://example.test',
            '//example.test/path', 'scheme:////path', "binary\0/path",
            'https://example.test/?next=http://other.test/path'), $result['values']);
        $this->assertSame(array_fill(0, count($inputs), array('helper', 'string')), $result['loader_calls']);
        $this->assertSame(array_map(static function ($input) {
            return array('reduce_double_slashes', $input);
        }, $inputs), $result['helper_calls']);
    }

    /**
     * Write only owned writable inline views and preserve loader output behavior.
     *
     * @dataProvider viewModes
     * @param bool $readonly
     * @return void
     */
    public function testLegacyInlineViews($readonly): void
    {
        $result = $this->legacy($readonly ? 'view-readonly' : 'view-writable');
        $this->assertSame(!$readonly, $result['writable']);
        $this->assertSame(array('rendered-legacy-view', 'rendered-legacy-view', null, 'rendered-legacy-view', 'rendered-legacy-view'), $result['returns']);
        $this->assertSame($readonly ? 'preserved-view' : '<?php echo "owned-inline-view"; ?>', $result['written']);
        $this->assertSame(array('example' => 'owned-original-example', 'unused' => 'owned-original-unused', 'zero' => 'owned-original-zero', 'empty' => 'owned-original-empty'), $result['templates']);
        $inline = $readonly ? 'unused' : 'dynamic';
        $this->assertSame(array(
            array('view', 'example', array('value' => null, 'ee_ver' => '2'), true),
            array('view', $inline, array('value' => 0, 'ee_ver' => '2'), true),
            array('view', $inline, array('ee_ver' => '2'), false),
            array('view', 'zero', array('ee_ver' => '2'), true),
            array('view', 'empty', array('ee_ver' => '2'), true),
        ), $result['calls']);
        $this->assertSame('rendered-legacy-view', $result['output']);
    }

    /**
     * Exercise genuine writable and read-only directories in independent children.
     *
     * @return array
     */
    public static function viewModes(): array
    {
        return array('writable' => array(false), 'readonly' => array(true));
    }

    /**
     * Require controlled completion and cleanup of the child-owned installation.
     *
     * @param string $mode
     * @return array
     */
    private function legacy($mode)
    {
        $before = (array) glob(PATH_CACHE . 'low-list-legacy-*');
        list($status, $output, $errors) = $this->probe(array('legacy-bridge', $mode));
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame($before, (array) glob(PATH_CACHE . 'low-list-legacy-*'));
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);

        return $result;
    }
}
