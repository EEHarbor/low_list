<?php

namespace LowListTests;

class NativeLoadingTest extends TestCase
{
    use NativeProbe;

    /**
     * Return native configured/predefined version precedence contexts.
     *
     * @return array
     */
    public static function bootstrapContexts(): array
    {
        return array(array('configured', '2.5.5'), array('defined-legacy', '2.5.5'), array('defined-modern', '7.5.0'));
    }

    /**
     * Load the real fallback before the test bootstrap can define ee().
     *
     * @dataProvider bootstrapContexts
     * @param string $case
     * @param string $version
     * @return void
     */
    public function testOriginalFallbackAndVersionPrecedence($case, $version): void
    {
        $result = $this->runContext('bootstrap', $case);
        $this->assertTrue($result['identity']);
        $this->assertSame($version, $result['version']);
        $this->assertSame('LowList\\FluxCapacitor\\Bridge\\' . ($version === '2.5.5' ? 'EE2' : 'EE6'), $result['parent']);
        $this->assertSame($case === 'configured' ? array('app_version') : array(), $result['config_calls']);
        $this->assertSame($case === 'configured' ? 3 : 2, $result['instance_calls']);
        $this->assertSame('phar://' . dirname(__DIR__, 3) . '/low_list/low_list.phar/FluxCapacitor/Bridge/EE.php', $result['source']);
    }

    /**
     * Return file priority, installer, predeclared and constant-precedence layouts.
     *
     * @return array
     */
    public static function fieldtypeLayouts(): array
    {
        return array(
            array('system', 'system/ee/legacy/fieldtypes/EE_Fieldtype.php'),
            array('app', 'application/fieldtypes/EE_Fieldtype.php'),
            array('priority', 'system/ee/legacy/fieldtypes/EE_Fieldtype.php'),
            array('installer', 'expressionengine/fieldtypes/EE_Fieldtype.php'),
            array('installer-ancestor', 'installer-backup/expressionengine/fieldtypes/EE_Fieldtype.php'),
            array('predeclared', '@fixture'),
            array('defined-version', 'system/ee/legacy/fieldtypes/EE_Fieldtype.php'),
        );
    }

    /**
     * Select the correct unchanged parent file and preserve configured versions.
     *
     * @dataProvider fieldtypeLayouts
     * @param string $case
     * @param string $relative
     * @return void
     */
    public function testOriginalFieldtypeLayouts($case, $relative): void
    {
        try {
            $result = $this->runContext('fieldtype-loading', $case);
        } finally {
            $this->assertSame(array(), glob(PATH_CACHE . '/low-list-loading-*'));
        }
        $this->assertSame($relative, $result['parent_relative']);
        $this->assertSame(hash_file('sha256', dirname(__DIR__, 2) . '/Fixtures/ee/legacy/fieldtypes/EE_Fieldtype.php'), $result['parent_hash']);
        $this->assertSame('2.2.0', $result['version']);
        $this->assertSame($case === 'defined-version' ? 'predefined-test-version' : '2.2.0', $result['info_version']);
        $this->assertNull($result['content_id']);
    }

    /**
     * Require strict native completion before interpreting a loading result.
     *
     * @param string $mode
     * @param string $case
     * @return array
     */
    private function runContext($mode, $case)
    {
        list($status, $output, $errors) = $this->probe(array($mode, $case));
        $this->assertSame(0, $status, $errors);
        $this->assertSame('', $errors);
        $result = json_decode($output, true);
        $this->assertIsArray($result, $output);

        return $result;
    }
}
