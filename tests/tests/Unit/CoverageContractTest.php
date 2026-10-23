<?php

namespace LowListTests;

require_once dirname(__DIR__, 2) . '/Support/Coverage.php';

class CoverageContractTest extends TestCase
{
    /**
     * Observe loaded declarations without loading classes or changing autoloaders.
     *
     * @return void
     */
    public function testLoadedDeclarationProvenance(): void
    {
        $root = dirname(__DIR__, 3) . '/low_list/';
        $method = new \ReflectionMethod('LowList\\FluxCapacitor\\Bridge\\EE', '__construct');
        $beforeClasses = get_declared_classes();
        $beforeLoaders = spl_autoload_functions();
        $declarations = Coverage::declarations($root);
        $this->assertSame($beforeClasses, get_declared_classes());
        $this->assertSame($beforeLoaders, spl_autoload_functions());
        $this->assertArrayNotHasKey('ee', $declarations);
        $name = 'LowList\\FluxCapacitor\\Bridge\\EE->__construct';
        $this->assertSame(array('file' => $method->getFileName(), 'line_start' => $method->getStartLine(), 'line_end' => $method->getEndLine()), $declarations[$name]);
        $this->assertArrayNotHasKey('LowList\\FluxCapacitor\\Bridge\\EE->getBaseURL', $declarations);
        $closures = array_filter(array_keys($declarations), static function ($name) use ($root) {
            return strpos($name, '{closure:' . $root . 'autoload.php:') === 0;
        });
        $this->assertCount(1, $closures);
        $this->assertFalse(Coverage::belongsToSource(false, $root));
        $this->assertFalse(Coverage::belongsToSource(rtrim($root, '/') . '-other/source.php', $root));
        $this->assertFalse(Coverage::belongsToSource(__FILE__, $root));
    }

    /**
     * Reject graph ranges from another conditional declaration as method evidence.
     *
     * @return void
     */
    public function testRuntimeRangeAttributionAndUnknowns(): void
    {
        $name = 'LowList\\FluxCapacitor\\Bridge\\EE->__construct';
        $root = dirname(__DIR__, 3) . '/low_list/';
        new \ReflectionMethod('LowList\\FluxCapacitor\\Bridge\\EE', '__construct');
        $declarations = Coverage::declarations($root);
        $declaration = $declarations[$name];
        $file = $declaration['file'];
        $nativeRange = array(array('line_start' => $declaration['line_start'], 'line_end' => $declaration['line_end']));
        $this->assertSame('matched', Coverage::attribution($file, $name, $nativeRange, $declarations)['status']);
        $inventory = json_decode(file_get_contents(dirname(__DIR__, 2) . '/source-inventory.json'), true);
        $other = $inventory['files']['low_list/low_list.phar/FluxCapacitor/Bridge/EE.php']['functions'][1];
        $wrongRange = array(array('line_start' => $other['start'], 'line_end' => $other['end']));
        $this->assertSame('graph_outside_runtime_range', Coverage::attribution($file, $name, $wrongRange, $declarations)['reason']);
        $this->assertSame('different_runtime_source', Coverage::attribution(__FILE__, $name, $nativeRange, $declarations)['reason']);
        $this->assertSame('unknown', Coverage::attribution($file, '{main}', $nativeRange, $declarations)['status']);
        $this->assertSame(array('status' => 'unknown', 'reason' => 'no_native_graph', 'declaration' => $declaration), Coverage::attribution($file, $name, array(), $declarations));
    }
}
