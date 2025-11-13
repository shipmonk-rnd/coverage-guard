<?php declare(strict_types = 1);

namespace ShipMonk\CoverageGuard\Utils;

use PHPUnit\Framework\TestCase;

final class IndenterTest extends TestCase
{

    public function testChangeConvertsIndentationLevels(): void
    {
        $codeTemplate = <<<'PHP'
        function example()
        {
        ..if (true) {
        ....doSomething();
        ...} // invalid indent
        // not indent ..
        }
        PHP;

        $converted = Indenter::change($codeTemplate, '..', '>>>>');

        $expected = <<<'PHP'
        function example()
        {
        >>>>if (true) {
        >>>>>>>>doSomething();
        >>>>.} // invalid indent
        // not indent ..
        }
        PHP;

        self::assertSame($expected, $converted);
    }

}
