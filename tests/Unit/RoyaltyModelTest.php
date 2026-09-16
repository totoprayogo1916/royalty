<?php

namespace Esoftdream\Royalty\Tests\Unit;

use CodeIgniter\Database\BaseConnection;
use Esoftdream\Royalty\Models\RoyaltyModel;
use PHPUnit\Framework\TestCase;

class RoyaltyModelTest extends TestCase
{
    public function testModelHasCorrectTableNameAndPrimaryKey()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $model  = new RoyaltyModel($dbMock);

        $refClass = new \ReflectionClass($model);

        $tableProperty = $refClass->getProperty('table');
        $tableProperty->setAccessible(true);
        $this->assertEquals('report_royalty_fee', $tableProperty->getValue($model));

        $primaryKeyProperty = $refClass->getProperty('primaryKey');
        $primaryKeyProperty->setAccessible(true);
        $this->assertEquals('royalty_fee_id', $primaryKeyProperty->getValue($model));
    }

    public function testModelHasAllowedFieldsConfigured()
    {
        $dbMock = $this->createMock(BaseConnection::class);
        $model  = new RoyaltyModel($dbMock);

        $refClass          = new \ReflectionClass($model);
        $allowedFieldsProp = $refClass->getProperty('allowedFields');
        $allowedFieldsProp->setAccessible(true);

        $expected = [
            'royalty_fee_acc',
            'royalty_fee_paid',
            'royalty_fee_last_updated_datetime',
        ];

        $this->assertEquals($expected, $allowedFieldsProp->getValue($model));
    }
}
