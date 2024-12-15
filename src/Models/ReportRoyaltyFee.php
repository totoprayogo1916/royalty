<?php

namespace Esoftdream\Royalty\Models;

use CodeIgniter\Model;
use stdClass;

class ReportRoyaltyFee extends Model
{
    protected $table                  = 'report_royalty_fee';
    protected $primaryKey             = 'royalty_fee_id';
    protected $useAutoIncrement       = true;
    protected $returnType             = 'object';
    protected $useSoftDeletes         = false;
    protected $protectFields          = false;
    protected $allowedFields          = [];
    protected bool $allowEmptyInserts = false;

    /**
     * Ambil Saldo terakhir
     */
    public function getLastBalance(): int
    {
        return $this->db->table('report_royalty_fee_log_monthly')
            ->select('royalty_fee_log_monthly_balance')
            ->orderBy('royalty_fee_log_monthly_year_month', 'desc')
            ->get()
            ->getRow('royalty_fee_log_monthly_balance') ?? 0;
    }

    /**
     * Ambil log royalty berdasarkan bulan dan tahun
     *
     * @return object|stdClass|null
     */
    public function getLogs(int $month, int $year)
    {
        return $this->db->table('report_royalty_fee_log_monthly')
            ->select('
                royalty_fee_log_monthly_id,
                royalty_fee_log_monthly_bill,
                royalty_fee_log_monthly_paid,
                royalty_fee_log_monthly_value_out,
                royalty_fee_log_monthly_unpaid,
                royalty_fee_log_monthly_balance,
                royalty_fee_log_monthly_value_in,
                royalty_fee_log_monthly_status')
            ->where("MONTH(royalty_fee_log_monthly_year_month) = {$month} AND YEAR(royalty_fee_log_monthly_year_month) = {$year}")
            ->get()
            ->getRowObject();
    }

    public function getUnpaidLists()
    {
        return $this->db->table('report_royalty_fee_log_monthly')
            ->getWhere(['royalty_fee_log_monthly_status' => 'unpaid'])
            ->getResultObject();
    }
}
