<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\PersistentCache;
use LowList\FluxCapacitor\Conduit\StaticCache;

class PersistentCacheTest extends TestCase
{
    /**
     * Round trip nested values through the shipped file-cache implementation.
     *
     * @return void
     */
    public function testDiskLifecycle(): void
    {
        $value = array('rows' => array(array('id' => 1, 'value' => null)), 'zero' => 0);
        $this->assertFalse(PersistentCache::has('article'));
        $this->assertTrue(PersistentCache::set('article', $value));
        $this->assertTrue(PersistentCache::has('article'));
        StaticCache::clear();
        $this->assertSame($value, PersistentCache::get('article'));
        $this->assertSame(serialize($value), file_get_contents(PATH_CACHE . 'low_list/' . sha1('article')));
        $this->assertTrue(PersistentCache::delete('article'));
        $this->assertFalse(PersistentCache::has('article'));
        $this->assertTrue(PersistentCache::delete('article'));
    }

    /**
     * Log missing cache entries and preserve unrelated static cache values.
     *
     * @return void
     */
    public function testMissingAndClear(): void
    {
        $this->assertFalse(PersistentCache::get('missing'));
        $this->assertCount(1, ee()->logger->messages);
        $this->assertStringContainsString(sha1('missing'), ee()->logger->messages[0]);
        StaticCache::put('unrelated', 'keep');
        PersistentCache::set(array('site', 'record'), 'value');
        mkdir(PATH_CACHE . 'low_list/subdirectory');
        $this->assertTrue(PersistentCache::clear());
        $this->assertFalse(PersistentCache::has(array('site', 'record')));
        $this->assertSame('keep', StaticCache::get('unrelated'));
        $this->assertDirectoryExists(PATH_CACHE . 'low_list/subdirectory');
    }

    /**
     * A successfully stored value remains available from the request cache.
     *
     * @return void
     */
    public function testStoredValueSurvivesDiskRemovalWithinRequest(): void
    {
        $this->assertTrue(PersistentCache::set('article', array('id' => 1)));
        unlink(PATH_CACHE . 'low_list/' . sha1('article'));

        $this->assertSame(array('id' => 1), PersistentCache::get('article'));
    }

    /**
     * A warm disk read must retain decoded data in the request cache.
     *
     * @return void
     */
    public function testDiskReadWarmsDecodedRequestCache(): void
    {
        PersistentCache::set('article', array('id' => 1));
        StaticCache::clear();
        $this->assertSame(array('id' => 1), PersistentCache::get('article'));
        unlink(PATH_CACHE . 'low_list/' . sha1('article'));

        $this->assertSame(array('id' => 1), PersistentCache::get('article'));
    }

    /**
     * Preserve false-like and binary payloads after a fresh disk read.
     *
     * @dataProvider diskValues
     * @param mixed $value
     * @return void
     */
    public function testDiskValueRoundTrip($value): void
    {
        $this->assertTrue(PersistentCache::set('value', $value));
        StaticCache::clear();

        $this->assertSame($value, PersistentCache::get('value'));
        $this->assertSame(serialize($value), file_get_contents(PATH_CACHE . 'low_list/' . sha1('value')));
        $this->assertTrue(PersistentCache::has('value'));
    }

    /**
     * Supply persisted null, scalar, empty, nested and binary data.
     *
     * @return array
     */
    public static function diskValues(): array
    {
        return array(
            'null' => array(null), 'false' => array(false), 'integer-zero' => array(0),
            'string-zero' => array('0'), 'empty-string' => array(''), 'empty-array' => array(array()),
            'binary' => array("a\0b\xff"), 'nested' => array(array('empty' => array(), 'null' => null)),
        );
    }

    /**
     * Keep independently written raw keys distinct from other keys' digests.
     *
     * @return void
     */
    public function testDigestNamedKeyDoesNotReadAnotherEntriesRequestValue(): void
    {
        $this->assertTrue(PersistentCache::set('article', 'article-value'));
        $this->assertTrue(PersistentCache::set(sha1('article'), 'digest-key-value'));

        $this->assertSame('digest-key-value', PersistentCache::get(sha1('article')));
    }

