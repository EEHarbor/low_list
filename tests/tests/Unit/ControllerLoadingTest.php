<?php

namespace LowListTests;

class ControllerLoadingTest extends TestCase
{
    use NativeProbe;

    /**
     * Supply cold providers and retained loaded classes for both bridges.
     *
     * @return array
     */
    public static function contexts(): array
    {
        return array(
            array('modern-present'), array('legacy-present'),
            array('modern-empty'), array('legacy-empty'),
            array('modern-missing'), array('legacy-missing'),
            array('modern-predeclared'), array('legacy-predeclared'),
        );
    }

    /**
     * Discover existing navigation and safely omit an optional undefined provider.
     *
     * @dataProvider contexts
     * @param string $case
     * @return void
     */
    public function testOriginalControllerProviderDiscovery($case): void
    {
        try {
            list($status, $output, $errors) = $this->probe(array('controller-loading', $case));
            $this->assertSame(0, $status, $errors);
            $this->assertSame('', $errors);
            $result = json_decode($output, true);
            $this->assertIsArray($result, $output);
        } finally {
            $this->assertSame(array(), glob(PATH_CACHE . 'low-list-controller-loading-*'));
        }
        $predeclared = substr($case, -11) === 'predeclared';
        $present = substr($case, -7) === 'present' || $predeclared;
        $this->assertSame($predeclared, $result['loaded_before']);
        $this->assertSame($present, $result['loaded_after']);
        $this->assertSame('2.2.0', $result['version']);
        $this->assertSame($present ? 'LowList\\Conduit\\McpNav' : null, $result['nav_class']);
        $this->assertSame($present ? 'LowList\\FluxCapacitor\\Conduit\\McpNav' : null, $result['nav_parent']);
        $this->assertSame($present && strpos($case, 'modern-') === 0, $result['sidebar']);
        $this->assertSame(array(), $result['right_nav']);
        $hash = null;
        if ($present && !$predeclared) {
            $hash = hash_file('sha256', dirname(__DIR__, 2) . '/Fixtures/navigation/McpNav.php');
        }
        if (substr($case, -5) === 'empty') {
            $hash = hash('sha256', "<?php\n");
        }
        $this->assertSame($hash, $result['provider_hash']);
    }
}
