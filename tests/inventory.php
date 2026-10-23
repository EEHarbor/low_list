<?php

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL);

/**
 * Inventory shipped PHP, retaining archive provenance and anonymous functions.
 *
 * @return array
 */
function sourceInventory()
{
    $root = dirname(__DIR__);
    $sources = array();
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/low_list', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink() || $file->getExtension() !== 'php') {
            continue;
        }
        $sources[substr($file->getPathname(), strlen($root) + 1)] = file_get_contents($file->getPathname());
    }
    $archive = new Phar($root . '/low_list/low_list.phar');
    $archivePrefix = 'phar://' . $root . '/low_list/low_list.phar/';
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        if ($file->getExtension() === 'php') {
            $sources['low_list/low_list.phar/' . substr($file->getPathname(), strlen($archivePrefix))] = $file->getContent();
        }
    }
    $sources['low_list/low_list.phar#stub'] = $archive->getStub();
    ksort($sources);
    $result = array();
    foreach ($sources as $path => $source) {
        $tokens = token_get_all($source);
        $functions = array();
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            $name = '{closure}';
            $start = $token[2];
            $line = $start;
            $depth = 0;
            $opened = false;
            $signatureStarted = false;
            for ($i = $index + 1; $i < count($tokens); $i++) {
                $part = $tokens[$i];
                $text = is_array($part) ? $part[1] : $part;
                if (!$signatureStarted && is_array($part) && $part[0] === T_STRING) {
                    $name = $part[1];
                    $signatureStarted = true;
                }
                if ($text === '(') {
                    $signatureStarted = true;
                }
                $line += substr_count($text, "\n");
                if ($text === '{') {
                    $opened = true;
                    $depth++;
                }
                if ($text === '}') {
                    $depth--;
                }
                if (($opened && $depth === 0) || (!$opened && $text === ';')) {
                    break;
                }
            }
            $functions[] = array('name' => $name, 'start' => $start, 'end' => $line);
        }
        $result[$path] = array('sha256' => hash('sha256', $source), 'bytes' => strlen($source), 'functions' => $functions);
    }

    return array('baseline' => '86e4da6b2e585467b9c068e55f0190e69107fe4f', 'archive_sha256' => hash_file('sha256', $root . '/low_list/low_list.phar'), 'files' => $result);
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    echo json_encode(sourceInventory(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
}
