<?php

namespace Esoftdream\Royalty;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use Config\Database;
use Esoftdream\Royalty\Config\Royalty as RoyaltyConfig;
use Esoftdream\Royalty\Exceptions\InvalidArgumentException;
use Esoftdream\Royalty\Exceptions\RoyaltyException;
use Esoftdream\Royalty\Exceptions\RoyaltyNotFoundException;
use Throwable;

class Royalty
{
    private BaseConnection $db;
    private int $minRoyalty;
    /** @var callable|null */
    private $minRoyaltyResolver = null;
    private string $datetime;
    private string $date;
    private string $month;
    private string $year;

    public function __construct(?BaseConnection $db = null, ?int $minRoyalty = null)
    {
        $this->db = $db ?? Database::connect();

        if ($minRoyalty !== null) {
            if ($minRoyalty < 0) {
                throw new InvalidArgumentException('Minimum royalty fee tidak boleh negatif.');
            }
            $this->minRoyalty = $minRoyalty;
        } else {
            $config = new RoyaltyConfig();
            $this->minRoyalty = $config->minimumRoyalty;
        }

        $this->datetime = date('Y-m-d H:i:s');
        $this->date     = date('Y-m-d', strtotime($this->datetime));
        $this->month    = date('m', strtotime($this->datetime));
        $this->year     = date('Y', strtotime($this->datetime));
    }

    /**
     * Initialize instance with a specific datetime context
     */
    public function init(string $datetime): self
    {
        if (strtotime($datetime) === false) {
            throw new InvalidArgumentException("Format datetime tidak valid: {$datetime}");
        }

        $this->datetime = $datetime;
        $this->date     = date('Y-m-d', strtotime($datetime));
        $this->month    = date('m', strtotime($datetime));
        $this->year     = date('Y', strtotime($datetime));

        return $this;
    }

    /**
     * Set dynamic minimum royalty value
     */
    public function setMinimumRoyalty(int $minRoyalty): self
    {
        if ($minRoyalty < 0) {
            throw new InvalidArgumentException('Minimum royalty fee tidak boleh negatif.');
        }
        $this->minRoyalty = $minRoyalty;

        return $this;
    }

    /**
     * Set a dynamic resolver callback to calculate minimum royalty based on custom business criteria.
     * Callback signature: function(string $date, BaseConnection $db): int
     */
    public function setMinimumRoyaltyResolver(callable $resolver): self
    {
        $this->minRoyaltyResolver = $resolver;

        return $this;
    }

    /**
     * Resolve minimum royalty for a given date or custom override
     */
    public function resolveMinimumRoyalty(?string $date = null, ?int $customMinRoyalty = null): int
    {
        if ($customMinRoyalty !== null) {
            if ($customMinRoyalty < 0) {
                throw new InvalidArgumentException('Minimum royalty fee tidak boleh negatif.');
            }

            return $customMinRoyalty;
        }

        if (is_callable($this->minRoyaltyResolver)) {
            $resolved = call_user_func($this->minRoyaltyResolver, $date ?? $this->date, $this->db);
            if (is_numeric($resolved) && (int) $resolved >= 0) {
                return (int) $resolved;
            }
        }

        return $this->minRoyalty;
    }

