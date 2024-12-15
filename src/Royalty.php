<?php

namespace Esoftdream\Royalty;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Esoftdream\Royalty\Models\ReportRoyaltyFee;
use Exception;

class Royalty
{
    private BaseConnection $db;

    /**
     * Tanggal dalam format YYYY-MM-DD
     */
    private string $datetime;

    /**
     * Tanggal dalam format YYYY-MM-DD
     */
    private string $date;

    /**
     * Bulan dalam format MM
     */
    private int $month;

    /**
     * Tahun dalam format YYYY
     */
    private int $year;

    /**
     * Nilai minimum royalty perbulan
     */
    private int $minRoyalty;

    public function __construct(int $minRoyalty)
    {
        $this->db         = Database::connect();
        $this->date       = date('Y-m-d');
        $this->datetime   = date('Y-m-d H:i:s');
        $this->month      = date('m', strtotime($this->date));
        $this->year       = date('Y', strtotime($this->date));
        $this->minRoyalty = $minRoyalty;
    }

    /**
     * Simpan log royalty
     */
    public function insertRoyaltyFeeLog(string $type, int $value, string $note, int $administratorId = 0): bool
    {
        $this->db->table('report_royalty_fee_log')->insert([
            'royalty_fee_log_value'                  => $value,
            'royalty_fee_log_type'                   => $type,
            'royalty_fee_log_note'                   => $note,
            'royalty_fee_log_input_datetime'         => $this->datetime,
            'royalty_fee_log_input_administrator_id' => $administratorId,
        ]);

        return ! ($this->db->affectedRows() <= 0);
    }

    /**
     * Insert / Upudate royalty fee
     *
     * @param float|int $price
     * @param mixed     $royalty
     */
    public function insertRoyaltyFee($royalty): int
    {
        $royaltyData = [
            'royalty_fee_acc'                   => "royalty_fee_acc + {$royalty}",
            'royalty_fee_last_updated_datetime' => $this->datetime,
        ];

        $query = $this->db->table('report_royalty_fee');

        if ($query->where('royalty_fee_id', 1)->countAllResults() > 0) {
            // Update existing royalty
            $query->set($royaltyData, false)->where('royalty_fee_id', 1)->update();
        } else {
            // Insert new royalty
            $royaltyData['royalty_fee_id']   = 1;
            $royaltyData['royalty_fee_paid'] = 0;
            $royaltyData['royalty_fee_acc']  = $royalty;
            $query->insert($royaltyData);
        }

        if ($this->db->affectedRows() <= 0) {
            throw new Exception('Gagal ubah royalty', 1);
        }

        return $royalty;
    }

    public function upsert(int $royalty): int
    {
        $royalty_model = new ReportRoyaltyFee();
        $last_balance  = $royalty_model->getLastBalance();
        $get_logs      = $royalty_model->getLogs($this->month, $this->year);

        // Hitung nilai minimum royalty yang harus dibayar
        $bill        = max($royalty, $this->minRoyalty);
        $new_balance = $last_balance;

        if ($get_logs) {
            $log = $get_logs;

            // Update log jika ada
            $new_out = $log->royalty_fee_log_monthly_value_out + $royalty;
            $paid    = $new_balance > 0 ? min($bill, $new_balance) + $log->royalty_fee_log_monthly_paid : $log->royalty_fee_log_monthly_paid;
            $unpaid  = $bill - $paid;

            // Update balance
            $new_balance -= ($royalty > $new_balance) ? $new_balance : $royalty;

            // Tentukan status pembayaran
            $status = $unpaid === 0 ? 'paid' : 'unpaid';

            // Update data ke database
            $this->db->table('report_royalty_fee_log_monthly')
                ->where('royalty_fee_log_monthly_id', $log->royalty_fee_log_monthly_id)
                ->update([
                    'royalty_fee_log_monthly_bill'      => $bill,
                    'royalty_fee_log_monthly_paid'      => $paid,
                    'royalty_fee_log_monthly_unpaid'    => max(0, $unpaid),
                    'royalty_fee_log_monthly_value_out' => $new_out,
                    'royalty_fee_log_monthly_balance'   => $new_balance,
                    'royalty_fee_log_monthly_status'    => $status,
                ]);

            if ($this->db->affectedRows() < 0) {
                throw new Exception('Gagal mengubah royalty monthly', 1);
            }

            return $log->royalty_fee_log_monthly_id;
        }

        // Insert data baru jika tidak ada log
        $paid   = $last_balance > 0 ? min($bill, $last_balance) : 0;
        $unpaid = $bill - $paid;
        $status = $unpaid === 0 ? 'paid' : 'unpaid';
        $new_balance -= $bill;

        // Siapkan data untuk insert
        $data_insert = [
            'royalty_fee_log_monthly_balance'    => $new_balance,
            'royalty_fee_log_monthly_year_month' => $this->date,
            'royalty_fee_log_monthly_value_out'  => $royalty,
            'royalty_fee_log_monthly_bill'       => $bill,
            'royalty_fee_log_monthly_paid'       => $paid,
            'royalty_fee_log_monthly_unpaid'     => $unpaid,
            'royalty_fee_log_monthly_status'     => $status,
        ];

        // Insert data ke database
        $this->db->table('report_royalty_fee_log_monthly')->insert($data_insert);

        // Validasi apakah insert berhasil
        if ($this->db->affectedRows() <= 0) {
            throw new Exception('Gagal menambahkan royalty fee log monthly');
        }

        // Kembalikan ID dari data yang baru di-insert
        return $this->db->insertID();
    }