    /**
     * Honor the public flag when an existence check receives a processed key.
     *
     * @return void
     */
    public function testProcessedExistenceKey(): void
    {
        $this->assertTrue(PersistentCache::set('separate-key', 'stored'));

        $this->assertTrue(PersistentCache::has(sha1('separate-key'), true));
    }

    /**
     * Preserve zero components in otherwise distinct compound storage keys.
     *
     * @dataProvider zeroKeyComponents
     * @param int|string $zero
     * @return void
     */
    public function testZeroKeyComponentIsDistinct($zero): void
    {
        $key = array('site', $zero, 'record');
        $this->assertTrue(PersistentCache::set($key, 'zero-component'));
        $this->assertTrue(PersistentCache::set(array('site', 'record'), 'without-component'));
        $this->assertTrue(PersistentCache::has(array('site', 'record')));
        $this->assertTrue(PersistentCache::delete($key));

        $this->assertTrue(PersistentCache::has(array('site', 'record')));
    }

    /**
     * Supply numeric zero components from stored and request-derived keys.
     *
     * @return array
     */
    public static function zeroKeyComponents(): array
    {
        return array(array(0), array('0'));
    }

    /**
     * Refuse filesystem mutation when either actual cache directory is read-only.
     *
     * @dataProvider unwritablePaths
     * @param string $operation
     * @param bool $modulePath
     * @return void
     */
    public function testUnwritableDirectoryGuards($operation, $modulePath): void
    {
        $this->assertTrue(PersistentCache::set('existing', 'keep'));
        StaticCache::clear();
        $directory = $modulePath ? PATH_CACHE . 'low_list/' : PATH_CACHE;
        $path = PATH_CACHE . 'low_list/' . sha1('existing');
        $before = glob(PATH_CACHE . 'low_list/*');

        try {
            $this->assertTrue(chmod($directory, 0500));
            clearstatcache();
            $this->assertFalse(is_writable($directory), 'This vector requires a genuinely read-only owned directory.');
            $arguments = $operation === 'clear' ? array() : array($operation === 'delete' ? 'existing' : 'new');
            if ($operation === 'set') {
                $arguments[] = 'new-value';
            }
            $this->assertFalse(call_user_func_array(array(PersistentCache::class, $operation), $arguments));
            $prefix = $modulePath ? 'module cache path' : 'cache path';
            $this->assertSame(array('Low List: Write Cache enabled but ' . $prefix . ' not writable: ' . $directory), ee()->logger->messages);
            $this->assertSame($before, glob(PATH_CACHE . 'low_list/*'));
            $this->assertSame(serialize('keep'), file_get_contents($path));
        } finally {
            chmod($directory, 0700);
            clearstatcache();
        }
    }

    /**
     * Exercise each caller's own early return for both writable-path checks.
     *
     * @return array
     */
    public static function unwritablePaths(): array
    {
        $vectors = array();
        foreach (array('get', 'set', 'delete', 'clear') as $operation) {
            $vectors[$operation . '-generic'] = array($operation, false);
            $vectors[$operation . '-module'] = array($operation, true);
        }

        return $vectors;
    }

    /**
     * Reject damaged disk serialization without emitting a native warning.
     *
     * @return void
     */
    public function testMalformedDiskPayloadIsRejectedWithoutDiagnostics(): void
    {
        $this->assertTrue(PersistentCache::set('damaged', array('id' => 1)));
        StaticCache::clear();
        $this->assertNotFalse(file_put_contents(PATH_CACHE . 'low_list/' . sha1('damaged'), 'not serialized data'));

        $this->assertFalse(PersistentCache::get('damaged'));
    }

    /**
     * Handle an existing file blocking either cache directory without warnings.
     *
     * @dataProvider blockingPaths
     * @param bool $modulePath
     * @return void
     */
    public function testBlockingDirectoryFileIsRejectedWithoutDiagnostics($modulePath): void
    {
        if ($modulePath) {
            mkdir(PATH_CACHE, DIR_WRITE_MODE);
        }
        $path = $modulePath ? PATH_CACHE . 'low_list' : rtrim(PATH_CACHE, '/');
        file_put_contents($path, 'preserve-blocking-file');

        try {
            $this->assertFalse(PersistentCache::set('blocked', 'value'));
        } finally {
            $this->assertSame('preserve-blocking-file', file_get_contents($path));
            unlink($path);
        }
    }

