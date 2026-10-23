<?php

namespace LowListTests;

class NativeSourceTest extends TestCase
{
    use NativeProbe;

    /**
     * Load shipped code in a fresh native runtime with strict E_ALL diagnostics.
     *
     * @dataProvider sourceFiles
     * @param string $mode
     * @param string $path
     * @return void
     */
    public function testNativeLoad($mode, $path): void
    {
        list($status, $output, $errors) = $this->probe(array($mode, $path));

        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame('OK', $output);
    }

    /**
     * Check conditional bridge declarations in genuinely separate EE contexts.
     *
     * @dataProvider eeVersions
     * @param string $version
     * @return void
     */
    public function testNativeBridgeSelection($version): void
    {
        list($status, $output, $errors) = $this->probe(array('bridge', 'FluxCapacitor/FluxCapacitor.php', $version));

        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame('OK', $output);
    }

    /**
     * Verify that @ cannot hide a deprecation from the child diagnostic gate.
     *
     * @return void
     */
    public function testDiagnosticHarnessDetectsSuppression(): void
    {
        list($status, $output, $errors) = $this->probe(array('diagnostic'));

        $this->assertNotSame(0, $status);
        $this->assertSame('', $output);
        $this->assertStringContainsString('suppressed diagnostic probe', $errors);
    }

    /**
     * Direct access stops before any EE collaborator or autoloader is needed.
     *
     * @return void
     */
    public function testDirectAccessGuard(): void
    {
        list($status, $output, $errors) = $this->probe(array('direct'));

        $this->assertSame(0, $status, $errors);
        $this->assertSame('No direct script access allowed', $output);
        $this->assertSame('', $errors);
    }

    /**
     * Exercise the original self-extracting archive in an isolated temp directory.
     *
     * @return void
     */
    public function testArchiveFallbackHasNoSuppressedDiagnostics(): void
    {
        list($status, $output, $errors) = $this->probe(array('fallback', 'low_list/low_list.phar'));

        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame('OK', $output);
    }

    /**
     * Reject corrupt extraction payloads without leaking PHP diagnostics.
     *
     * @dataProvider corruptPayloads
     * @param string $kind
     * @param string $message
     * @return void
     */
    public function testArchivePayloadValidation($kind, $message): void
    {
        $this->assertArchiveValidation($this->probe(array('stub-extract', $kind)), $message);
    }

    /**
     * Reject an abnormal child exit even when it prints the exact validation text.
     *
     * @return void
     */
    public function testValidationOracleRejectsAbnormalExit(): void
    {
        $result = $this->probe(array('validation-abnormal-exit'));
        $this->assertSame(23, $result[0]);
        $this->assertSame('Invalid internal .phar file (checksum error)', $result[1]);
        $this->assertSame('', $result[2]);
        $this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);

        $this->assertArchiveValidation($result, 'Invalid internal .phar file (checksum error)');
    }

    /**
     * Terminate a stalled owned child, retain its diagnostics and remove capture files.
     *
     * @return void
     */
    public function testNativeProbeDeadline(): void
    {
        $before = (array) glob(PATH_CACHE . 'native-*');
        $started = hrtime(true);
        try {
            $this->probe(array('stall'), 1.0);
            $this->fail('A stalled native child must reach its deadline.');
        } catch (NativeProbeTimeout $exception) {
            $elapsed = (hrtime(true) - $started) / 1000000000;
            $this->assertGreaterThanOrEqual(1.0, $elapsed);
            $this->assertLessThan(5.0, $elapsed);
            $this->assertSame("STALL_READY\n", $exception->output);
            $this->assertSame("STALL_STDERR\n", $exception->errors);
            $this->assertFalse(posix_kill($exception->pid, 0));
        }
        $this->assertSame($before, (array) glob(PATH_CACHE . 'native-*'));
    }

    /**
     * Supply wrong size/checksum and invalid supported compression payloads.
     *
     * @return array
     */
    public static function corruptPayloads(): array
    {
        return array(
            array('size', 'Invalid internal .phar file (size error 7 != 8)'),
            array('checksum', 'Invalid internal .phar file (checksum error)'),
            array('gzip', 'Invalid internal .phar file ('),
            array('bzip2', 'Invalid internal .phar file ('),
        );
    }

    /**
     * Reject corrupt manifest bytes with an explicit exception, without PHP warnings.
     *
     * @dataProvider corruptManifests
     * @param string $kind
     * @return void
     */
    public function testArchiveManifestValidation($kind): void
    {
        list($status, $output, $errors) = $this->probe(array('stub-manifest', $kind));

        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame('REJECTED', $output);
    }

    /**
     * Supply absent and truncated original manifest bytes.
     *
     * @return array
     */
    public static function corruptManifests(): array
    {
        return array(array('empty'), array('truncated'));
    }

    /**
     * Verify missing-resource guards and the process-local opcache compatibility path.
     *
     * @dataProvider loaderModes
     * @param string $mode
     * @return void
     */
    public function testNativeLoaderModes($mode): void
    {
        list($status, $output, $errors) = $this->probe(array($mode, ''));

        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertSame('OK', $output);
    }

    /**
     * Supply independently configured native loader scenarios.
     *
     * @return array
     */
    public static function loaderModes(): array
    {
        return array(array('loader-missing-archive'), array('loader-missing-class'), array('loader-opcache'));
    }

    /**
     * Enumerate all non-view loose/archive source and the original PHAR stub.
     *
     * @return array
     */
    public static function sourceFiles(): array
    {
        $root = dirname(__DIR__, 3);
        $inventory = json_decode(file_get_contents($root . '/tests/source-inventory.json'), true);
        $files = array();
        foreach ($inventory['files'] as $path => $entry) {
            if (strpos($path, '/Views/') !== false || strpos($path, '/views/') !== false) {
                continue;
            }
            if (substr($path, -5) === '#stub') {
                $files[$path] = array('loose', 'low_list/low_list.phar');
                continue;
            }
            $prefix = 'low_list/low_list.phar/';
            $files[$path] = strpos($path, $prefix) === 0
                ? array('archive', substr($path, strlen($prefix)))
                : array('loose', $path);
        }

        return $files;
    }

    /**
     * Supply every supported bridge major and the fallback branch.
     *
     * @return array
     */
    public static function eeVersions(): array
    {
        return array(array('2.11.9'), array('3.5.17'), array('4.3.8'), array('5.4.2'), array('6.4.18'), array('7.5.0'));
    }

    /**
     * Accept only controlled validation termination with no native diagnostics.
     *
     * @param array $result
     * @param string $message
     * @return void
     */
    private function assertArchiveValidation(array $result, $message)
    {
        list($status, $output, $errors) = $result;
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $this->assertStringContainsString($message, $output);
        $this->assertStringNotContainsString('UNEXPECTED_RETURN', $output);
    }
}
