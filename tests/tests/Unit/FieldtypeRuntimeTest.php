<?php

namespace LowListTests;

class FieldtypeRuntimeTest extends TestCase
{
    use NativeProbe;

    /**
     * Use EE2's direct channel request and exact native table calls in a fresh load.
     *
     * @return void
     */
    public function testLegacyFieldtypeRequestAndSettings(): void
    {
        list($status, $output, $errors) = $this->probe(array('fieldtype', 'legacy'));
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);
        $this->assertSame(array('posted', 'query', false, 0, '0', '', false), $result['channels']);
        $this->assertSame(array(array('Title', '<input>'), array(array('colspan' => '2', 'data' => 'Wide<select>'))), $result['rows']);
        $this->assertSame(array(), $result['modern_rows']);
        $this->assertSame(array(true, true), $result['returns']);
        $this->assertSame(19, $result['content_id']);
        $this->assertSame(array(), $result['model_calls']);
    }

    /**
     * Reject an array-valued channel request on the legacy direct-return path.
     *
     * @return void
     */
    public function testLegacyStructuredChannelIsRejected(): void
    {
        list($status, $output, $errors) = $this->probe(array('fieldtype', 'legacy-structured'));
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);
        $this->assertSame(array(false, false), $result['channels']);
    }

}
