<?php

namespace Esoftdream\Royalty\Tests\Unit;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ResultInterface;
use Esoftdream\Royalty\Royalty;
use PHPUnit\Framework\TestCase;

class RoyaltyTopUpTest extends TestCase
{
    public function testTopUpAutoResolvesLogIdWhenNull()
    {
        $dbMock      = $this->createMock(BaseConnection::class);
        $builderMock = $this->createMock(BaseBuilder::class);
        $resultMock  = $this->createMock(ResultInterface::class);

        $logObj = (object) [
            'royalty_fee_log_monthly_id'        => 5,
            'royalty_fee_log_monthly_bill'      => 500000,
            'royalty_fee_log_monthly_paid'      => 0,
            'royalty_fee_log_monthly_value_out' => 0,
            'royalty_fee_log_monthly_balance'  => -500000,
            'royalty_fee_log_monthly_value_in'  => 0,
            'royalty_fee_log_monthly_status'    => 'unpaid',
        ];

        $resultMock->method('getRow')->willReturn($logObj);
        $resultMock->method('getResult')->willReturn([]);
        $resultMock->method('getLastRow')->willReturn($logObj);

        $builderMock->method('select')->willReturnSelf();
        $builderMock->method('where')->willReturnSelf();
        $builderMock->method('getCompiledSelect')->willReturn('SELECT * FROM report_royalty_fee_log_monthly');
        $builderMock->method('get')->willReturn($resultMock);
        $builderMock->method('getWhere')->willReturn($resultMock);
        $builderMock->method('set')->willReturnSelf();
        $builderMock->method('update')->willReturn(true);
        $builderMock->method('insert')->willReturn(true);

        $dbMock->method('table')->willReturn($builderMock);
        $dbMock->method('query')->willReturn($resultMock);
        $dbMock->method('affectedRows')->willReturn(1);

        $royalty = new Royalty($dbMock, 500000);
        $success = $royalty->topUp(500000, '2026-09-16 10:00:00', null, 'Auto resolution test');

        $this->assertTrue($success);
    }
}
