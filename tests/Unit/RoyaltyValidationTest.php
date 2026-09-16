<?php

namespace Esoftdream\Royalty\Tests\Unit;

use CodeIgniter\Database\BaseConnection;
use Esoftdream\Royalty\Exceptions\InvalidArgumentException;
use Esoftdream\Royalty\Royalty;
use PHPUnit\Framework\TestCase;

class RoyaltyValidationTest extends TestCase
{
    public function testNegativeMinimumRoyaltyThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $dbMock = $this->createMock(BaseConnection::class);
        new Royalty($dbMock, -500);
    }

    public function testInvalidDatetimeContextThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock);
        $royalty->init('invalid-date-string');
    }

    public function testNegativeRoyaltyUpdateThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock);
        $royalty->updateRoyalty(-1000);
    }

    public function testZeroOrNegativeTopUpThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock);
        $royalty->topUp(0, '2026-09-16');
    }
}
