<?php

namespace Esoftdream\Royalty\Database\Migrations;

use CodeIgniter\Database\Migration;

class ReportRoyaltyFee extends Migration
{
    public function up()
    {
        // tabel report_royalty_fee
        $this->forge->addField([
            'royalty_fee_id'                    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true, 'comment' => 'ID Increment Royalti'],
            'royalty_fee_acc'                   => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'comment' => 'Nilai Royalti Terhitung'],
            'royalty_fee_paid'                  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'comment' => 'Nilai Royalti Dibayar'],
            'royalty_fee_last_updated_datetime' => ['type' => 'DATETIME', 'comment' => 'Tanggal Terakhir Update'],
        ]);

        $this->forge->addPrimaryKey('royalty_fee_id');
        $this->forge->createTable('report_royalty_fee', true);

        // tabel report_royalty_fee_log
        $this->forge->addField([
            'royalty_fee_log_id'                     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true, 'comment' => 'ID Increment Royalti Log'],
            'royalty_fee_log_value'                  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Nilai Royalti'],
            'royalty_fee_log_type'                   => ['type' => 'ENUM', 'constraint' => ['in', 'out', 'outmin'], 'default' => 'out', 'comment' => 'Keluar / Masuk'],
            'royalty_fee_log_note'                   => ['type' => 'MEDIUMTEXT', 'null' => true, 'default' => null, 'comment' => 'Keterangan'],
            'royalty_fee_log_input_datetime'         => ['type' => 'DATETIME', 'comment' => 'Tanggal Input'],
            'royalty_fee_log_input_administrator_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'default' => null, 'comment' => 'ID Administrator yang Input (Jika Ada)'],
        ]);

        $this->forge->addPrimaryKey('royalty_fee_log_id');
        $this->forge->createTable('report_royalty_fee_log', true);

        // tabel report_royalty_fee_log_monthly
        $this->forge->addField([
            'royalty_fee_log_monthly_id'         => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true, 'comment' => 'ID Increment Royalti Bulanan'],
            'royalty_fee_log_monthly_year_month' => ['type' => 'DATE', 'null' => false, 'comment' => 'Bulan dan Tahun berjalan'],
            'royalty_fee_log_monthly_value_in'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Topup deposit'],
            'royalty_fee_log_monthly_value_out'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Fee Royalty'],
            'royalty_fee_log_monthly_bill'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Tagihan'],
            'royalty_fee_log_monthly_paid'       => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Tagihan Terbayar'],
            'royalty_fee_log_monthly_unpaid'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Tagihan Belum Terbayar'],
            'royalty_fee_log_monthly_status'     => ['type' => 'ENUM', 'constraint' => ['paid', 'unpaid'], 'default' => 'unpaid', 'comment' => 'Status Bayar, Lunas atau Belum Lunas'],
            'royalty_fee_log_monthly_balance'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0, 'comment' => 'Saldo Deposit'],
        ]);

        $this->forge->addPrimaryKey('royalty_fee_log_monthly_id');
        $this->forge->createTable('report_royalty_fee_log_monthly', true);
    }

    public function down()
    {
        $this->forge->dropTable('report_royalty_fee', true);
        $this->forge->dropTable('report_royalty_fee_log', true);
        $this->forge->dropTable('report_royalty_fee_log_monthly', true);
    }
}
