<?php

namespace LowListTests;

final class PharSessionProbe
{
    /**
     * Prove owned native session storage before the original cold PHAR fallback.
     *
     * @param string $root
     * @param string $case
     * @return void
     * @throws \RuntimeException
     */
    public static function run($root, $case)
    {
        $cases = array('writable', 'depth', 'mode', 'empty', 'readonly', 'file-temp', 'missing-session', 'file-session');
        if (!in_array($case, $cases, true)) {
            throw new \RuntimeException('Unsupported session fallback request.');
        }
        $owned = sys_get_temp_dir() . '/low-list-session-' . bin2hex(random_bytes(8));
        mkdir($owned, 0700);
        $originalDirectory = getcwd();
        $sessionFile = null;
        $externalExtraction = null;
        $temporary = $owned . '/temporary';
        $result = array('case' => $case, 'owned_root' => $owned, 'native_session' => null, 'returned' => false);
        $buffer = ob_get_level();
        ob_start();
        register_shutdown_function(static function () use (&$result, &$sessionFile, &$externalExtraction, $temporary, $owned, $originalDirectory, $buffer) {
            $result['diagnostic'] = ob_get_contents();
            $result['source_temp'] = class_exists('Extract_low_list', false) ? \Extract_low_list::$temp : null;
            $result['boundary_contents'] = is_file($temporary) ? file_get_contents($temporary) : null;
            $sessionBoundary = $owned . '/session-file';
            $result['session_boundary_contents'] = is_file($sessionBoundary) ? file_get_contents($sessionBoundary) : null;
            while (ob_get_level() > $buffer) {
                ob_end_clean();
            }
            chdir($originalDirectory);
            if (is_dir($temporary)) {
                chmod($temporary, 0700);
            }
            if ($sessionFile !== null && is_file($sessionFile)) {
                unlink($sessionFile);
            }
            if ($externalExtraction !== null && is_dir($externalExtraction)) {
                self::removeOwnedTree($externalExtraction);
            }
            $result['external_extraction_root'] = $externalExtraction;
            self::removeOwnedTree($owned);
            $result['cleaned'] = !file_exists($owned);
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
        });
        $sessionRoot = $owned . '/sessions';
        mkdir($sessionRoot, 0700);
        $path = $sessionRoot;
        $depth = 0;
        if ($case === 'depth' || $case === 'mode') {
            $depth = 2;
            $path = '2;' . ($case === 'mode' ? '0600;' : '') . $sessionRoot;
        }
        if ($case === 'empty') {
            $path = '';
            $candidate = sys_get_temp_dir() . '/pharextract';
            if (file_exists($candidate) || is_link($candidate)) {
                throw new \RuntimeException('The default extraction root must be absent before this owned request.');
            }
            $externalExtraction = $candidate;
        }
        if ($case === 'missing-session') {
            $path = $owned . '/absent-session';
        }
        if ($case === 'file-session') {
            $path = $owned . '/session-file';
            file_put_contents($path, 'preserved-session-boundary');
        }
        if ($case === 'file-temp') {
            file_put_contents($temporary, 'preserved-temp-boundary');
        } else {
            mkdir($temporary, 0700);
            if ($case !== 'writable') {
                chmod($temporary, 0500);
            }
        }
        clearstatcache();
        if (is_writable($temporary) !== ($case === 'writable' || $case === 'file-temp')) {
            throw new \RuntimeException('The owned temporary permission boundary is unavailable.');
        }
        ini_set('session.save_handler', 'files');
        ini_set('session.serialize_handler', 'php');
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        ini_set('session.gc_probability', '0');
        session_save_path($path);
        $result['session_save_path'] = session_save_path();
        $result['expected_base'] = $case === 'writable' ? $temporary : ($case === 'empty' ? sys_get_temp_dir() : $sessionRoot);
        if ($case !== 'missing-session' && $case !== 'file-session') {
            $sessionId = bin2hex(random_bytes(16));
            $storage = $case === 'empty' ? sys_get_temp_dir() : $sessionRoot;
            for ($index = 0; $index < $depth; $index++) {
                $storage .= '/' . $sessionId[$index];
                mkdir($storage, 0700);
            }
            $sessionFile = $storage . '/sess_' . $sessionId;
            session_id($sessionId);
            if (!session_start()) {
                throw new \RuntimeException('The native files session did not start.');
            }
            $_SESSION = array('owned' => 'native');
            if (!session_write_close()) {
                throw new \RuntimeException('The native files session did not close.');
            }
            clearstatcache();
            $result['native_session'] = array(
                'handler' => session_module_name(),
                'configured_path' => session_save_path(),
                'file' => $sessionFile,
                'mode' => fileperms($sessionFile) & 0777,
                'contents' => file_get_contents($sessionFile),
            );
        }
        unset($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
        define('APP_VER', '7.5.0');
        define('REQ', 'PAGE');
        putenv('TMPDIR=' . $temporary);
        if (!stream_wrapper_unregister('phar')) {
            throw new \RuntimeException('The child PHAR wrapper was not removed.');
        }
        require $root . '/low_list/low_list.phar';
        $result['returned'] = true;
    }

    /**
     * Remove only this child's tree, including hidden files and dangling links.
     *
     * @param string $path
     * @return void
     */
    private static function removeOwnedTree($path)
    {
        foreach (new \FilesystemIterator($path) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                self::removeOwnedTree($entry->getPathname());
                continue;
            }
            unlink($entry->getPathname());
        }
        rmdir($path);
    }
}
