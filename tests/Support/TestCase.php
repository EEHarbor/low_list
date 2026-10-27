<?php

namespace LowListTests;

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
    }

    /**
     * Restore process state and remove only this process's cache files.
     *
     * @return void
     */
    protected function tearDown(): void
    {
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

}
