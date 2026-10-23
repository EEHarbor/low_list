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
}