    public function upsertRoyaltyFeeLogMonthly($royalty)
    {
        $balance = $this->db->table('report_royalty_fee_log_monthly')
            ->select('royalty_fee_log_monthly_balance')
            ->orderBy('royalty_fee_log_monthly_year_month', 'desc')
            ->get()
            ->getRow('royalty_fee_log_monthly_balance');

        $log = $this->db->table('report_royalty_fee_log_monthly')
            ->select('*')
            ->where('MONTH(royalty_fee_log_monthly_year_month)', $this->month)
            ->where('YEAR(royalty_fee_log_monthly_year_month)', $this->year)
            ->get()
            ->getRow();

        $newOut       = $log ? $log->royalty_fee_log_monthly_value_out + $royalty : $royalty;
        $newBill      = max($newOut, $this->minRoyalty);
        $paid         = $balance > 0 ? min($newBill, ($log ? $log->royalty_fee_log_monthly_paid : 0) + $royalty) : ($log ? $log->royalty_fee_log_monthly_paid : 0);
        $unpaid       = max($newBill - $paid, 0);
        $balanceAfter = $balance - $royalty;

        $data = [
            'royalty_fee_log_monthly_bill'       => $newBill,
            'royalty_fee_log_monthly_paid'       => $paid,
            'royalty_fee_log_monthly_unpaid'     => $unpaid,
            'royalty_fee_log_monthly_value_out'  => $newOut,
            'royalty_fee_log_monthly_balance'    => $balanceAfter,
            'royalty_fee_log_monthly_status'     => ($unpaid > 0) ? 'unpaid' : 'paid',
            'royalty_fee_log_monthly_year_month' => $this->date,
        ];

        if ($log) {
            $this->db->table('report_royalty_fee_log_monthly')
                ->where('royalty_fee_log_monthly_id', $log->royalty_fee_log_monthly_id)
                ->update($data);

            if ($this->db->affectedRows() < 0) {
                throw new Exception('Gagal ubah royalty monthly', 1);
            }
        } else {
            $this->db->table('report_royalty_fee_log_monthly')->insert($data);

            if ($this->db->affectedRows() <= 0) {
                throw new Exception('Gagal tambah royalty monthly', 1);
            }

            return $this->db->insertID();
        }

        return true;
    }

    public function top_up($top_up, $date, $log_id)
    {
        $this->date = $date;
        $log        = $this->db->table('report_royalty_fee_log_monthly')
            ->select('royalty_fee_log_monthly_id, royalty_fee_log_monthly_bill, royalty_fee_log_monthly_paid, royalty_fee_log_monthly_value_out, royalty_fee_log_monthly_balance, royalty_fee_log_monthly_value_in, royalty_fee_log_monthly_status')
            ->where('royalty_fee_log_monthly_id', $log_id)
            ->get()
            ->getRow();

        $balance     = $top_up;
        $list_unpaid = $this->db->table('report_royalty_fee_log_monthly')->getWhere(['royalty_fee_log_monthly_status' => 'unpaid'])->getResult();

        if (count($list_unpaid) > 0) {
            foreach ($list_unpaid as $key => $value) {
                if ($balance <= 0) {
                    $balance -= ($value->royalty_fee_log_monthly_bill - $value->royalty_fee_log_monthly_paid);
                } else {
                    $this->db->table('report_royalty_fee_log_monthly')
                        ->where('royalty_fee_log_monthly_id', $value->royalty_fee_log_monthly_id)
                        ->update([
                            'royalty_fee_log_monthly_paid'   => ($value->royalty_fee_log_monthly_paid + $balance) > $value->royalty_fee_log_monthly_bill ? $value->royalty_fee_log_monthly_bill : $value->royalty_fee_log_monthly_paid + $balance,
                            'royalty_fee_log_monthly_unpaid' => ($value->royalty_fee_log_monthly_paid + $balance) > $value->royalty_fee_log_monthly_bill ? 0 : ($value->royalty_fee_log_monthly_unpaid - $balance),
                            'royalty_fee_log_monthly_status' => ($value->royalty_fee_log_monthly_paid + $balance) >= $value->royalty_fee_log_monthly_bill ? 'paid' : 'unpaid',
                        ]);

                    if ($this->db->affectedRows() <= 0) {
                        throw new Exception('Gagal ubah royalty monthly', 1);
                    }

                    $balance -= ($value->royalty_fee_log_monthly_bill - $value->royalty_fee_log_monthly_paid);
                }
            }
        }

        $last_id = $this->db->table('report_royalty_fee_log_monthly')->get()->getLastRow();

        $this->db->table('report_royalty_fee_log_monthly')
            ->where('royalty_fee_log_monthly_id', $log_id)
            ->update([
                'royalty_fee_log_monthly_value_in' => $log->royalty_fee_log_monthly_value_in + $top_up,
            ]);

        if ($this->db->affectedRows() <= 0) {
            throw new Exception('Gagal ubah royalty monthly', 1);
        }

        $this->db->table('report_royalty_fee_log_monthly')
            ->where('royalty_fee_log_monthly_id', $last_id->royalty_fee_log_monthly_id)
            ->update([
                'royalty_fee_log_monthly_balance' => $balance,
            ]);

        if ($this->db->affectedRows() <= 0) {
            throw new Exception('Gagal ubah royalty monthly', 1);
        }

        return true;
    }
}
