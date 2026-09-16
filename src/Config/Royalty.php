<?php

namespace Esoftdream\Royalty\Config;

use CodeIgniter\Config\BaseConfig;

class Royalty extends BaseConfig
{
    /**
     * Minimum Royalty Fee per month.
     */
    public int $minimumRoyalty = 500000;

    public function __construct()
    {
        parent::__construct();

        if (defined('MINIMUM_ROYALTY')) {
            $this->minimumRoyalty = (int) MINIMUM_ROYALTY;
        }
    }
}
