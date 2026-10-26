<?php

namespace App\Libraries\Ltk;

use App\Models\Ltk\HariLiburModel;

/**
 * SlaCalculator — Kalkulasi hari kerja untuk SLA Layanan Telaah Konsep.
 *
 * Hari kerja = Senin–Jumat, bukan hari libur nasional dari tabel ltk_hari_libur.
 * SLA dimulai saat PIC menerima penugasan (tgl_terima_pic).
 */
class SlaCalculator
{
    private array $hariLibur = [];

    public function __construct()
    {
        $this->hariLibur = (new HariLiburModel())->getTanggalLibur();
    }

    /**
     * Apakah $tanggal adalah hari kerja?
     */
    public function isHariKerja(\DateTime $tanggal): bool
    {
        $dayOfWeek = (int)$tanggal->format('N'); // 1=Senin, 7=Minggu
        if ($dayOfWeek >= 6) {
            return false; // Sabtu atau Minggu
        }
        return !in_array($tanggal->format('Y-m-d'), $this->hariLibur, true);
    }

    /**
     * Tambah N hari kerja ke $start; kembalikan DateTime deadline.
     */
    public function addWorkingDays(\DateTime $start, int $days): \DateTime
    {
        $current = clone $start;
        $added   = 0;

        while ($added < $days) {
            $current->modify('+1 day');
            if ($this->isHariKerja($current)) {
                $added++;
            }
        }

        return $current;
    }

    /**
     * Hitung berapa hari kerja antara $start dan $end (inklusif $end, eksklusif $start).
     */
    public function countWorkingDays(\DateTime $start, \DateTime $end): int
    {
        $current = clone $start;
        $current->modify('+1 day');
        $count = 0;

        while ($current <= $end) {
            if ($this->isHariKerja($current)) {
                $count++;
            }
            $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * Hitung sisa hari kerja dari sekarang sampai $deadline.
     * Negatif jika sudah melewati deadline.
     */
    public function getRemainingWorkingDays(\DateTime $deadline): int
    {
        $today = new \DateTime('today');
        if ($today > $deadline) {
            return -$this->countWorkingDays($deadline, $today);
        }
        return $this->countWorkingDays($today, $deadline);
    }

    /**
     * Hitung deadline dan kembalikan dalam format 'Y-m-d'.
     *
     * @param string $tglMulai Format 'Y-m-d H:i:s' atau 'Y-m-d'
     * @param int    $slaHk    Jumlah hari kerja
     */
    public function hitungDeadline(string $tglMulai, int $slaHk): string
    {
        $start    = new \DateTime($tglMulai);
        $deadline = $this->addWorkingDays($start, $slaHk);
        return $deadline->format('Y-m-d');
    }

    /**
     * Tentukan label status SLA berdasarkan deadline dan apakah sudah breach.
     *
     * @return array{label: string, level: string, sisa_hk: int}
     */
    public function getStatusSla(?string $tglDeadline, bool $isBreach): array
    {
        if (!$tglDeadline) {
            return ['label' => 'Belum Ada SLA', 'level' => 'secondary', 'sisa_hk' => 0];
        }

        if ($isBreach) {
            return ['label' => 'SLA Breach / Terlambat', 'level' => 'danger', 'sisa_hk' => 0];
        }

        $deadline = new \DateTime($tglDeadline);
        $sisa     = $this->getRemainingWorkingDays($deadline);

        if ($sisa < 0) {
            return ['label' => 'Terlambat (' . abs($sisa) . ' HK)', 'level' => 'danger', 'sisa_hk' => $sisa];
        }
        if ($sisa === 0) {
            return ['label' => 'Hari Terakhir!', 'level' => 'warning', 'sisa_hk' => 0];
        }
        if ($sisa <= 3) {
            return ['label' => "Mendekati Deadline ({$sisa} HK)", 'level' => 'warning', 'sisa_hk' => $sisa];
        }

        return ['label' => "On Track ({$sisa} HK)", 'level' => 'success', 'sisa_hk' => $sisa];
    }
}
