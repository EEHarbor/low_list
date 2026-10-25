<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\PersistentCache;
use LowList\FluxCapacitor\Conduit\StaticCache;

class PersistentCacheOverwriteTest extends TestCase
{
    /**
     * Replace a committed record without retaining disk or request-cache data.
     *
     * @return void
     */
    public function testShorterOverwriteReplacesCommittedValue(): void
    {
        $key = 'overwrite-shorter';
        $digest = sha1($key);
        $original = array('description' => 'A longer previously committed value');
        $replacement = array('id' => 7);
        $unrelated = array('keep' => 'unrelated request value');
        StaticCache::put('overwrite-unrelated', $unrelated);

        $this->assertTrue(PersistentCache::set($key, $original));
        $path = PATH_CACHE . 'low_list/' . $digest;
        $this->assertSame(serialize($original), file_get_contents($path));
        $this->assertTrue(PersistentCache::set($key, $replacement));

        $this->assertSame('a:1:{s:2:"id";i:7;}', file_get_contents($path));
        $this->assertSame($replacement, StaticCache::get($digest, true));
        $this->assertSame($unrelated, StaticCache::get('overwrite-unrelated'));

        StaticCache::clear();
        $this->assertSame($replacement, PersistentCache::get($key));
        $this->assertTrue(PersistentCache::has($key));
    }

    /**
     * Keep a false replacement distinguishable from an absent committed record.
     *
     * @return void
     */
    public function testFalseOverwriteRemainsAStoredValue(): void
    {
        $key = 'overwrite-false';
        $digest = sha1($key);
        $this->assertTrue(PersistentCache::set($key, array('id' => 1)));
        $this->assertTrue(PersistentCache::set($key, false));

        $path = PATH_CACHE . 'low_list/' . $digest;
        $this->assertSame('b:0;', file_get_contents($path));
        $this->assertTrue(StaticCache::has($digest, true));
        $this->assertFalse(StaticCache::get($digest, true));
        $this->assertTrue(PersistentCache::has($key));

        StaticCache::clear();
        $this->assertFalse(PersistentCache::get($key));
        $this->assertTrue(PersistentCache::has($key));
        $this->assertSame('b:0;', file_get_contents($path));
    }

    /**
     * Preserve committed data when its existing file refuses an overwrite.
     *
     * @return void
     * @throws \ErrorException When the original helper emits a native diagnostic.
     */
    public function testReadOnlyFilePreservesCommittedValue(): void
    {
        $key = 'overwrite-read-only';
        $digest = sha1($key);
        $original = array('id' => 3, 'label' => 'committed value');
        StaticCache::put('overwrite-unrelated', array('keep' => 'unrelated request value'));
        $this->assertTrue(PersistentCache::set($key, $original));

        $path = PATH_CACHE . 'low_list/' . $digest;
        $originalMode = fileperms($path) & 0777;
        $originalBytes = file_get_contents($path);
        $requestCache = StaticCache::all();
        $this->assertSame(serialize($original), $originalBytes);
        $this->assertSame($original, StaticCache::get($digest, true));
        $this->assertSame(array(), ee()->logger->messages);

        try {
            $this->assertTrue(chmod($path, 0400));
            clearstatcache(true, $path);
            $this->assertFalse(is_writable($path), 'The owned cache file must actually be read-only.');
            $this->assertTrue(is_writable(PATH_CACHE));
            $this->assertTrue(is_writable(dirname($path)));

            $this->assertFalse(PersistentCache::set($key, array('id' => 9)));
            $this->assertSame(array(
                'Low List: Write Cache enabled but could not write cache file: ' . $path,
            ), ee()->logger->messages);
        } finally {
            $restored = chmod($path, $originalMode);
            clearstatcache(true, $path);
            $this->assertTrue($restored);
            $this->assertSame($originalBytes, file_get_contents($path));
            $this->assertSame($requestCache, StaticCache::all());
        }
    }
}
