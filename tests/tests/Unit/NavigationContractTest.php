<?php

namespace LowListTests;

class NavigationContractTest extends TestCase
{
    /**
     * Match the native sidebar's fluent object, header and last-button semantics.
     *
     * @return void
     */
    public function testSidebarHeaderStateAndIdentity(): void
    {
        $sidebar = new Sidebar();
        $this->assertSame($sidebar, $sidebar->make());
        $url = new Url();
        $url->make('first');
        $header = $sidebar->addHeader('First', $url);
        $this->assertSame(array($header), $sidebar->headers);
        $this->assertSame('First', $header->text);
        $this->assertSame($url, $header->url);
        $this->assertSame('', $header->class);
        $this->assertNull($header->button);
        $this->assertSame($header, $header->withButton('Create', '/create'));
        $this->assertSame($header, $header->withButton('Import', '/import'));
        $this->assertSame(array('text' => 'Import', 'url' => '/import'), $header->button);
        $url->requested = true;
        $this->assertSame($header, $header->withUrl($url));
        $this->assertSame('active ', $header->class);
        $this->assertSame($header, $header->isActive());
        $this->assertSame('active active ', $header->class);
        $heading = $sidebar->addHeader('Heading');
        $this->assertNull($heading->url);
        $this->assertSame(array($header, $heading), $sidebar->headers);
    }

    /**
     * Preserve independent CP URL dependency objects and breadcrumb references.
     *
     * @return void
     */
    public function testUrlFactoryAndBreadcrumbIdentity(): void
    {
        $factory = new UrlFactory('cp/first');
        $first = $factory->make('first');
        $second = $factory->make('second');
        $this->assertNotSame($first, $second);
        $this->assertSame('/cp/first', $first->compile());
        $this->assertSame('/cp/second', $second->compile());
        $this->assertSame(array($first, $second), $factory->urls);
        $this->assertTrue($first->isTheRequestedURI());
        $this->assertFalse($second->isTheRequestedURI());
        ee()->view = new \stdClass();
        $cp = new ControlPanel();
        $cp->set_breadcrumb($first, 'One');
        $cp->set_breadcrumb($second, 'Two');
        $cp->set_breadcrumb($first, 'Updated');
        $this->assertSame(array(array($first, 'One'), array($second, 'Two'), array($first, 'Updated')), $cp->breadcrumbs);
        $this->assertSame(array('/cp/first' => 'Updated', '/cp/second' => 'Two'), ee()->view->cp_breadcrumbs);
    }
}
