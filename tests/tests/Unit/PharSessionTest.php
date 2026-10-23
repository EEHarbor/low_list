<?php

namespace LowListTests;

class PharSessionTest extends TestCase
{
    use NativeProbe;

    /**
     * Accept valid native files sessions without suppressed fallback diagnostics.
     *
     * @dataProvider validSessionPaths
     * @param string $case
     * @return void
     */
    public function testNativeSessionFallback($case): void
    {
        list($status, $result, $errors) = $this->sessionContext($case);
        $session = $result['native_session'];
        $this->assertIsArray($session);
        $this->assertSame('files', $session['handler']);
        $this->assertSame($result['session_save_path'], $session['configured_path']);
        $this->assertSame(0600, $session['mode']);
        $this->assertSame('owned|s:6:"native";', $session['contents']);
        $this->assertFileDoesNotExist($session['file']);
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame('', $result['diagnostic']);
        $this->assertTrue($result['returned'], $result['diagnostic']);
        $this->assertSame($result['expected_base'] . '/pharextract/low_list', $result['source_temp']);
    }

    /**
     * Supply native plain, depth, mode and default-path session configurations.
     *
     * @return array
     */
    public static function validSessionPaths(): array
    {
        return array(array('writable'), array('depth'), array('mode'), array('empty'), array('readonly'), array('file-temp'));
    }

    /**
     * Reject invalid fallback directories through the original controlled guard.
     *
     * @dataProvider invalidSessionPaths
     * @param string $case
     * @return void
     */
    public function testInvalidSessionFallbackGuard($case): void
    {
        list($status, $result, $errors) = $this->sessionContext($case);
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertNull($result['native_session']);
        $this->assertFalse($result['returned']);
        $this->assertNull($result['source_temp']);
        $this->assertSame('Could not locate temporary directory to extract phar', $result['diagnostic']);
    }

    /**
     * Keep absent and file-valued fallback boundaries distinct.
     *
     * @return array
     */
    public static function invalidSessionPaths(): array
    {
        return array(array('missing-session'), array('file-session'));
    }

    /**
     * Require a complete child protocol and independent parent ownership checks.
     *
     * @param string $case
     * @return array
     */
    private function sessionContext($case)
    {
        if (!is_dir(PATH_CACHE)) {
            mkdir(PATH_CACHE, 0700, true);
        }
        $preserved = PATH_CACHE . 'session-outside.txt';
        file_put_contents($preserved, 'keep');
        $before = (array) glob(PATH_CACHE . 'low-list-session-*');
        try {
            list($status, $output, $errors) = $this->probe(array('phar-session', $case));
            $this->assertSame($before, (array) glob(PATH_CACHE . 'low-list-session-*'));
            $result = json_decode($output, true);
            $this->assertIsArray($result, $output . $errors);
            $this->assertSame($case, $result['case']);
            $this->assertTrue($result['cleaned']);
            $this->assertDirectoryDoesNotExist($result['owned_root']);
            $this->assertDirectoryDoesNotExist(PATH_CACHE . 'pharextract');
            $this->assertSame($case === 'file-temp' ? 'preserved-temp-boundary' : null, $result['boundary_contents']);
            $this->assertSame($case === 'file-session' ? 'preserved-session-boundary' : null, $result['session_boundary_contents']);

            return array($status, $result, $errors);
        } finally {
            $this->assertSame('keep', file_get_contents($preserved));
            $this->assertSame($before, (array) glob(PATH_CACHE . 'low-list-session-*'));
        }
    }
}
