<?php

namespace LowListTests;

class PaginationContractTest extends TestCase
{
    /**
     * Keep the native renderer's URL object boundary and delegation identity.
     *
     * @return void
     */
    public function testRendererRetainsUrlObject(): void
    {
        $pagination = new Pagination();
        $url = (new Url())->make('records');

        $this->assertSame('pagination', $pagination->render($url));
        $this->assertSame(array(array('render', $url)), $pagination->calls);
        $this->assertSame('/cp/records', $url->compile());
    }

    /**
     * Reject strings at the same boundary as EE's typed CP URL renderer.
     *
     * @return void
     */
    public function testRendererRejectsStringUrl(): void
    {
        $pagination = new Pagination();
        $this->expectException(\TypeError::class);

        $pagination->render('/cp/records');
    }
}
