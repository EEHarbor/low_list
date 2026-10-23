<?php

namespace LowListTests;

class FacadeContractTest extends TestCase
{
    /**
     * Keep facade objects separate from dependency factories and allow reloading.
     *
     * @return void
     */
    public function testSetRemoveAndReload(): void
    {
        $object = new \stdClass();
        ee()->services['example'] = 'factory-result';
        ee()->set('example', $object);

        $this->assertTrue(ee()->has('example'));
        $this->assertSame($object, ee()->get('example'));
        $this->assertSame($object, ee()->example);
        $this->assertSame('factory-result', ee('example'));
        ee()->remove('example');
        ee()->remove('example');
        $this->assertFalse(ee()->has('example'));
        ee()->set('example', null);
        $this->assertTrue(ee()->has('example'));
        $this->assertNull(ee()->get('example'));
        $this->assertTrue(isset(ee()->example));
        ee()->set('input', null);
        $this->assertTrue(ee()->has('input'));
        $this->assertNull(ee()->get('input'));
        $this->assertNull(ee()->input);
        $this->assertTrue(isset(ee()->input));
        $this->expectException(\RuntimeException::class);
        ee()->set('example', $object);
    }

    /**
     * Reject replacing a loaded native object until the caller removes it.
     *
     * @return void
     */
    public function testNativeOverwriteGuard(): void
    {
        $logger = ee()->logger;
        try {
            ee()->set('logger', new \stdClass());
            $this->fail('An existing facade object must not be overwritten.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Cannot overwrite logger on the loader.', $exception->getMessage());
        }
        $this->assertSame($logger, ee()->get('logger'));
        ee()->remove('logger');
        $this->assertFalse(ee()->has('logger'));
        ee()->set('logger', $logger);
        $this->assertSame($logger, ee()->get('logger'));
        $this->assertSame($logger, ee()->logger);
        $this->assertTrue(isset(ee()->logger));
    }

    /**
     * Initialize cookies when the native facade installs its session object.
     *
     * @return void
     */
    public function testSessionInstallation(): void
    {
        $session = new Session();
        ee()->set('session', $session);

        $this->assertTrue($session->cookiesSet);
        $this->assertSame($session, ee()->get('session'));
        $this->assertSame($session, ee()->session);
        $this->assertTrue(isset(ee()->session));
    }

    /**
     * Fail when a removed facade object is requested.
     *
     * @return void
     */
    public function testMissingFacadeObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ee()->get('absent');
    }

    /**
     * Return a fresh DB query with shared connection recording and no stale metadata.
     *
     * @return void
     */
    public function testDatabaseFactoryIsolation(): void
    {
        ee()->db->data_cache['table_names'] = array('stale');
        $first = ee('db');
        $second = ee('db');

        $this->assertNotSame(ee()->db, $first);
        $this->assertNotSame($first, $second);
        $this->assertSame(array(), $first->data_cache);
        $first->data_cache['table_names'] = array('new');
        $first->where('site_id', 1);
        $this->assertSame(array(), $second->data_cache);
        $this->assertSame(array('table_names' => array('stale')), ee()->db->data_cache);
        $this->assertSame(array(array('where', array('site_id', 1))), ee()->db->calls);
    }
}
