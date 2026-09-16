<?php

namespace Esoftdream\Royalty\Tests\Unit;

use CodeIgniter\Database\BaseConnection;
use Esoftdream\Royalty\Royalty;
use PHPUnit\Framework\TestCase;

class DynamicRoyaltyTest extends TestCase
{
    public function testSetMinimumRoyaltyUpdatesDefault()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock, 4000000);

        $this->assertEquals(4000000, $royalty->resolveMinimumRoyalty());

        $royalty->setMinimumRoyalty(5000000);
        $this->assertEquals(5000000, $royalty->resolveMinimumRoyalty());
    }

    public function testCustomMinRoyaltyOverrideInResolve()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock, 4000000);

        $this->assertEquals(5000000, $royalty->resolveMinimumRoyalty('2026-09-16', 5000000));
    }

    public function testDynamicResolverCallback()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $royalty = new Royalty($dbMock, 4000000);

        // Define a dynamic resolver rule based on date / omset criteria
        $royalty->setMinimumRoyaltyResolver(function (string $date, $db) {
            $month = date('m', strtotime($date));
            if ($month === '09') {
                return 5000000;
            }
            return 4000000;
        });

        $this->assertEquals(5000000, $royalty->resolveMinimumRoyalty('2026-09-16'));
        $this->assertEquals(4000000, $royalty->resolveMinimumRoyalty('2026-10-01'));
    }
}