    /**
     * Update/Accumulate royalty fee
     */
    public function updateRoyalty(int|float $royalty, ?int $customMinRoyalty = null): bool|int
    {
        if ($royalty < 0) {
            throw new InvalidArgumentException('Nominal royalty fee tidak boleh negatif.');
        }

        $effectiveMinRoyalty = $this->resolveMinimumRoyalty($this->date, $customMinRoyalty);

        try {
            $this->db->transBegin();

            $this->db->table('report_royalty_fee')
                ->set('royalty_fee_acc', 'royalty_fee_acc + ' . (int) $royalty, false)
                ->set('royalty_fee_last_updated_datetime', $this->datetime)
                ->where('royalty_fee_id', 1)
                ->update();

            if ($this->db->affectedRows() < 0) {
                throw new RoyaltyException('Gagal update royalty fee.', 1);
            }

            $sqlBalance = $this->db->table('report_royalty_fee_log_monthly')
                ->select('royalty_fee_log_monthly_balance')
                ->orderBy('royalty_fee_log_monthly_year_month', 'desc')
                ->getCompiledSelect() . ' FOR UPDATE';

            $balance = $this->db->query($sqlBalance)->getRow('royalty_fee_log_monthly_balance') ?? 0;

            $sqlLog = $this->db->table('report_royalty_fee_log_monthly')
                ->select('
                    royalty_fee_log_monthly_id,
                    royalty_fee_log_monthly_bill,
                    royalty_fee_log_monthly_paid,
                    royalty_fee_log_monthly_value_out,
                    royalty_fee_log_monthly_unpaid,
                    royalty_fee_log_monthly_balance')
                ->where("MONTH(royalty_fee_log_monthly_year_month) = {$this->month} AND YEAR(royalty_fee_log_monthly_year_month) = {$this->year}")
                ->getCompiledSelect() . ' FOR UPDATE';

            $log = $this->db->query($sqlLog)->getRow();

            if ($log) {
                $this->updateMonthlyLog($royalty, $balance, $log, $effectiveMinRoyalty);
                $this->db->transCommit();

                $this->triggerEvent('royalty_updated', ['amount' => $royalty, 'datetime' => $this->datetime]);
                return true;
            }

            $insertId = $this->insertMonthlyLog($royalty, $balance, $effectiveMinRoyalty);
            $this->db->transCommit();

            $this->triggerEvent('royalty_updated', ['amount' => $royalty, 'datetime' => $this->datetime, 'log_id' => $insertId]);
            return $insertId;
        } catch (Throwable $th) {
            $this->db->transRollback();
            if ($th instanceof RoyaltyException) {
                throw $th;
            }
            throw new RoyaltyException($th->getMessage(), (int) $th->getCode(), $th);
        }
    }

    /**
     * Snake_case alias for updateRoyalty
     */
    public function update_royalty(int|float $royalty, ?int $customMinRoyalty = null): bool|int
    {
        return $this->updateRoyalty($royalty, $customMinRoyalty);
    }

    private function insertMonthlyLog(int|float $royalty, int|float $balance, ?int $minRoyalty = null): int
    {
        $min = $minRoyalty ?? $this->minRoyalty;

        $dataInsert = [
            'royalty_fee_log_monthly_balance'    => $royalty > $min ? ($balance - $royalty) : ($balance - $min),
            'royalty_fee_log_monthly_year_month' => $this->date,
            'royalty_fee_log_monthly_value_out'  => $royalty,
            'royalty_fee_log_monthly_bill'       => $royalty > $min ? $royalty : $min,
            'royalty_fee_log_monthly_min'        => $min,
        ];

        $dataInsert['royalty_fee_log_monthly_paid']   = $balance > 0 ? ($dataInsert['royalty_fee_log_monthly_balance'] >= 0 ? $dataInsert['royalty_fee_log_monthly_bill'] : $balance) : 0;
        $dataInsert['royalty_fee_log_monthly_unpaid'] = $dataInsert['royalty_fee_log_monthly_bill'] - $dataInsert['royalty_fee_log_monthly_paid'];
        $dataInsert['royalty_fee_log_monthly_status'] = $dataInsert['royalty_fee_log_monthly_bill'] == $dataInsert['royalty_fee_log_monthly_paid'] ? 'paid' : 'unpaid';

        $this->db->table('report_royalty_fee_log_monthly')->insert($dataInsert);

        if ($this->db->affectedRows() <= 0) {
            throw new RoyaltyException('Gagal tambah royalty monthly', 1);
        }

        return (int) $this->db->insertID();
    }

    private function updateMonthlyLog(int|float $royalty, int|float $balance, object $log, ?int $minRoyalty = null): void
    {
        $min = $minRoyalty ?? $this->minRoyalty;

        if ($log->royalty_fee_log_monthly_value_out > $min) {
            $newBill = $log->royalty_fee_log_monthly_value_out + $royalty;
            $newOut  = $log->royalty_fee_log_monthly_value_out + $royalty;
            $paid    = $balance <= 0 ? $log->royalty_fee_log_monthly_paid : ($royalty < $balance ? $log->royalty_fee_log_monthly_paid + $royalty : $log->royalty_fee_log_monthly_paid + $balance);
            $unpaid  = $newBill - $paid;
            $balance -= $royalty;
        } elseif (($log->royalty_fee_log_monthly_value_out + $royalty) > $min) {
            $newBill = $log->royalty_fee_log_monthly_value_out + $royalty;
            $newOut  = $log->royalty_fee_log_monthly_value_out + $royalty;
            $paid    = $balance <= 0 ? $log->royalty_fee_log_monthly_paid : ($royalty < $balance ? $log->royalty_fee_log_monthly_paid + $royalty : $log->royalty_fee_log_monthly_paid + $balance);
            $unpaid  = $newBill - $paid;
            $balance -= (($log->royalty_fee_log_monthly_value_out + $royalty) - $min);
        } else {
            $newBill = $log->royalty_fee_log_monthly_bill;
            $newOut  = $log->royalty_fee_log_monthly_value_out + $royalty;
            $paid    = $log->royalty_fee_log_monthly_paid;
            $unpaid  = $log->royalty_fee_log_monthly_unpaid;
        }

        $this->db->table('report_royalty_fee_log_monthly')
            ->where('royalty_fee_log_monthly_id', $log->royalty_fee_log_monthly_id)
            ->update([
                'royalty_fee_log_monthly_bill'      => $newBill,
                'royalty_fee_log_monthly_paid'      => $paid,
                'royalty_fee_log_monthly_unpaid'    => $unpaid < 0 ? 0 : $unpaid,
                'royalty_fee_log_monthly_value_out' => $newOut,
                'royalty_fee_log_monthly_balance'   => $balance,
                'royalty_fee_log_monthly_status'    => ($unpaid > 0) ? 'unpaid' : 'paid',
                'royalty_fee_log_monthly_min'       => $min,
            ]);

        if ($this->db->affectedRows() < 0) {
            throw new RoyaltyException('Gagal ubah royalty monthly', 1);
        }
    }

    /**
     * Top-up deposit royalty
     */
    public function topUp(int|float $topUp, string $date, ?int $logId = null, string $note = '', ?int $adminId = null): bool
    {
        if ($topUp <= 0) {
            throw new InvalidArgumentException('Nominal top-up harus lebih besar dari 0.');
        }

        if (strtotime($date) === false) {
            throw new InvalidArgumentException("Format tanggal tidak valid: {$date}");
        }

        try {
            $this->db->transBegin();

            $this->date = $date;

            if ($logId === null) {
                $month = date('m', strtotime($date));
                $year  = date('Y', strtotime($date));

                $sqlLogRow = $this->db->table('report_royalty_fee_log_monthly')
                    ->select('royalty_fee_log_monthly_id')
                    ->where("MONTH(royalty_fee_log_monthly_year_month) = {$month} AND YEAR(royalty_fee_log_monthly_year_month) = {$year}")
                    ->getCompiledSelect() . ' FOR UPDATE';

                $logRow = $this->db->query($sqlLogRow)->getRow();

                if ($logRow) {
                    $logId = (int) $logRow->royalty_fee_log_monthly_id;
                } else {
                    $this->init($date);
                    $logId = (int) $this->updateRoyalty(0);
                }
            }

            $sqlLog = $this->db->table('report_royalty_fee_log_monthly')
                ->select('royalty_fee_log_monthly_id, royalty_fee_log_monthly_bill, royalty_fee_log_monthly_paid, royalty_fee_log_monthly_value_out, royalty_fee_log_monthly_balance, royalty_fee_log_monthly_value_in, royalty_fee_log_monthly_status')
                ->where('royalty_fee_log_monthly_id', $logId)
                ->getCompiledSelect() . ' FOR UPDATE';

            $log = $this->db->query($sqlLog)->getRow();

            if (!$log) {
                throw new RoyaltyNotFoundException('Data log monthly tidak ditemukan.', 1);
            }

            $balance    = $topUp;
            $listUnpaid = $this->db->table('report_royalty_fee_log_monthly')
                ->getWhere(['royalty_fee_log_monthly_status' => 'unpaid'])
                ->getResult();

            if (count($listUnpaid) > 0) {
                foreach ($listUnpaid as $value) {
                    if ($balance <= 0) {
                        $balance -= ($value->royalty_fee_log_monthly_bill - $value->royalty_fee_log_monthly_paid);
                    } else {
                        $paidNew   = ($value->royalty_fee_log_monthly_paid + $balance) > $value->royalty_fee_log_monthly_bill ? $value->royalty_fee_log_monthly_bill : $value->royalty_fee_log_monthly_paid + $balance;
                        $unpaidNew = ($value->royalty_fee_log_monthly_paid + $balance) > $value->royalty_fee_log_monthly_bill ? 0 : ($value->royalty_fee_log_monthly_unpaid - $balance);
                        $statusNew = ($value->royalty_fee_log_monthly_paid + $balance) >= $value->royalty_fee_log_monthly_bill ? 'paid' : 'unpaid';

                        $this->db->table('report_royalty_fee_log_monthly')
                            ->where('royalty_fee_log_monthly_id', $value->royalty_fee_log_monthly_id)
                            ->update([
                                'royalty_fee_log_monthly_paid'   => $paidNew,
                                'royalty_fee_log_monthly_unpaid' => $unpaidNew,
                                'royalty_fee_log_monthly_status' => $statusNew,
                            ]);

                        if ($this->db->affectedRows() < 0) {
                            throw new RoyaltyException('Gagal ubah royalty monthly', 1);
                        }

                        $balance -= ($value->royalty_fee_log_monthly_bill - $value->royalty_fee_log_monthly_paid);
                    }
                }
            }

            $lastRow = $this->db->table('report_royalty_fee_log_monthly')->get()->getLastRow();

            $this->db->table('report_royalty_fee_log_monthly')
                ->where('royalty_fee_log_monthly_id', $logId)
                ->update([
                    'royalty_fee_log_monthly_value_in' => $log->royalty_fee_log_monthly_value_in + $topUp,
                ]);

            if ($this->db->affectedRows() < 0) {
                throw new RoyaltyException('Gagal ubah royalty monthly', 1);
            }

            if ($lastRow) {
                $this->db->table('report_royalty_fee_log_monthly')
                    ->where('royalty_fee_log_monthly_id', $lastRow->royalty_fee_log_monthly_id)
                    ->update([
                        'royalty_fee_log_monthly_balance' => $balance,
                    ]);

                if ($this->db->affectedRows() < 0) {
                    throw new RoyaltyException('Gagal ubah royalty monthly', 1);
                }
            }

            // Record in summary table
            $this->db->table('report_royalty_fee')
                ->set('royalty_fee_paid', "royalty_fee_paid+{$topUp}", false)
                ->set('royalty_fee_last_updated_datetime', $date)
                ->where('royalty_fee_id', 1)
                ->update();

            // Record in detail log table
            $this->db->table('report_royalty_fee_log')->insert([
                'royalty_fee_log_value'                  => $topUp,
                'royalty_fee_log_type'                   => 'in',
                'royalty_fee_log_note'                   => $note,
                'royalty_fee_log_input_datetime'         => $date,
                'royalty_fee_log_input_administrator_id' => $adminId,
            ]);

            $this->db->transCommit();

            $this->triggerEvent('royalty_topup', ['amount' => $topUp, 'date' => $date, 'note' => $note, 'admin_id' => $adminId]);

            return true;
        } catch (Throwable $th) {
            $this->db->transRollback();
            if ($th instanceof RoyaltyException) {
                throw $th;
            }
            throw new RoyaltyException($th->getMessage(), (int) $th->getCode(), $th);
        }
    }

    /**
     * Snake_case alias for topUp
     */
    public function top_up(int|float $topUp, string $date, ?int $logId = null, string $note = '', ?int $adminId = null): bool
    {
        return $this->topUp($topUp, $date, $logId, $note, $adminId);
    }

    /**
     * Process monthly royalty adjustment (Cron job)
     */
    public function processAdjustment(?BaseConnection $db = null, ?int $customMinRoyalty = null): string
    {
        $db = $db ?? $this->db;

        try {
            $db->transBegin();

            $month = date('m', strtotime($this->date));
            $year  = date('Y', strtotime($this->date));

            $lastMonth          = date('Y-m-d', strtotime('last day of previous month'));
            $lastMonthNum       = date('m', strtotime($lastMonth));
            $lastMonthFormatted = Time::parse($lastMonth)->toLocalizedString('MMMM');
            $lastYear           = date('Y', strtotime($lastMonth));
            $min                = $this->resolveMinimumRoyalty($lastMonth, $customMinRoyalty);

            $royalty = $db->table('report_royalty_fee_log')
                ->selectSum('royalty_fee_log_value')
                ->where("royalty_fee_log_type IN ('out', 'outmin') AND MONTH(royalty_fee_log_input_datetime) = {$lastMonthNum} AND YEAR(royalty_fee_log_input_datetime) = {$lastYear}")
                ->get()
                ->getRow('royalty_fee_log_value') ?? 0;

            if ($royalty < $min) {
                $value = $min - $royalty;
                $db->table('report_royalty_fee')
                    ->set('royalty_fee_acc', "royalty_fee_acc+{$value}", false)
                    ->set('royalty_fee_last_updated_datetime', $this->datetime)
                    ->where('royalty_fee_id', 1)
                    ->update();

                if ($db->affectedRows() <= 0) {
                    throw new RoyaltyException('Gagal ubah royalty', 1);
                }

                $db->table('report_royalty_fee_log')->insert([
                    'royalty_fee_log_value'                  => $value,
                    'royalty_fee_log_type'                   => 'outmin',
                    'royalty_fee_log_note'                   => "Kekurangan Royalty IT di Bulan {$lastMonthFormatted} Tahun {$lastYear}",
                    'royalty_fee_log_input_datetime'         => date('Y-m-t 23:59:59', strtotime($lastMonth)),
                    'royalty_fee_log_input_administrator_id' => 0,
                ]);

                if ($db->affectedRows() <= 0) {
                    throw new RoyaltyException('Gagal tambah riwayat royalty', 1);
                }
            } else {
                $lastRoyalty = $db->table('report_royalty_fee_log_monthly')
                    ->select('royalty_fee_log_monthly_value_out')
                    ->where("MONTH(royalty_fee_log_monthly_year_month) = {$lastMonthNum} AND YEAR(royalty_fee_log_monthly_year_month) = {$lastYear}")
                    ->get()
                    ->getRow('royalty_fee_log_monthly_value_out') ?? 0;

                $value = ($min - $lastRoyalty) < 0 ? 0 : ($min - $lastRoyalty);

                $db->table('report_royalty_fee')
                    ->set('royalty_fee_acc', "royalty_fee_acc+{$value}", false)
                    ->set('royalty_fee_last_updated_datetime', $this->datetime)
                    ->where('royalty_fee_id', 1)
                    ->update();

                if ($db->affectedRows() <= 0) {
                    throw new RoyaltyException('Gagal ubah royalty', 1);
                }

                $db->table('report_royalty_fee_log')->insert([
                    'royalty_fee_log_value'                  => $value,
                    'royalty_fee_log_type'                   => 'out',
                    'royalty_fee_log_note'                   => "Kekurangan Royalty IT di Bulan {$lastMonthFormatted} Tahun {$lastYear}",
                    'royalty_fee_log_input_datetime'         => date('Y-m-d 23:59:59', strtotime($lastMonth)),
                    'royalty_fee_log_input_administrator_id' => 0,
                ]);

                if ($db->affectedRows() <= 0) {
                    throw new RoyaltyException('Gagal tambah riwayat royalty', 1);
                }
            }

            // Init royalty log monthly for current month
            $check = $db->table('report_royalty_fee_log_monthly')
                ->select('royalty_fee_log_monthly_id')
                ->where("MONTH(royalty_fee_log_monthly_year_month) = {$month} AND YEAR(royalty_fee_log_monthly_year_month) = {$year}")
                ->get()
                ->getRow();

            if (!$check) {
                $currentMonthMin = $this->resolveMinimumRoyalty($this->date, $customMinRoyalty);
                $balance         = $db->table('report_royalty_fee_log_monthly')
                    ->select('royalty_fee_log_monthly_balance')
                    ->orderBy('royalty_fee_log_monthly_year_month', 'desc')
                    ->get()
                    ->getRow('royalty_fee_log_monthly_balance') ?? 0;

                $dataInsert = [
                    'royalty_fee_log_monthly_balance'    => $balance - $currentMonthMin,
                    'royalty_fee_log_monthly_year_month' => $this->date,
                    'royalty_fee_log_monthly_value_out'  => 0,
                    'royalty_fee_log_monthly_min'        => $currentMonthMin,
                    'royalty_fee_log_monthly_bill'       => $currentMonthMin,
                ];

                $dataInsert['royalty_fee_log_monthly_paid']   = $balance > 0 ? ($dataInsert['royalty_fee_log_monthly_balance'] >= 0 ? $dataInsert['royalty_fee_log_monthly_bill'] : $balance) : 0;
                $dataInsert['royalty_fee_log_monthly_unpaid'] = $dataInsert['royalty_fee_log_monthly_bill'] - $dataInsert['royalty_fee_log_monthly_paid'];
                $dataInsert['royalty_fee_log_monthly_status'] = $dataInsert['royalty_fee_log_monthly_paid'] >= $dataInsert['royalty_fee_log_monthly_bill'] ? 'paid' : 'unpaid';

                $db->table('report_royalty_fee_log_monthly')->insert($dataInsert);

                if ($db->affectedRows() <= 0) {
                    throw new RoyaltyException('Gagal tambah royalty monthly', 1);
                }
            }

            $db->transCommit();

            $this->triggerEvent('royalty_adjusted', ['date' => $this->date, 'min_royalty' => $min]);

            return 'Penyesuaian royalty IT berhasil.';
        } catch (Throwable $th) {
            $db->transRollback();
            if ($th instanceof RoyaltyException) {
                throw $th;
            }
            throw new RoyaltyException($th->getMessage(), (int) $th->getCode(), $th);
        }
    }

    /**
     * Snake_case alias for processAdjustment
     */
    public function royalty_adjustment(?BaseConnection $db = null, ?int $customMinRoyalty = null): string
    {
        return $this->processAdjustment($db, $customMinRoyalty);
    }

    /**
     * Trigger CodeIgniter Event safely if available
     */
    private function triggerEvent(string $eventName, array $data): void
    {
        if (class_exists(Events::class)) {
            Events::trigger($eventName, $data);
        }
    }
}
