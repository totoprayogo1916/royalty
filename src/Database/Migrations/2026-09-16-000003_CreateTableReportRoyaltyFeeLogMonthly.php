<?php

namespace Esoftdream\Royalty\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTableReportRoyaltyFeeLogMonthly extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'royalty_fee_log_monthly_id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'royalty_fee_log_monthly_year_month' => [
                'type'    => 'DATE',
                'null'    => false,
                'comment' => 'Bulan Berjalan',
            ],
            'royalty_fee_log_monthly_value_in' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Top Up Deposit',
            ],
            'royalty_fee_log_monthly_value_out' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Fee Royalti',
            ],
            'royalty_fee_log_monthly_min' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Minimal Royalty Bulanan',
            ],
            'royalty_fee_log_monthly_bill' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Tagihan',
            ],
            'royalty_fee_log_monthly_paid' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Tagihan Terbayar',
            ],
            'royalty_fee_log_monthly_unpaid' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Tagihan Belum Terbayar',
            ],
            'royalty_fee_log_monthly_status' => [
                'type'       => 'ENUM',
                'constraint' => ['paid', 'unpaid'],
                'default'    => 'unpaid',
                'null'       => false,
                'comment'    => 'Lunas / Belum Lunas',
            ],
            'royalty_fee_log_monthly_balance' => [
                'type'       => 'INT',
                'constraint' => 10,
                'default'    => 0,
                'null'       => false,
                'comment'    => 'Saldo Deposit',
            ],
        ]);

        $this->forge->addPrimaryKey('royalty_fee_log_monthly_id');
        $this->forge->createTable('report_royalty_fee_log_monthly', true, ['comment' => 'Tabel log fee bulanan']);
    }

    public function down()
    {
        $this->forge->dropTable('report_royalty_fee_log_monthly', true);
    }
}