    /**
     * Cover generic and module directory creation failures separately.
     *
     * @return array
     */
    public static function blockingPaths(): array
    {
        return array('generic' => array(false), 'module' => array(true));
    }

    /**
     * Read an ordinary compound key through the documented public API.
     *
     * @return void
     */
    public function testCompoundKeyRoundTrip(): void
    {
        $key = array('site', 'record');
        $this->assertTrue(PersistentCache::set($key, array('id' => 7)));
        StaticCache::clear();

        $this->assertSame(array('id' => 7), PersistentCache::get($key));
    }

    /**
     * Decode supported objects with their properties and class intact.
     *
     * @return void
     */
    public function testObjectDiskRoundTrip(): void
    {
        $value = (object) array('id' => 7, 'empty' => null);
        $this->assertTrue(PersistentCache::set('object', $value));
        StaticCache::clear();

        $stored = PersistentCache::get('object');
        $this->assertInstanceOf(\stdClass::class, $stored);
        $this->assertNotSame($value, $stored);
        $this->assertEquals($value, $stored);
    }

    /**
     * Reject a directory occupying a generated cache-file path without warnings.
     *
     * @dataProvider cacheFileOperations
     * @param string $operation
     * @return void
     */
    public function testDirectoryAtCacheFileIsRejectedWithoutDiagnostics($operation): void
    {
        $this->assertTrue(PersistentCache::set('blocked', 'value'));
        StaticCache::clear();
        $path = PATH_CACHE . 'low_list/' . sha1('blocked');
        unlink($path);
        mkdir($path, DIR_WRITE_MODE);
        $arguments = $operation === 'get' ? array('blocked') : array('blocked', 'replacement');

        try {
            $this->assertFalse(call_user_func_array(array(PersistentCache::class, $operation), $arguments));
        } finally {
            $this->assertDirectoryExists($path);
        }
    }

    /**
     * Exercise both native helper boundaries at a genuine filesystem collision.
     *
     * @return array
     */
    public static function cacheFileOperations(): array
    {
        return array(array('get'), array('set'));
    }

    /**
     * Clear an empty module cache repeatedly without touching unrelated values.
     *
     * @return void
     */
    public function testEmptyClearIsIdempotent(): void
    {
        StaticCache::put('unrelated', 'keep');

        $this->assertTrue(PersistentCache::clear());
        $this->assertSame(array(), glob(PATH_CACHE . 'low_list/*'));
        $this->assertTrue(PersistentCache::clear());
        $this->assertSame('keep', StaticCache::get('unrelated'));
    }

    /**
     * Remove every stored entry and request copy while preserving directory targets.
     *
     * @return void
     */
    public function testClearMultipleFilesAndSymlinks(): void
    {
        $this->assertTrue(PersistentCache::set('one', 'first'));
        $this->assertTrue(PersistentCache::set('two', 'second'));
        $this->assertSame('first', StaticCache::get(sha1('one'), true));
        $target = PATH_CACHE . 'separate-target';
        mkdir($target, DIR_WRITE_MODE);
        file_put_contents($target . '/keep', 'preserve-target');
        symlink($target . '/keep', PATH_CACHE . 'low_list/file-link');
        symlink($target, PATH_CACHE . 'low_list/directory-link');

        $this->assertTrue(PersistentCache::clear());
        $this->assertFalse(PersistentCache::has('one'));
        $this->assertFalse(PersistentCache::has('two'));
        $this->assertFalse(StaticCache::get(sha1('one'), true));
        $this->assertFalse(StaticCache::get(sha1('two'), true));
        $this->assertFileDoesNotExist(PATH_CACHE . 'low_list/file-link');
        $this->assertTrue(is_link(PATH_CACHE . 'low_list/directory-link'));
        $this->assertSame('preserve-target', file_get_contents($target . '/keep'));
    }
}
