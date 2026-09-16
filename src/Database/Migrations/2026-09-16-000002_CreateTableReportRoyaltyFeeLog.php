<?php

namespace Esoftdream\Royalty\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTableReportRoyaltyFeeLog extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'royalty_fee_log_id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'royalty_fee_log_value' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => false,
                'default'    => 0,
                'comment'    => 'Nilai Royalti',
            ],
            'royalty_fee_log_type' => [
                'type'       => 'ENUM',
                'constraint' => ['in', 'out', 'outmin'],
                'default'    => 'out',
                'comment'    => 'Keluar / Masuk',
            ],
            'royalty_fee_log_note' => [
                'type'    => 'MEDIUMTEXT',
                'null'    => true,
                'default' => null,
                'comment' => 'Keterangan',
            ],
            'royalty_fee_log_input_datetime' => [
                'type'    => 'DATETIME',
                'comment' => 'Tanggal Input',
            ],
            'royalty_fee_log_input_administrator_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'comment'    => 'ID Administrator yang Input (Jika Ada)',
            ],
        ]);
        $this->forge->addPrimaryKey('royalty_fee_log_id');
        $this->forge->createTable('report_royalty_fee_log', true, ['comment' => 'Tabel log fee']);
    }

    public function down()
    {
        $this->forge->dropTable('report_royalty_fee_log', true);
    }
}
