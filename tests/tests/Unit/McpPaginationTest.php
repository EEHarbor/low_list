<?php

namespace LowListTests;

use LowList\FluxCapacitor\Base\Mcp;

class McpPaginationTest extends TestCase
{
    /**
     * Preserve model identity, page offsets, count isolation and pagination options.
     *
     * @dataProvider pageInputs
     * @param mixed $page
     * @param int $expectedPage
     * @param int $expectedOffset
     * @return void
     */
    public function testPageInputs($page, $expectedPage, $expectedOffset): void
    {
        $mcp = $this->controller();
        ee()->input->values['page'] = $page;
        $model = new PaginationModel(array('one', 'two', 'three'));
        $url = (new Url())->make('explicit');

        $this->assertSame($model, $mcp->paginateModel($model, $url, 10, 7, 'page'));
        $this->assertSame(array(array('limit', 10), array('offset', $expectedOffset)), $model->calls);
        $this->assertContains(array('CP/Pagination', 3), ee()->serviceCalls);
        $this->assertSame(array(array('perPage', 10), array('currentPage', $expectedPage), array('displayPageLinks', 7), array('queryStringVariable', 'page'), array('render', $url)), ee()->services['CP/Pagination']->calls);
        $this->assertSame(array('pagination' => 'pagination'), $mcp->data);
    }

    /**
     * Supply absent, empty, numeric, fractional, malformed and large page values.
     *
     * @return array
     */
    public static function pageInputs(): array
    {
        return array(
            array(null, 1, 0), array(false, 1, 0), array('', 1, 0), array('0', 1, 0),
            array('1', 1, 0), array('3', 3, 20), array('2.5', 2, 10), array('malformed', 1, 0),
            array('10000000000', 10000000000, 99999999990),
        );
    }

    /**
     * Infer the current handler URL and respect a custom query parameter.
     *
     * @return void
     */
    public function testHandlerUrlAndCustomPageParameter(): void
    {
        $mcp = $this->controller();
        ee()->input->values['position'] = '2';
        $model = new PaginationModel(array());
        $this->assertSame($model, $mcp->listing($model));
        $this->assertSame(array(array('limit', 25), array('offset', 25)), $model->calls);
        $calls = ee()->services['CP/Pagination']->calls;
        $this->assertSame(array('queryStringVariable', 'position'), $calls[3]);
        $this->assertSame(ee()->services['CP/URL'], $calls[4][1]);
        $this->assertSame('/cp/addons/settings/low_list/listing', $calls[4][1]->compile());
        $this->assertContains(array('CP/Pagination', 0), ee()->serviceCalls);
    }

    /**
     * Negative request pages must not generate invalid negative model offsets.
     *
     * @return void
     */
    public function testNegativePageDoesNotCreateNegativeOffset(): void
    {
        $mcp = $this->controller();
        ee()->input->values['page'] = '-2';
        $model = new PaginationModel(array());
        $mcp->paginateModel($model, (new Url())->make('explicit'), 10);

        $this->assertSame(array(array('limit', 10), array('offset', 0)), $model->calls);
    }

    /**
     * The shipped MCP base must not emit a dynamic-property deprecation.
     *
     * @return void
     */
    public function testBaseControllerPaginationHasNoDeprecations(): void
    {
        $this->controller();
        $mcp = new Mcp();
        $model = new PaginationModel(array());

        $this->assertSame($model, $mcp->paginateModel($model, (new Url())->make('explicit')));
    }

    /**
     * Supply real bridge dependencies and a normal controller view-data container.
     *
     * @return PaginatorMcp
     */
    private function controller()
    {
        ee()->input = new Input();
        ee()->services['CP/URL'] = new Url();
        ee()->services['CP/Pagination'] = new Pagination();

        return new PaginatorMcp();
    }
}

class PaginatorMcp extends Mcp
{
    public $data = array();

    /**
     * Model a regular control-panel handler which uses inferred pagination URLs.
     *
     * @param PaginationModel $model
     * @return PaginationModel
     */
    public function listing($model)
    {
        return $this->paginateModel($model, null, 25, 5, 'position');
    }
}

class PaginationModel
{
    public $calls = array();
    private $rows;

    /**
     * Retain explicit query-result rows for the count query.
     *
     * @param array $rows
     * @return void
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * Count only the cloned builder's rows without altering the original builder.
     *
     * @return PaginationRows
     */
    public function all()
    {
        $this->calls[] = array('all');

        return new PaginationRows($this->rows);
    }

    /**
     * Record the original builder's requested result limit.
     *
     * @param int $limit
     * @return self
     */
    public function limit($limit)
    {
        $this->calls[] = array('limit', $limit);

        return $this;
    }

    /**
     * Record the original builder's requested result offset.
     *
     * @param int $offset
     * @return self
     */
    public function offset($offset)
    {
        $this->calls[] = array('offset', $offset);

        return $this;
    }
}

class PaginationRows
{
    private $rows;

    /**
     * Store the explicit rows returned by the query.
     *
     * @param array $rows
     * @return void
     */
    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    /**
     * Return the native model collection row count.
     *
     * @return int
     */
    public function count()
    {
        return count($this->rows);
    }
}
