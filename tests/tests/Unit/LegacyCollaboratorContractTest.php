<?php

namespace LowListTests;

class LegacyCollaboratorContractTest extends TestCase
{
    /**
     * Return text only for true and otherwise retain the rendered output.
     *
     * @return void
     */
    public function testLoaderSeparatesReturnedAndAppendedOutput(): void
    {
        $loader = ee()->load;

        $this->assertSame('rendered-legacy-view', $loader->view('example', array('value' => null), true));
        $this->assertSame('', $loader->viewOutput);
        $this->assertNull($loader->view('example', array(), false));
        $this->assertSame('rendered-legacy-view', $loader->viewOutput);
        $this->assertNull($loader->view('example', array(), 1));
        $this->assertSame('rendered-legacy-viewrendered-legacy-view', $loader->viewOutput);
        $this->assertSame(array(
            array('view', 'example', array('value' => null), true),
            array('view', 'example', array(), false),
            array('view', 'example', array(), 1),
        ), $loader->calls);
    }

    /**
     * Match the verified full EE2.5.4 Functions source for protocol and path boundaries.
     *
     * @dataProvider slashValues
     * @param string $input
     * @param string $expected
     * @return void
     */
    public function testLegacySlashCleanup($input, $expected): void
    {
        $this->assertSame($expected, (new Functions())->remove_double_slashes($input));
    }

    /**
     * Supply literal expected values independently of the fixture's implementation.
     *
     * @return array
     */
    public static function slashValues(): array
    {
        return array(
            array('', ''), array('0', '0'), array('/', '/'), array('//', '/'),
            array('///', '/'), array('path//to///file', 'path/to/file'),
            array('https://example.test//path///', 'https://example.test/path/'),
            array('http://example.test', 'http://example.test'),
            array('//example.test///path', '/example.test/path'),
            array('scheme:////path', 'scheme://path'),
            array("binary\0//path", "binary\0/path"),
            array('https://example.test/?next=http://other.test//path', 'https://example.test/?next=http://other.test/path'),
        );
    }

    /**
     * Preserve the later helper's distinct leading and protocol slash semantics.
     *
     * @dataProvider helperSlashValues
     * @param string $input
     * @param string $expected
     * @return void
     */
    public function testLaterSlashHelper($input, $expected): void
    {
        $this->assertSame($expected, reduce_double_slashes($input));
        $this->assertSame(array(array('reduce_double_slashes', $input)), $GLOBALS['low_list_helpers']);
    }

    /**
     * Supply literal native helper results, including intentionally retained slashes.
     *
     * @return array
     */
    public static function helperSlashValues(): array
    {
        return array(
            array('', ''), array('0', '0'), array('/', '/'), array('//', '//'),
            array('///', '///'), array('path//to///file', 'path/to/file'),
            array('https://example.test//path///', 'https://example.test/path/'),
            array('http://example.test', 'http://example.test'),
            array('//example.test///path', '//example.test/path'),
            array('scheme:////path', 'scheme:////path'),
            array("binary\0//path", "binary\0/path"),
            array('https://example.test/?next=http://other.test//path', 'https://example.test/?next=http://other.test/path'),
        );
    }

    /**
     * Preserve zero values while missing and null session items use the default.
     *
     * @return void
     */
    public function testSessionItemsMatchNativeDefaults(): void
    {
        $session = new Session();
        $session->values = array('session_id' => 'session', 'fingerprint' => 'fingerprint', 'zero' => 0, 'string_zero' => '0', 'empty' => '', 'null' => null);
        $expected = array('session_id' => 'session', 'fingerprint' => 'fingerprint', 'zero' => 0, 'string_zero' => '0', 'empty' => '', 'null' => 'default', 'missing' => 'default');
        foreach ($expected as $name => $value) {
            $this->assertSame($value, $session->userdata($name, 'default'));
        }
    }
}
