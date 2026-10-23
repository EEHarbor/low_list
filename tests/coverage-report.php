<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

require __DIR__ . '/inventory.php';
require_once __DIR__ . '/Support/Coverage.php';
$inventory = sourceInventory();
$reportDirectory = $argv[1];
$instrumentation = json_decode(file_get_contents($reportDirectory . '/xdebug.json'), true);
if (!$instrumentation['coverage']) {
    throw new RuntimeException('Empty coverage cannot count as success.');
}
$root = dirname(__DIR__);
$measurement = __DIR__ . '/.coverage-source';
$archive = new Phar($root . '/low_list/low_list.phar');
$xml = new SimpleXMLElement('<coverage><project/></coverage>');
$summary = array();
$totals = array('lines_hit' => 0, 'lines' => 0, 'basic_blocks_hit' => 0, 'basic_blocks' => 0);
foreach ($inventory['files'] as $relative => $entry) {
    $archivePrefix = 'low_list/low_list.phar/';
    if ($relative === 'low_list/low_list.phar#stub') {
        $native = $root . '/low_list/low_list.phar';
        $source = $archive->getStub();
        $target = $measurement . '/stub.php';
    } else {
        $native = strpos($relative, $archivePrefix) === 0 ? 'phar://' . $root . '/' . $relative : $root . '/' . $relative;
        $source = file_get_contents($native);
        $target = strpos($relative, $archivePrefix) === 0 ? $measurement . '/archive/' . substr($relative, strlen($archivePrefix)) : $native;
    }
    if (hash('sha256', $source) !== $entry['sha256']) {
        throw new RuntimeException('Source provenance mismatch: ' . $relative);
    }
    if ($target !== $native) {
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        file_put_contents($target, $source);
        if (hash_file('sha256', $target) !== $entry['sha256']) {
            throw new RuntimeException('Measurement copy mismatch: ' . $relative);
        }
    }
    $data = isset($instrumentation['coverage'][$native]) ? $instrumentation['coverage'][$native] : array('lines' => array(), 'functions' => array());
    $lines = $data['lines'];
    $executable = array_filter($lines, static function ($hit) { return $hit !== -2; });
    $hitLines = array_filter($executable, static function ($hit) { return $hit > 0; });
    $functions = array();
    $fileBranches = 0;
    $fileBranchesHit = 0;
    foreach ($data['functions'] as $name => $function) {
        $branches = $function['branches'];
        $hit = count(array_filter($branches, static function ($branch) { return $branch['hit'] > 0; }));
        $uncovered = array();
        foreach ($branches as $id => $branch) {
            if (!$branch['hit']) {
            $uncovered[] = array('id' => $id, 'line_start' => $branch['line_start'], 'line_end' => $branch['line_end']);
            }
        }
        $edges = array();
        foreach ($branches as $id => $branch) {
            foreach ($branch['out'] as $edge => $destination) {
                $edges[] = array('branch' => $id, 'destination' => $destination, 'hit' => $branch['out_hit'][$edge]);
            }
        }
        $attribution = \LowListTests\Coverage::attribution($native, $name, $branches, isset($instrumentation['runtime_declarations']) ? $instrumentation['runtime_declarations'] : array());
        $functions[$name] = array('basic_blocks_hit' => $hit, 'basic_blocks' => count($branches), 'attribution' => $attribution, 'declared_basic_blocks_hit' => $attribution['status'] === 'matched' ? $hit : null, 'declared_basic_blocks' => $attribution['status'] === 'matched' ? count($branches) : null, 'uncovered' => $uncovered, 'edges' => $edges, 'graph' => $branches);
        $fileBranches += count($branches);
        $fileBranchesHit += $hit;
    }
    $summary[$relative] = array('sha256' => $entry['sha256'], 'compiled_in_main_process' => isset($instrumentation['coverage'][$native]), 'lines_hit' => count($hitLines), 'lines' => count($executable), 'uncovered_lines' => array_keys(array_filter($executable, static function ($hit) { return $hit < 1; })), 'basic_blocks_hit' => $fileBranchesHit, 'basic_blocks' => $fileBranches, 'functions' => $functions);
    $totals['lines_hit'] += count($hitLines);
    $totals['lines'] += count($executable);
    $totals['basic_blocks_hit'] += $fileBranchesHit;
    $totals['basic_blocks'] += $fileBranches;
    $file = $xml->project->addChild('file');
    $file->addAttribute('name', $target);
    foreach ($executable as $number => $hit) {
        $line = $file->addChild('line');
        $line->addAttribute('num', $number);
        $line->addAttribute('type', 'stmt');
        $line->addAttribute('count', max(0, $hit));
    }
}
$xml->asXML($reportDirectory . '/clover.xml');
file_put_contents($reportDirectory . '/branch-report.json', json_encode(array('baseline' => $inventory['baseline'], 'archive_sha256' => $inventory['archive_sha256'], 'runtime' => $instrumentation['php'], 'xdebug' => $instrumentation['xdebug'], 'context' => isset($instrumentation['context']) ? $instrumentation['context'] : null, 'totals_compiled_only' => $totals, 'files' => $summary), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$uncompiled = array_keys(array_filter($summary, static function ($file) { return !$file['compiled_in_main_process']; }));
$text = sprintf("Main-process native Xdebug: %d/%d lines, %d/%d basic blocks.\nUncompiled shipped files: %d. These are unmeasured, never 100%%.\nDecision exits are retained separately; basic-block percentages do not establish branch closure.\n", $totals['lines_hit'], $totals['lines'], $totals['basic_blocks_hit'], $totals['basic_blocks'], count($uncompiled));
file_put_contents($reportDirectory . '/summary.txt', $text);
