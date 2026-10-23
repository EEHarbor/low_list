<?php

require __DIR__ . '/inventory.php';
require_once __DIR__ . '/Support/Coverage.php';
$inventory = sourceInventory();
$reportDirectory = $argv[1];
$mode = isset($argv[2]) ? $argv[2] : 'navigation';
$configurations = array(
    'navigation' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Conduit/McpNav.php',
        'cases' => array('modern' => '7.5.0', 'legacy' => '2.5.5', 'http-route' => '7.5.0'),
    ),
    'legacy-bridge' => array(
        'source' => 'low_list/low_list.phar/FluxCapacitor/Bridge/EE2.php',
        'cases' => array('urls' => '2.5.5', 'view-writable' => '2.5.5', 'view-readonly' => '2.5.5'),
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
foreach (glob($reportDirectory . '/native-' . $mode . '-*.json') as $path) {
    $capture = json_decode(file_get_contents($path), true);
    if (!is_array($capture) || !isset($capture['php'], $capture['xdebug'], $capture['context']['native_mode'], $capture['context']['native_case'], $capture['context']['REQ'], $capture['context']['APP_VER'], $capture['runtime_declarations']) || !is_array($capture['runtime_declarations'])) {
        throw new RuntimeException('Native source metadata is required.');
    }
    $case = $capture['context']['native_case'];
    if ($capture['context']['native_mode'] !== $mode || isset($contexts[$case]) || !isset($configuration['cases'][$case])) {
        throw new RuntimeException('Unexpected or duplicate native source context.');
    }
    if ($capture['php'] !== $parent['php'] || $capture['xdebug'] !== $parent['xdebug'] || !isset($capture['coverage'][$native])) {
        throw new RuntimeException('Missing or incompatible native source instrumentation.');
    }
    if ($capture['context']['REQ'] !== 'CP' || $capture['context']['APP_VER'] !== $configuration['cases'][$case]) {
        throw new RuntimeException('Unexpected native source request context.');
    }
    $source = $capture['coverage'][$native];
    $executable = array_filter($source['lines'], static function ($hit) { return $hit !== -2; });
    $methods = array();
    $observed = false;
    foreach ($source['functions'] as $name => $function) {
        $branches = $function['branches'];
        foreach ($branches as $branch) {
            $observed = $observed || $branch['hit'] > 0;
        }
        $attribution = \LowListTests\Coverage::attribution($native, $name, $branches, $capture['runtime_declarations']);
        $declaration = $attribution['declaration'];
        $lines = $declaration === null || $declaration['file'] !== $native ? null : array_filter($executable, static function ($line) use ($declaration) {
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
    if (!$observed || !$executable) {
        throw new RuntimeException('Empty native source instrumentation cannot count as success.');
    }
    $contexts[$case] = array(
        'capture' => basename($path),
        'context' => $capture['context'],
        'lines_hit' => count(array_filter($executable, static function ($hit) { return $hit > 0; })),
        'lines' => count($executable),
        'methods' => $methods,
        'source_data' => $source,
    );
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
