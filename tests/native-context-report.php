<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

require __DIR__ . '/inventory.php';
require_once __DIR__ . '/Support/Coverage.php';
$inventory = sourceInventory();
$reportDirectory = $argv[1];
$mode = isset($argv[2]) ? $argv[2] : 'navigation';
$configurations = array(
    'controller-loading' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Mcp.php',
        'cases' => array('modern-present' => '7.5.0', 'legacy-present' => '2.5.5', 'modern-empty' => '7.5.0', 'legacy-empty' => '2.5.5', 'modern-missing' => '7.5.0', 'legacy-missing' => '2.5.5', 'modern-predeclared' => '7.5.0', 'legacy-predeclared' => '2.5.5'),
    ),
    'bootstrap' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Bridge/EE.php',
        'cases' => array('configured' => '2.5.5', 'defined-legacy' => '2.5.5', 'defined-modern' => '7.5.0'),
        'request' => 'PAGE',
    ),
    'fieldtype-loading' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Ft.php',
        'cases' => array('system' => '7.5.0', 'app' => '7.5.0', 'priority' => '7.5.0', 'installer' => '7.5.0', 'installer-ancestor' => '7.5.0', 'predeclared' => '7.5.0', 'defined-version' => '7.5.0'),
        'request' => 'PAGE',
        'line_only_cases' => array('installer-ancestor'),
    ),
    'navigation' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Conduit/McpNav.php',
        'cases' => array('modern' => '7.5.0', 'legacy' => '2.5.5', 'http-route' => '7.5.0'),
    ),
    'fieldtype' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Ft.php',
        'cases' => array('legacy' => '2.5.5', 'legacy-structured' => '2.5.5'),
        'request' => 'PAGE',
    ),
    'legacy-bridge' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Bridge/EE2.php',
        'cases' => array('urls' => '2.5.5', 'view-writable' => '2.5.5', 'view-readonly' => '2.5.5', 'slashes' => '2.5.4', 'slashes-helper' => '2.10.1'),
    ),
    'cp-fieldtype' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Ft.php',
        'capture_mode' => 'control-panel',
        'cases' => array('fieldtype-modern' => '7.5.0', 'fieldtype-legacy' => '2.5.5', 'shared-modern' => '7.5.0', 'shared-legacy' => '2.5.5'),
    ),
    'cp-tab' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Tab.php',
        'capture_mode' => 'control-panel',
        'cases' => array('tab-modern' => '7.5.0', 'tab-legacy' => '2.5.5', 'shared-modern' => '7.5.0', 'shared-legacy' => '2.5.5'),
    ),
    'cp-menu' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Ext.php',
        'capture_mode' => 'control-panel',
        'cases' => array('menu-modern' => '7.5.0', 'menu-legacy' => '2.5.5'),
    ),
    'cp-updater' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Base/Upd.php',
        'capture_mode' => 'control-panel',
        'cases' => array('updater-present' => '7.5.0', 'updater-empty' => '7.5.0', 'updater-missing' => '7.5.0'),
    ),
);
if (!isset($configurations[$mode])) {
    throw new RuntimeException('Unknown native source context.');
}
$configuration = $configurations[$mode];
$parent = json_decode(file_get_contents($reportDirectory . '/xdebug.json'), true);
if (!is_array($parent) || !isset($parent['php'], $parent['xdebug'])) {
    throw new RuntimeException('Fresh parent instrumentation metadata is required.');
}
$relative = $configuration['source'];
$native = 'phar://' . dirname(__DIR__) . '/' . $relative;
$entry = $inventory['files'][$relative];
if (hash_file('sha256', $native) !== $entry['sha256']) {
    throw new RuntimeException('Native source provenance mismatch.');
}
$contexts = array();
$seen = array();
$captureMode = isset($configuration['capture_mode']) ? $configuration['capture_mode'] : $mode;
$allowedCases = $configuration['cases'];
if ($captureMode === 'control-panel') {
    foreach ($configurations as $candidate) {
        if (isset($candidate['capture_mode']) && $candidate['capture_mode'] === $captureMode) {
            $allowedCases = array_merge($allowedCases, $candidate['cases']);
        }
    }
}
// Prefer the longest family name so fieldtype-loading stays separate from fieldtype.
$captureModes = array();
foreach ($configurations as $name => $candidate) {
    $captureModes[] = isset($candidate['capture_mode']) ? $candidate['capture_mode'] : $name;
}
$captureModes = array_unique($captureModes);
usort($captureModes, static function ($left, $right) { return strlen($right) - strlen($left); });
foreach (glob($reportDirectory . '/native-*.json') as $path) {
    $fileMode = null;
    foreach ($captureModes as $candidateMode) {
        if (strpos(basename($path), 'native-' . $candidateMode . '-') === 0) {
            $fileMode = $candidateMode;
            break;
        }
    }
    if ($fileMode !== $captureMode) {
        continue;
    }
    $capture = json_decode(file_get_contents($path), true);
    if (!is_array($capture) || !isset($capture['php'], $capture['xdebug'], $capture['context']['native_mode'], $capture['context']['native_case'], $capture['context']['REQ'], $capture['context']['APP_VER'], $capture['runtime_declarations']) || !is_array($capture['runtime_declarations'])) {
        throw new RuntimeException('Native source metadata is required.');
    }
    $case = $capture['context']['native_case'];
    if ($capture['context']['native_mode'] !== $captureMode || isset($seen[$case]) || !isset($allowedCases[$case])) {
        throw new RuntimeException('Unexpected or duplicate native source context.');
    }
    $seen[$case] = true;
    if ($capture['php'] !== $parent['php'] || $capture['xdebug'] !== $parent['xdebug']) {
        throw new RuntimeException('Missing or incompatible native source instrumentation.');
    }
    if ($capture['context']['REQ'] !== (isset($configuration['request']) ? $configuration['request'] : 'CP') || $capture['context']['APP_VER'] !== $allowedCases[$case]) {
        throw new RuntimeException('Unexpected native source request context.');
    }
    if (!isset($configuration['cases'][$case])) {
        continue;
    }
    if (!isset($capture['coverage'][$native])) {
        throw new RuntimeException('Missing or incompatible native source instrumentation.');
    }
    $source = $capture['coverage'][$native];
    $executable = array_filter($source['lines'], static function ($hit) { return $hit !== -2; });
    $methods = array();
    $observed = false;
    $hasGraph = false;
    foreach ($source['functions'] as $name => $function) {
        $branches = $function['branches'];
        $hasGraph = $hasGraph || (bool) $branches;
        foreach ($branches as $branch) {
            $observed = $observed || $branch['hit'] > 0;
        }
        $attribution = \LowListTests\Coverage::attribution($native, $name, $branches, $capture['runtime_declarations']);
        $declaration = $attribution['declaration'];
        $lines = $attribution['status'] !== 'matched' || $declaration === null || $declaration['file'] !== $native ? null : array_filter($executable, static function ($line) use ($declaration) {
            return $line >= $declaration['line_start'] && $line <= $declaration['line_end'];
        }, ARRAY_FILTER_USE_KEY);
        $methods[$name] = array(
            'attribution' => $attribution,
            'lines_hit' => $lines === null ? null : count(array_filter($lines, static function ($hit) { return $hit > 0; })),
            'lines' => $lines === null ? null : count($lines),
            'basic_blocks_hit' => count(array_filter($branches, static function ($branch) { return $branch['hit'] > 0; })),
            'basic_blocks' => count($branches),
        );
        // Full-path enumeration remains in the original capture, outside this summary.
        unset($source['functions'][$name]['paths']);
    }
    $linesHit = count(array_filter($executable, static function ($hit) { return $hit > 0; }));
    // The known predeclaration fatal executes source lines without hit procedural blocks.
    $lineOnly = isset($configuration['line_only_cases'])
        && in_array($case, $configuration['line_only_cases'], true)
        && $hasGraph
        && $linesHit > 0;
    if ((!$observed && !$lineOnly) || !$executable) {
        throw new RuntimeException('Empty native source instrumentation cannot count as success.');
    }
    $contexts[$case] = array(
        'capture' => basename($path),
        'context' => $capture['context'],
        'lines_hit' => $linesHit,
        'lines' => count($executable),
        'methods' => $methods,
        'source_data' => $source,
    );
    if (!$observed) {
        $contexts[$case]['branch_observation'] = 'unknown_no_hit_native_graph';
    }
}
if (count($contexts) !== count($configuration['cases'])) {
    throw new RuntimeException('All configured fresh native contexts are required.');
}
ksort($contexts);
$report = array(
    'baseline' => $inventory['baseline'],
    'archive_sha256' => $inventory['archive_sha256'],
    'source' => $relative,
    'source_sha256' => $entry['sha256'],
    'runtime' => $parent['php'],
    'xdebug' => $parent['xdebug'],
    'metrics' => 'Independent native contexts; raw basic blocks and out/out_hit exits are preserved without graph merging.',
    'contexts' => $contexts,
);
file_put_contents($reportDirectory . '/native-' . $mode . '.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
