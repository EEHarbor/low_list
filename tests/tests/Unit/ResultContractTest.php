<?php

namespace LowListTests;

class ResultContractTest extends TestCase
{
    /**
     * Preserve EE DB_result's empty result shape for every row accessor.
     *
     * @return void
     */
    public function testEmptyRowsReturnArrays(): void
    {
        $result = new Result(array());

        $this->assertSame(array(), $result->row());
        $this->assertSame(array(), $result->row('field'));
        $this->assertSame(array(), $result->row('field', 'array'));
        $this->assertSame(array(), $result->row_array());
        $this->assertSame(0, $result->num_rows());
    }

    /**
     * Distinguish SQL NULL columns from absent column names.
     *
     * @return void
     */
    public function testNamedColumnsPreserveNullAndMissingColumnFallback(): void
    {
        $result = new Result(array(array('id' => 1, 'nullable' => null)));
        $first = $result->row();

        $this->assertSame(null, $result->row('nullable'));
        $this->assertSame(1, $result->row('id'));
        $this->assertSame($first, $result->row('missing'));
        $this->assertSame(array('id' => 1, 'nullable' => null), $result->row('missing', 'array'));
    }

    /**
     * Select numeric row indices and preserve the last valid row on invalid indices.
     *
     * @return void
     */
    public function testNumericIndicesAndCurrentRowFallback(): void
    {
        $result = new Result(array(array('id' => 1), array('id' => 2)));
        $first = $result->row();
        $second = $result->row('1');

        $this->assertSame(2, $second->id);
        $this->assertSame($second, $result->row(99));
        $this->assertSame($second, $result->row(-1));
        $this->assertSame($first, $result->row('missing'));
        $this->assertSame(array('id' => 2), $result->row(1, 'array'));
        $this->assertSame(array('id' => 2), $result->row_array(99));
        $this->assertSame($first, $result->row());
        $this->assertSame(2, $result->num_rows());
    }
}
