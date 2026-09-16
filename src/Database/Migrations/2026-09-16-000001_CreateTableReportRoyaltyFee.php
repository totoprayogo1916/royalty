<?php

namespace Esoftdream\Royalty\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTableReportRoyaltyFee extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'royalty_fee_id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'royalty_fee_acc' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => false,
                'default'    => 0,
                'comment'    => 'Nilai Royalti Terhitung',
            ],
            'royalty_fee_paid' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => false,
                'default'    => 0,
                'comment'    => 'Nilai Royalti Dibayar',
            ],
            'royalty_fee_last_updated_datetime' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Tanggal Terakhir Update',
            ],
        ]);
        $this->forge->addPrimaryKey('royalty_fee_id');
        $this->forge->createTable('report_royalty_fee', true, ['comment' => 'Tabel summary royalty fee']);
    }

    public function down()
    {
        $this->forge->dropTable('report_royalty_fee', true);
    }
}
