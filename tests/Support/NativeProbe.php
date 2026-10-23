<?php

namespace LowListTests;

trait NativeProbe
{
    /**
     * Start a child using this suite's explicit PHP binary and local settings.
     *
     * @param array $arguments
     * @param float $timeoutSeconds
     * @return array
     * @throws \RuntimeException
     * @throws NativeProbeTimeout
     */
    private function probe(array $arguments, $timeoutSeconds = 10.0)
    {
        $command = array(PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', __DIR__ . '/native-probe.php');
        $environment = getenv();
        $environment['LOW_LIST_NATIVE_MODE'] = $arguments[0];
        $environment['LOW_LIST_NATIVE_CASE'] = isset($arguments[1]) ? $arguments[1] : '';
        if ($arguments[0] === 'loader-opcache') {
            array_splice($command, 1, 0, array('-d', 'opcache.enable_cli=1', '-d', 'opcache.validate_permission=1'));
        }
        if (getenv('LOW_LIST_XDEBUG_EXTENSION')) {
            array_splice($command, 1, 0, array('-d', 'zend_extension=' . getenv('LOW_LIST_XDEBUG_EXTENSION'), '-d', 'auto_prepend_file=' . dirname(__DIR__) . '/coverage-prepend.php'));
            $environment['LOW_LIST_COVERAGE_FILE'] = getenv('LOW_LIST_COVERAGE_DIRECTORY') . '/native-' . $arguments[0] . '-' . bin2hex(random_bytes(8)) . '.json';
        }
        if (!is_dir(PATH_CACHE)) {
            mkdir(PATH_CACHE, 0700, true);
        }
        array_splice($command, 1, 0, array('-d', 'sys_temp_dir=' . PATH_CACHE));
        $environment['TMPDIR'] = PATH_CACHE;
        $outputPath = null;
        $errorPath = null;
        $process = null;
        $status = null;
        $expired = false;
        $pid = null;
        try {
            $outputPath = tempnam(PATH_CACHE, 'native-out-');
            $errorPath = tempnam(PATH_CACHE, 'native-err-');
            $process = proc_open(array_merge($command, $arguments), array(1 => array('file', $outputPath, 'w'), 2 => array('file', $errorPath, 'w')), $pipes, null, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start native source probe.');
            }
            $deadline = hrtime(true) + $timeoutSeconds * 1000000000;
            while (true) {
                $information = proc_get_status($process);
                $pid = $information['pid'];
                if (!$information['running']) {
                    $status = $information['exitcode'];
                    break;
                }
                if (hrtime(true) >= $deadline) {
                    $expired = true;
                    break;
                }
                usleep(10000);
            }
        } finally {
            if (is_resource($process)) {
                $information = proc_get_status($process);
                if ($information['running']) {
                    proc_terminate($process, 9);
                }
                $closedStatus = proc_close($process);
                if ($status === null || $status < 0) {
                    $status = $closedStatus;
                }
            }
            $output = $outputPath === null ? '' : file_get_contents($outputPath);
            $errors = $errorPath === null ? '' : file_get_contents($errorPath);
            if ($outputPath !== null) {
                unlink($outputPath);
            }
            if ($errorPath !== null) {
                unlink($errorPath);
            }
        }
        if ($expired) {
            throw new NativeProbeTimeout($pid, $output, $errors);
        }

        return array($status, $output, $errors);
    }
}


class NativeProbeTimeout extends \RuntimeException
{
    public $pid;
    public $output;
    public $errors;

    /**
     * Preserve the timed-out child's identity and captured diagnostics after cleanup.
     *
     * @param int $pid
     * @param string $output
     * @param string $errors
     * @return void
     */
    public function __construct($pid, $output, $errors)
    {
        parent::__construct('The owned native source probe exceeded its deadline.');
        $this->pid = $pid;
        $this->output = $output;
        $this->errors = $errors;
    }
}
