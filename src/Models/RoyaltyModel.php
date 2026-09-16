<?php

namespace Esoftdream\Royalty\Models;

use CodeIgniter\Model;

class RoyaltyModel extends Model
{
    protected $table            = 'report_royalty_fee';
    protected $primaryKey       = 'royalty_fee_id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'royalty_fee_acc',
        'royalty_fee_paid',
        'royalty_fee_last_updated_datetime',
    ];

    /**
     * Get summary fee info
     */
    public function getSummary()
    {
        return $this->db->table('report_royalty_fee')
            ->where('royalty_fee_id', 1)
            ->get()
            ->getRow();
    }

    /**
     * Get monthly logs by month & year
     */
    public function getMonthlyLog(int $month, int $year)
    {
        return $this->db->table('report_royalty_fee_log_monthly')
            ->where("MONTH(royalty_fee_log_monthly_year_month) = {$month} AND YEAR(royalty_fee_log_monthly_year_month) = {$year}")
            ->get()
            ->getRow();
    }

    /**
     * Get detailed log list for a given month & year
     */
    public function getLogsByMonthYear(int $month, int $year)
    {
        return $this->db->table('report_royalty_fee_log')
            ->where("MONTH(royalty_fee_log_input_datetime) = {$month} AND YEAR(royalty_fee_log_input_datetime) = {$year}")
            ->orderBy('royalty_fee_log_input_datetime', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Get annual breakdown of royalty by month
     */
    public function getAnnualSummary(int $year)
    {
        return $this->db->table('report_royalty_fee_log_monthly')
            ->where("YEAR(royalty_fee_log_monthly_year_month) = {$year}")
            ->orderBy('royalty_fee_log_monthly_year_month', 'ASC')
            ->get()
            ->getResult();
    }

    /**
     * Get list of distinct years available in royalty log
     */
    public function getAvailableYears(): array
    {
        $result = $this->db->table('report_royalty_fee_log')
            ->select("YEAR(royalty_fee_log_input_datetime) AS year")
            ->groupBy("YEAR(royalty_fee_log_input_datetime)")
            ->orderBy("year", "DESC")
            ->get()
            ->getResult();

        return array_map(fn($row) => (int) $row->year, $result);
    }
}
