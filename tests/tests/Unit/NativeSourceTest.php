<?php

namespace LowListTests;

class NativeSourceTest extends TestCase
{
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
     * Start a child using this suite's explicit PHP binary and local settings.
     *
     * @param array $arguments
     * @return array
     * @throws \RuntimeException
     */
    private function probe(array $arguments)
    {
        $command = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', dirname(__DIR__, 2) . '/Support/native-probe.php');
        $environment = getenv();
        if (getenv('LOW_LIST_XDEBUG_EXTENSION')) {
            array_splice($command, 1, 0, array('-d', 'zend_extension=' . getenv('LOW_LIST_XDEBUG_EXTENSION'), '-d', 'auto_prepend_file=' . dirname(__DIR__, 2) . '/coverage-prepend.php'));
            $environment['LOW_LIST_COVERAGE_FILE'] = getenv('LOW_LIST_COVERAGE_DIRECTORY') . '/native-' . bin2hex(random_bytes(8)) . '.json';
        }
        if (!is_dir(PATH_CACHE)) {
            mkdir(PATH_CACHE, 0700, true);
        }
        array_splice($command, 1, 0, array('-d', 'sys_temp_dir=' . PATH_CACHE));
        $environment['TMPDIR'] = PATH_CACHE;
        $outputPath = tempnam(PATH_CACHE, 'native-out-');
        $errorPath = tempnam(PATH_CACHE, 'native-err-');
        $process = proc_open(array_merge($command, $arguments), array(1 => array('file', $outputPath, 'w'), 2 => array('file', $errorPath, 'w')), $pipes, null, $environment);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start native source probe.');
        }
        $status = proc_close($process);
        $output = file_get_contents($outputPath);
        $errors = file_get_contents($errorPath);
        unlink($outputPath);
        unlink($errorPath);

        return array($status, $output, $errors);
    }
}
