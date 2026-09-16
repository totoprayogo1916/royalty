<?php

namespace Esoftdream\Royalty\Tests\Config;

use Esoftdream\Royalty\Config\Royalty as RoyaltyConfig;
use PHPUnit\Framework\TestCase;

class RoyaltyConfigTest extends TestCase
{
    public function testDefaultMinimumRoyaltyValue()
    {
        $config = new RoyaltyConfig();
        $this->assertEquals(500000, $config->minimumRoyalty);
    }
}
