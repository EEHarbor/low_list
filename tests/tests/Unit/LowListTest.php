<?php

namespace LowListTests;

class LowListTest extends TestCase
{
    /**
     * Preserve ordering, delimiters, variable names and renderer arguments.
     *
     * @dataProvider eachVectors
     * @param array $params
     * @param array $rows
     * @return void
     */
    public function testEachPassesExactRowsToTemplate(array $params, array $rows): void
    {
        ee()->TMPL->tagparams = $params;
        ee()->TMPL->tagdata = '[{key}={val}]';
        $plugin = new \Low_list();
        $expected = ee()->TMPL->parse_variables(ee()->TMPL->tagdata, $rows);
        ee()->TMPL->parsed = array();

        $this->assertSame($expected, $plugin->each());
        $this->assertSame(array(array('[{key}={val}]', $rows)), ee()->TMPL->parsed);
        $this->assertSame($expected, $plugin->each());
        $this->assertCount(2, ee()->TMPL->parsed);
    }

    /**
     * Supply ordinary, boundary and legacy template vectors.
     *
     * @return array
     */
    public static function eachVectors(): array
    {
        $one = PHP_VERSION_ID >= 80300 ? '1' : 1;
        $two = PHP_VERSION_ID >= 80300 ? '2' : 2;
        $three = PHP_VERSION_ID >= 80300 ? '3' : 3;

        return array(
            'defaults retain empty item' => array(array(), array(array('key' => '', 'val' => ''))),
            'trim items preserve key value whitespace' => array(array('items' => ' a | b:c | :d | e: | f:g:h '), array(
                array('key' => 'a', 'val' => 'a'), array('key' => 'b', 'val' => 'c'),
                array('key' => '', 'val' => 'd'), array('key' => 'e', 'val' => ''), array('key' => 'f', 'val' => 'g:h'),
            )),
            'custom separator' => array(array('items' => ' a <> b ', 'sep' => '<>'), array(array('key' => 'a', 'val' => 'a'), array('key' => 'b', 'val' => 'b'))),
            'single variable uses value' => array(array('items' => 'a:b|c', 'as' => 'item'), array(array('item' => 'b'), array('item' => 'c'))),
            'custom names split only once' => array(array('items' => 'x:y', 'as' => 'one:two:three'), array(array('one' => 'x', 'two:three' => 'y'))),
            'empty variable names' => array(array('items' => 'x:y', 'as' => ':'), array(array('' => 'y'))),
            'empty and zero items' => array(array('items' => '|0||0.5|'), array(array('key' => '', 'val' => ''), array('key' => '0', 'val' => '0'), array('key' => '', 'val' => ''), array('key' => '0.5', 'val' => '0.5'), array('key' => '', 'val' => ''))),
            'ascending range' => array(array('items' => '1|3', 'range' => 'yes'), array(array('key' => $one, 'val' => $one), array('key' => $two, 'val' => $two), array('key' => $three, 'val' => $three))),
            'descending range legacy yes alias' => array(array('items' => '3|1', 'range' => 'on'), array(array('key' => $three, 'val' => $three), array('key' => $two, 'val' => $two), array('key' => $one, 'val' => $one))),
            'alphabetic range' => array(array('items' => 'a|c', 'range' => 'yes'), array(array('key' => 'a', 'val' => 'a'), array('key' => 'b', 'val' => 'b'), array('key' => 'c', 'val' => 'c'))),
            'range requires two values' => array(array('items' => '1|2|3', 'range' => 'yes'), array(array('key' => '1', 'val' => '1'), array('key' => '2', 'val' => '2'), array('key' => '3', 'val' => '3'))),
            'range with one item' => array(array('items' => '1', 'range' => 'yes'), array(array('key' => '1', 'val' => '1'))),
            'range disabled' => array(array('items' => '1|3', 'range' => 'off'), array(array('key' => '1', 'val' => '1'), array('key' => '3', 'val' => '3'))),
        );
    }

    /**
     * Match membership results and tag-pair truthiness without mutating items.
     *
     * @dataProvider membershipVectors
     * @param mixed $value
     * @param mixed $tagdata
     * @param mixed $has
     * @param mixed $hasNot
     * @return void
     */
    public function testMembership($value, $tagdata, $has, $hasNot): void
    {
        ee()->TMPL->tagparams = array('items' => 'foo| 0 |bar', 'value' => $value);
        ee()->TMPL->tagdata = $tagdata;
        $plugin = new \Low_list();

        $this->assertSame($has, $plugin->has());
        $this->assertSame($hasNot, $plugin->has_not());
        $this->assertSame($has, $plugin->has());
        $this->assertSame(array(), ee()->TMPL->parsed);
    }

    /**
     * Supply missing, false, zero, case and tag-pair branches.
     *
     * @return array
     */
    public static function membershipVectors(): array
    {
        return array(
            array('foo', '', 'TRUE', 'FALSE'), array('missing', '', 'FALSE', 'TRUE'),
            array('foo', 'matched', 'matched', ''), array('missing', 'matched', '', 'matched'),
            array('FOO', '', 'FALSE', 'TRUE'), array('0', '', 'TRUE', 'FALSE'),
            array('', '', null, null), array(false, 'body', null, null),
            array('foo', '0', 'TRUE', 'FALSE'),
        );
    }

    /**
     * Preserve literal markup for the EE renderer to interpret.
     *
     * @return void
     */
    public function testItemsAreNotExecutedOrPreescaped(): void
    {
        ee()->TMPL->tagparams['items'] = '<script>x</script>|{tag}';
        ee()->TMPL->tagdata = '{val}';

        $this->assertSame('<script>x</script>{tag}', (new \Low_list())->each());
    }

    /**
     * Reject an empty delimiter through the runtime's native validation boundary.
     *
     * @return void
     */
    public function testEmptySeparatorIsRejected(): void
    {
        ee()->TMPL->tagparams = array('items' => 'a|b', 'sep' => '');
        $this->expectException(PHP_VERSION_ID >= 80000 ? \ValueError::class : \ErrorException::class);
        new \Low_list();
    }

}
