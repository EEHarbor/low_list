<?php

namespace LowListTests;

class PharStubTest extends TestCase
{
    /**
     * Parse and extract every original manifest entry without rebuilding the archive.
     *
     * @return void
     */
    public function testOriginalManifestAndEntryContents(): void
    {
        $archive = new \Phar(PATH_THIRD . 'low_list/low_list.phar');
        $stream = fopen(PATH_THIRD . 'low_list/low_list.phar', 'rb');
        try {
            fseek($stream, \Extract_low_list::LEN);
            $length = unpack('V', fread($stream, 4));
            $manifest = fread($stream, $length[1]);
            $entries = \Extract_low_list::_unpack($manifest);
            $start = ftell($stream);
            $this->assertCount(count($archive), $entries['m']);
            $this->assertSame(0, $entries['c']);
            foreach ($entries['m'] as $path => $entry) {
                $file = $archive[$path];
                $this->assertSame($file->getSize(), $entry[0], $path);
                $this->assertSame($file->getMTime(), $entry[1], $path);
                $this->assertSame($file->getCompressedSize(), $entry[2], $path);
                $this->assertSame(sprintf('%u', $file->getCRC32()), $entry[3], $path);
                $this->assertSame($start + $entry[7], ftell($stream), $path);
                $this->assertSame($file->getContent(), \Extract_low_list::extractFile($path, $entry, $stream), $path);
            }
            $this->assertSame($start + array_sum(array_column($entries['m'], 2)), ftell($stream));
        } finally {
            fclose($stream);
        }
    }

    /**
     * Skip manifest alias/metadata bytes and retain file offsets and compression flags.
     *
     * @return void
     */
    public function testManifestAliasMetadataAndOffsets(): void
    {
        $alias = 'fixture-alias';
        $metadata = serialize(array('fixture' => 'global'));
        $fileMetadata = serialize(array('fixture' => 'entry'));
        $first = $this->entry('first value', gzdeflate('first value'), \Extract_low_list::GZ);
        $second = $this->entry('second value', bzcompress('second value'), \Extract_low_list::BZ2);
        $first[5] = strlen($fileMetadata);
        $source = fopen(PATH_THIRD . 'low_list/low_list.phar', 'rb');
        try {
            fseek($source, \Extract_low_list::LEN + 8);
            $versionAndFlags = fread($source, 6);
        } finally {
            fclose($source);
        }
        $manifest = pack('V', 2) . $versionAndFlags . pack('V', strlen($alias)) . $alias
            . pack('V', strlen($metadata)) . $metadata;
        foreach (array('nested/first.txt' => $first, 'second.txt' => $second) as $path => $entry) {
            $manifest .= pack('V', strlen($path)) . $path
                . pack('V6', $entry[0], $entry[1], $entry[2], $entry[3], $entry[4], $entry[5]);
            if ($entry[5]) {
                $manifest .= $fileMetadata;
            }
        }

        $parsed = \Extract_low_list::_unpack($manifest);

        $this->assertSame(array('nested/first.txt', 'second.txt'), array_keys($parsed['m']));
        $this->assertSame(\Extract_low_list::GZ | \Extract_low_list::BZ2, $parsed['c']);
        $this->assertSame($first[3], $parsed['m']['nested/first.txt'][3]);
        $this->assertSame(strlen($fileMetadata), $parsed['m']['nested/first.txt'][5]);
        $this->assertSame(0, $parsed['m']['nested/first.txt'][7]);
        $this->assertSame($first[2], $parsed['m']['second.txt'][7]);
        $this->assertSame($second[3], $parsed['m']['second.txt'][3]);
    }

