<?php
declare(strict_types=1);

final class NumberingTest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        fresh_db();
    }

    public function testHandOrderSequential(): void
    {
        $n1 = next_番号('手配');
        $n2 = next_番号('手配');
        $this->assertSame('H0000000001', $n1);
        $this->assertSame('H0000000002', $n2);
    }

    public function testShipNumberSuffix(): void
    {
        $n = next_番号('出庫');
        $this->assertStringStartsWith('S', $n);
        $this->assertSame('S0000000001', $n);
    }

    public function testTwoSeriesAreIndependent(): void
    {
        next_番号('手配');
        next_番号('手配');
        $this->assertSame('S0000000001', next_番号('出庫'));
    }

    public function testPaddingToTenDigits(): void
    {
        $pdo = db();
        $pdo->exec("UPDATE 採番 SET 連番 = 999999999 WHERE 種別 = '手配'");
        $this->assertSame('H1000000000', next_番号('手配'));
    }

    public function testWorksInsideTransaction(): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        $n = next_番号('手配');
        $pdo->commit();
        $this->assertSame('H0000000001', $n);
    }
}
