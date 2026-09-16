<?php

namespace Esoftdream\Royalty\Tests\Unit;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use Esoftdream\Royalty\Libraries\Royalty as RoyaltyLibrary;
use Esoftdream\Royalty\Royalty;
use PHPUnit\Framework\TestCase;

class RoyaltyTest extends TestCase
{
    public function testInitializationWithCustomMinRoyalty()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock, 750000);

        $this->assertInstanceOf(Royalty::class, $royalty);
    }

    public function testInitMethodUpdatesDatetimeContext()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock, 500000);

        $result = $royalty->init('2026-05-15 10:30:00');
        $this->assertSame($royalty, $result);
    }

    public function testLibraryAliasInheritsFromMainRoyaltyClass()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $library = new RoyaltyLibrary($dbMock, 500000);

        $this->assertInstanceOf(Royalty::class, $library);
    }

    public function testSnakeCaseAliasesCallCamelCaseMethods()
    {
        $dbMock     = $this->createMock(BaseConnection::class);
        $builderMock = $this->createMock(BaseBuilder::class);
        $resultMock  = $this->createMock(ResultInterface::class);

        $resultMock->method('getRow')->willReturn(null);

        $builderMock->method('set')->willReturnSelf();
        $builderMock->method('where')->willReturnSelf();
        $builderMock->method('update')->willReturn(true);
        $builderMock->method('select')->willReturnSelf();
        $builderMock->method('orderBy')->willReturnSelf();
        $builderMock->method('get')->willReturn($resultMock);
        $builderMock->method('insert')->willReturn(true);

        $dbMock->method('table')->willReturn($builderMock);
        $dbMock->method('affectedRows')->willReturn(1);
        $dbMock->method('insertID')->willReturn(10);

        $royalty = new Royalty($dbMock, 500000);
        $result  = $royalty->update_royalty(25000);

        $this->assertEquals(10, $result);
    }
}
