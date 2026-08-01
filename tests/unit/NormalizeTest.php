<?php
declare(strict_types=1);

final class NormalizeTest extends PHPUnit\Framework\TestCase
{
    public function testNormalizeCodeFullWidthToHalfWidth(): void
    {
        $this->assertSame('A-001', normalize_code('Ａ−００１'));
    }

    public function testNormalizeCodeUpperAndTrim(): void
    {
        $this->assertSame('ABC', normalize_code(' abc '));
    }

    public function testNormalizeCodeKeepsHalfWidth(): void
    {
        $this->assertSame('A-001', normalize_code('A-001'));
    }

    public function test金額Normal(): void
    {
        $this->assertSame(100.0, 金額(10, 10, 100));
        $this->assertSame(150.0, 金額(15, 10, 100));
    }

    public function test金額ZeroBaseGuard(): void
    {
        $this->assertSame(0.0, 金額(5, 0, 100));
        $this->assertSame(0.0, 金額(5, -1, 100));
    }

    public function test金額ZeroQty(): void
    {
        $this->assertSame(0.0, 金額(0, 10, 100));
    }
}
