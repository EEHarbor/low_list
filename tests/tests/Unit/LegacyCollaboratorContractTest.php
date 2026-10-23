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
