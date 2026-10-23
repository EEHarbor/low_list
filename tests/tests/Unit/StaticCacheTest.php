<?php

namespace LowListTests;

use LowList\FluxCapacitor\Conduit\StaticCache;

class StaticCacheTest extends TestCase
{
    /**
     * Exercise round trips, replacement, deletion and process state restoration.
     *
     * @return void
     */
    public function testCacheLifecycle(): void
    {
        $this->assertFalse(StaticCache::has('key'));
        $this->assertFalse(StaticCache::get('key'));
        $this->assertNull(StaticCache::put('key', array('nested' => array(1, null))));
        $this->assertTrue(StaticCache::has('key'));
        $this->assertSame(array('nested' => array(1, null)), StaticCache::get('key'));
        $this->assertNull(StaticCache::set('key', 'replacement'));
        $this->assertSame('replacement', StaticCache::get('key'));
        $this->assertSame(array(sha1('key') => 'replacement'), StaticCache::all());
        StaticCache::delete('key');
        $this->assertFalse(StaticCache::has('key'));
        $this->assertFalse(StaticCache::get('key'));
        $this->assertTrue(StaticCache::clear());
        $this->assertSame(array(), StaticCache::all());
    }

    /**
     * Keep already processed keys intact rather than hashing them twice.
     *
     * @return void
     */
    public function testProcessedKeysAndArrayKeys(): void
    {
        StaticCache::put('already-hashed', 'raw', true);
        $this->assertSame('raw', StaticCache::get('already-hashed', true));
        $this->assertTrue(StaticCache::has('already-hashed', true));
        $this->assertFalse(StaticCache::get('already-hashed'));
        StaticCache::put(array('site', 'addon'), 'value');
        $this->assertSame('value', StaticCache::get(array('site', 'addon')));
        StaticCache::delete('already-hashed', true);
        $this->assertFalse(StaticCache::has('already-hashed', true));
    }

    /**
     * A stored false-like value must round trip rather than become a cache miss.
     *
     * @dataProvider falseLikeValues
     * @param mixed $value
     * @return void
     */
    public function testFalseLikeValuesRoundTrip($value): void
    {
        StaticCache::put('false-like', $value);
        $this->assertSame($value, StaticCache::get('false-like'));
    }

    /**
     * Supply valid cache values that empty() conflates with a missing key.
     *
     * @return array
     */
    public static function falseLikeValues(): array
    {
        return array(array(false), array(0), array('0'), array(''), array(array()), array(null));
    }

    /**
     * Distinguish array keys whose zero component identifies a different entry.
     *
     * @return void
     */
    public function testZeroComponentsDoNotCollide(): void
    {
        StaticCache::put(array('site', 0), 'zero site');
        StaticCache::put(array('site'), 'other site');

        $this->assertSame('zero site', StaticCache::get(array('site', 0)));
    }
}
