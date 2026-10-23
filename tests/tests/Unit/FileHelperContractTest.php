<?php

namespace LowListTests;

class FileHelperContractTest extends TestCase
{
    /**
     * Match EE's missing-file return and binary/truncate/append write behavior.
     *
     * @return void
     */
    public function testNativeFileLifecycle(): void
    {
        mkdir(PATH_CACHE, DIR_WRITE_MODE);
        $path = PATH_CACHE . 'helper-data';
        $this->assertFalse(read_file($path));
        $this->assertTrue(write_file($path, "binary\0data"));
        $this->assertSame("binary\0data", read_file($path));
        $this->assertTrue(write_file($path, 'short'));
        $this->assertSame('short', read_file($path));
        $this->assertTrue(write_file($path, '-append', 'ab'));
        $this->assertSame('short-append', read_file($path));
    }

    /**
     * Preserve the real helper's native warning for an existing directory.
     *
     * @return void
     */
    public function testReadDirectoryReachesNativeDiagnostic(): void
    {
        mkdir(PATH_CACHE, DIR_WRITE_MODE);
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('file_get_contents');

        read_file(PATH_CACHE);
    }

    /**
     * Preserve diagnostics from EE's suppressed native file-open operation.
     *
     * @return void
     */
    public function testWriteDirectoryReachesNativeDiagnostic(): void
    {
        mkdir(PATH_CACHE, DIR_WRITE_MODE);
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('fopen');

        write_file(PATH_CACHE, 'data');
    }
}
