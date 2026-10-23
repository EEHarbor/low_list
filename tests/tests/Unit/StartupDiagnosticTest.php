<?php

namespace LowListTests;

class StartupDiagnosticTest extends TestCase
{
    use NativeProbe;

    /**
     * Stop startup diagnostics before its first dependency, including suppressed ones.
     *
     * @dataProvider entryDiagnostics
     * @param string $entry
     * @param string $diagnostic
     * @return void
     */
    public function testEarlyEntryHandler($entry, $diagnostic): void
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $entry);
        $firstDependency = strpos($contents, 'require');
        $this->assertNotFalse($firstDependency);
        $prefix = substr($contents, 0, $firstDependency);
        $owned = PATH_CACHE . 'startup-' . bin2hex(random_bytes(8));
        mkdir($owned, 0700, true);
        $fixture = $owned . '/dependency.php';
        $guarded = $owned . '/guarded.php';
        $unguarded = $owned . '/unguarded.php';
        $marker = $owned . '/continued';
        $fault = $diagnostic === 'warning'
            ? '@fopen(' . var_export($owned . '/absent', true) . ", 'rb');"
            : "@trigger_error('suppressed startup deprecation', E_USER_DEPRECATED);";
        try {
            file_put_contents($fixture, '<?php ' . $fault . " file_put_contents(\$argv[1], 'continued');");
            $load = 'require ' . var_export($fixture, true) . ';';
            file_put_contents($guarded, $prefix . $load);
            file_put_contents($unguarded, $prefix . 'restore_error_handler(); ' . $load);
            list($status, $output, $errors) = $this->probe(array($marker), 10.0, $guarded);
            $this->assertSame(255, $status, $errors);
            $this->assertSame('', $output);
            $this->assertStringContainsString('Uncaught ErrorException:', $errors);
            $this->assertStringContainsString($diagnostic === 'warning' ? 'fopen(' : 'suppressed startup deprecation', $errors);
            $this->assertFileDoesNotExist($marker);

            list($status, $output, $errors) = $this->probe(array($marker), 10.0, $unguarded);
            $this->assertSame(0, $status, $errors);
            $this->assertSame('', $output);
            $this->assertStringNotContainsString('ErrorException', $errors);
            $this->assertSame('continued', file_get_contents($marker));
        } finally {
            foreach (array($fixture, $guarded, $unguarded, $marker) as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            rmdir($owned);
        }
        $this->assertDirectoryDoesNotExist($owned);
    }

    /**
     * Check both actual entry prefixes against native warnings and deprecations.
     *
     * @return array
     */
    public static function entryDiagnostics(): array
    {
        return array(
            'bootstrap native warning' => array('bootstrap.php', 'warning'),
            'bootstrap deprecation' => array('bootstrap.php', 'deprecation'),
            'coverage native warning' => array('coverage-prepend.php', 'warning'),
            'coverage deprecation' => array('coverage-prepend.php', 'deprecation'),
        );
    }
}