    /**
     * Read exact chunk boundaries and decode each supported file compression mode.
     *
     * @dataProvider payloads
     * @param string $contents
     * @param string $compression
     * @return void
     */
    public function testExtractionPayloads($contents, $compression): void
    {
        $encoded = $contents;
        $flag = 0;
        if ($compression === 'gzip') {
            $encoded = gzdeflate($contents);
            $flag = \Extract_low_list::GZ;
        }
        if ($compression === 'bzip2') {
            $encoded = bzcompress($contents);
            $flag = \Extract_low_list::BZ2;
        }
        $stream = fopen('php://temp', 'w+b');
        try {
            fwrite($stream, $encoded . 'next-entry');
            rewind($stream);

            $this->assertSame($contents, \Extract_low_list::extractFile('fixture.bin', $this->entry($contents, $encoded, $flag), $stream));
            $this->assertSame(strlen($encoded), ftell($stream));
            $this->assertSame('next-entry', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
    }

    /**
     * Supply empty, binary, chunk-boundary and compression vectors.
     *
     * @return array
     */
    public static function payloads(): array
    {
        $cases = array();
        foreach (array(1, 8191, 8192, 8193, 16384) as $length) {
            $cases['plain length ' . $length] = array(str_repeat('a', $length), 'plain');
        }
        foreach (array('plain', 'gzip', 'bzip2') as $compression) {
            $cases[$compression . ' binary'] = array("\0\xff\x01binary\0\r\n", $compression);
            $cases[$compression . ' empty'] = array('', $compression);
        }

        return $cases;
    }

    /**
     * Honor an explicit Unix temporary directory without changing it or creating files.
     *
     * @return void
     */
    public function testConfiguredTemporaryDirectory(): void
    {
        $original = getenv('TMPDIR');
        try {
            putenv('TMPDIR=' . PATH_CACHE . 'configured');

            $this->assertSame(PATH_CACHE . 'configured', \Extract_low_list::tmpdir());
            $this->assertFileDoesNotExist(PATH_CACHE . 'configured');
        } finally {
            $original === false ? putenv('TMPDIR') : putenv('TMPDIR=' . $original);
        }
    }

    /**
     * Use the native Unix fallback when TMPDIR is absent.
     *
     * @return void
     */
    public function testDefaultTemporaryDirectory(): void
    {
        $original = getenv('TMPDIR');
        try {
            putenv('TMPDIR');

            $this->assertSame(realpath('/tmp'), \Extract_low_list::tmpdir());
        } finally {
            $original === false ? putenv('TMPDIR') : putenv('TMPDIR=' . $original);
        }
    }

    /**
     * Remove only the requested extraction tree and restore the caller's directory.
     *
     * @dataProvider cleanupTrees
     * @param string $shape
     * @return void
     */
    public function testTemporaryTreeCleanup($shape): void
    {
        $directory = PATH_CACHE . 'extraction';
        mkdir($directory, 0700, true);
        file_put_contents(PATH_CACHE . 'preserved.txt', 'keep');
        if ($shape === 'files' || $shape === 'nested') {
            file_put_contents($directory . '/first.txt', 'first');
            file_put_contents($directory . '/second.txt', 'second');
        }
        if ($shape === 'nested') {
            mkdir($directory . '/nested');
            file_put_contents($directory . '/nested/child.txt', 'child');
        }
        $original = getcwd();
        try {
            \Extract_low_list::_removeTmpFiles($directory, $original);

            $this->assertDirectoryDoesNotExist($directory);
            $this->assertSame($original, getcwd());
            $this->assertSame('keep', file_get_contents(PATH_CACHE . 'preserved.txt'));
        } finally {
            chdir($original);
            $this->assertSame('keep', file_get_contents(PATH_CACHE . 'preserved.txt'));
        }
    }

    /**
     * Supply empty and populated extraction directories.
     *
     * @return array
     */
    public static function cleanupTrees(): array
    {
        return array(array('empty'), array('files'), array('nested'));
    }

    /**
     * Avoid following a cache-tree symlink into a separate owned fixture directory.
     *
     * @return void
     */
    public function testCleanupPreservesSymlinkTarget(): void
    {
        $directory = PATH_CACHE . 'extraction';
        $target = PATH_CACHE . 'separate-fixture';
        mkdir($directory, 0700, true);
        mkdir($target);
        file_put_contents($target . '/preserved.txt', 'keep');
        symlink($target, $directory . '/linked-directory');
        $original = getcwd();
        try {
            \Extract_low_list::_removeTmpFiles($directory, $original);

            $this->assertDirectoryDoesNotExist($directory);
            $this->assertSame($original, getcwd());
        } finally {
            chdir($original);
            $this->assertSame('keep', file_get_contents($target . '/preserved.txt'));
        }
    }

    /**
     * Remove a file symlink without deleting the separate owned target file.
     *
     * @return void
     */
    public function testFileSymlinkCleanupPreservesTarget(): void
    {
        $directory = PATH_CACHE . 'extraction';
        mkdir($directory, 0700, true);
        file_put_contents(PATH_CACHE . 'preserved.txt', 'keep');
        symlink(PATH_CACHE . 'preserved.txt', $directory . '/linked-file');
        $original = getcwd();
        try {
            \Extract_low_list::_removeTmpFiles($directory, $original);

            $this->assertDirectoryDoesNotExist($directory);
            $this->assertSame($original, getcwd());
            $this->assertSame('keep', file_get_contents(PATH_CACHE . 'preserved.txt'));
        } finally {
            chdir($original);
        }
    }

    /**
     * Exercise the archive's registered loader with unrelated, existing and missing names.
     *
     * @return void
     */
    public function testArchiveAutoloadCallback(): void
    {
        $loaders = array_filter(spl_autoload_functions(), static function ($loader) {
            if (!$loader instanceof \Closure) {
                return false;
            }
            $file = (new \ReflectionFunction($loader))->getFileName();

            return strpos($file, 'phar://' . PATH_THIRD . 'low_list/low_list.phar/index.php') === 0;
        });
        $this->assertCount(1, $loaders);
        $loader = reset($loaders);
        $this->assertNull($loader('OtherVendor\\Missing'));
        $this->assertNull($loader('LowList\\FluxCapacitor\\Conduit\\StaticCache'));
        $this->assertNull($loader('LowList\\Missing'));
        $this->assertTrue(class_exists('LowList\\FluxCapacitor\\Conduit\\StaticCache', false));
        $this->assertFalse(class_exists('LowList\\Missing', false));
    }

    /**
     * Leave classes outside the exact foundation namespace to their own autoloaders.
     *
     * @dataProvider unrelatedClasses
     * @param string $class
     * @return void
     */
    public function testLooseAutoloadNamespaceBoundary($class): void
    {
        $loaders = array_filter(spl_autoload_functions(), static function ($loader) {
            return $loader instanceof \Closure
                && (new \ReflectionFunction($loader))->getFileName() === PATH_THIRD . 'low_list/autoload.php';
        });
        $this->assertCount(1, $loaders);

        $this->assertNull(reset($loaders)($class));
        $this->assertFalse(class_exists($class, false));
    }

    /**
     * Supply other vendors and sibling namespaces with a shared class-name prefix.
     *
     * @return array
     */
    public static function unrelatedClasses(): array
    {
        return array(
            array('OtherVendor\\Missing'),
            array('LowList\\Other\\Missing'),
            array('LowList\\FluxCapacitorOther\\Missing'),
        );
    }

    /**
     * Build one documented PHAR entry descriptor for a byte-stream fixture.
     *
     * @param string $decoded
     * @param string $encoded
     * @param int $compression
     * @return array
     */
    private function entry($decoded, $encoded, $compression)
    {
        return array(0 => strlen($decoded), 1 => 0, 2 => strlen($encoded),
            3 => sprintf('%u', crc32($decoded)), 4 => $compression | 0644, 5 => 0, 7 => 0);
    }
}
