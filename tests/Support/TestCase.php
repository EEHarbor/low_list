<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\StaticCache;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private $includePath;
    private $workingDirectory;
    private $encryptionLog;

    /**
     * Install strict diagnostics, including errors hidden with @.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->includePath = get_include_path();
        $this->workingDirectory = getcwd();
        $this->encryptionLog = array(array_key_exists('low_list_encryptions', $GLOBALS), isset($GLOBALS['low_list_encryptions']) ? $GLOBALS['low_list_encryptions'] : null);
        $GLOBALS['low_list_encryptions'] = array();
        $GLOBALS['low_list_ee'] = new Environment();
        $GLOBALS['low_list_helpers'] = array();
        set_error_handler(static function ($severity, $message, $file, $line) {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        }, E_ALL);
        StaticCache::clear();
    }

    /**
     * Restore process state and remove only this process's cache files.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        StaticCache::clear();
        restore_error_handler();
        set_include_path($this->includePath);
        chdir($this->workingDirectory);
        $this->removeDirectory(PATH_CACHE);
        if ($this->encryptionLog[0]) {
            $GLOBALS['low_list_encryptions'] = $this->encryptionLog[1];
        } else {
            unset($GLOBALS['low_list_encryptions']);
        }
        unset($GLOBALS['low_list_ee'], $GLOBALS['low_list_helpers']);
    }

    /**
     * Remove this suite's isolated temporary tree without following symlinks.
     *
     * @param string $path
     * @return void
     */
    private function removeDirectory($path)
    {
        if (!is_dir($path) || is_link($path)) {
            return;
        }
        foreach (new \FilesystemIterator($path) as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                $this->removeDirectory($entry->getPathname());
                continue;
            }
            unlink($entry->getPathname());
        }
        rmdir($path);
    }

    /**
     * Instantiate a library unit without unrelated constructor effects.
     *
     * @param string $class
     * @return object
     */
    protected function withoutConstructor($class)
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }

    /**
     * Invoke a protected compatibility method with its original arguments.
     *
     * @param object $object
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    protected function invoke($object, $method, array $arguments = array())
    {
        $reflection = new \ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }

        return $reflection->invokeArgs($object, $arguments);
    }
}
