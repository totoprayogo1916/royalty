<?php

namespace Esoftdream\Royalty\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Esoftdream\Royalty\Royalty;
use Throwable;

class RoyaltyAdjustment extends BaseCommand
{
    /**
     * Command group name
     */
    protected $group = 'Royalty';

    /**
     * Command name
     */
    protected $name = 'royalty:adjustment';

    /**
     * Command description
     */
    protected $description = 'Proses penyesuaian minimal royalty IT bulanan.';

    /**
     * Command usage
     */
    protected $usage = 'royalty:adjustment';

    public function run(array $params)
    {
        CLI::write('Memulai penyesuaian royalty IT...', 'yellow');

        try {
            $db      = Database::connect();
            $royalty = new Royalty($db);
            $message = $royalty->processAdjustment();

            CLI::write($message, 'green');
        } catch (Throwable $th) {
            CLI::error('Gagal penyesuaian royalty IT: ' . $th->getMessage());
        }
    }
}
