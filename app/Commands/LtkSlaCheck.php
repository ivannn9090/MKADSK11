<?php

namespace App\Commands;

use App\Models\Ltk\AktivitasLogModel;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * LtkSlaCheck — Cron harian: periksa permohonan yang melewati/mendekati deadline SLA.
 *
 * Jadwalkan di crontab:
 *   0 8 * * 1-5 php /path/to/spark ltk:sla-check >> /dev/null 2>&1
 *   (Setiap hari kerja pukul 08:00)
 */
class LtkSlaCheck extends BaseCommand
{
    protected $group       = 'ltk';
    protected $name        = 'ltk:sla-check';
    protected $description = 'Periksa SLA permohonan telaah: tandai breach dan kirim notifikasi.';

    public function run(array $params): void
    {
        CLI::write('[LTK SLA Check] Dimulai: ' . date('Y-m-d H:i:s'), 'cyan');

        $db             = \Config\Database::connect();
        $permohonanModel= new PermohonanModel();
        $logModel       = new AktivitasLogModel();
        $notifModel     = new LtkNotifikasiModel();

        $breachCount    = 0;
        $warningCount   = 0;

        // ── 1. Tandai permohonan yang sudah melewati deadline ──────────────
        $statusAktif = [
            'diterima_pic', 'asesmen_awal',
            'tidak_perlu_tindak_lanjut', 'penyusunan_policy_brief',
            'penyusunan_nota_dinas_tanggapan', 'perlu_tindak_lanjut',
            'nota_dinas_permintaan_penyesuaian', 'kajian_lanjutan',
            'penyusunan_policy_paper', 'nota_dinas_hasil_telaah_akhir',
        ];

        $lewatDeadline = $db->query("
            SELECT id, kode, judul, pic_user_id, klien_user_id, tgl_deadline
            FROM ltk_permohonan
            WHERE deleted_at IS NULL
              AND is_sla_breach = 0
              AND tgl_deadline IS NOT NULL
              AND tgl_deadline < CURDATE()
              AND status IN ('" . implode("','", $statusAktif) . "')
        ")->getResultArray();

        foreach ($lewatDeadline as $p) {
            $permohonanModel->update((int)$p['id'], ['is_sla_breach' => 1]);

            $logModel->catat(
                (int)$p['id'],
                $p['status'] ?? 'breach',
                'SLA Breach — Melewati Deadline ' . $p['tgl_deadline'],
                null,
                'Otomatis ditandai oleh sistem cron ltk:sla-check'
            );

            // Kumpulkan penerima notifikasi (PIC + semua TU/Admin)
            $penerima = $this->_getPenerimaSlaAlert((int)$p['pic_user_id']);
            $notifModel->kirimBulk(
                $penerima,
                'sla_breach',
                'SLA Breach — ' . $p['kode'],
                "Permohonan {$p['kode']}: \"{$p['judul']}\" telah melewati deadline SLA ({$p['tgl_deadline']}).",
                (int)$p['id']
            );

            $breachCount++;
            CLI::write("  [BREACH] #{$p['id']} {$p['kode']} deadline: {$p['tgl_deadline']}", 'red');
        }

        // ── 2. Kirim peringatan untuk yang mendekati deadline (H-3, H-1) ──
        foreach ([3, 1] as $hariSisa) {
            $mendekati = $db->query("
                SELECT id, kode, judul, pic_user_id, klien_user_id, tgl_deadline
                FROM ltk_permohonan
                WHERE deleted_at IS NULL
                  AND is_sla_breach = 0
                  AND tgl_deadline IS NOT NULL
                  AND DATEDIFF(tgl_deadline, CURDATE()) = {$hariSisa}
                  AND status IN ('" . implode("','", $statusAktif) . "')
            ")->getResultArray();

            foreach ($mendekati as $p) {
                $penerima = $this->_getPenerimaSlaAlert((int)$p['pic_user_id']);
                $notifModel->kirimBulk(
                    $penerima,
                    'sla_warning',
                    "Peringatan SLA H-{$hariSisa} — " . $p['kode'],
                    "Permohonan {$p['kode']}: \"{$p['judul']}\" akan melewati deadline SLA dalam {$hariSisa} hari kerja ({$p['tgl_deadline']}).",
                    (int)$p['id']
                );
                $warningCount++;
                CLI::write("  [WARN H-{$hariSisa}] #{$p['id']} {$p['kode']} deadline: {$p['tgl_deadline']}", 'yellow');
            }
        }

        CLI::write(
            "[LTK SLA Check] Selesai. Breach baru: {$breachCount}, Peringatan: {$warningCount}",
            'green'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Ambil user_id penerima notifikasi SLA: PIC + semua TU + semua Admin
    // ──────────────────────────────────────────────────────────────
    private function _getPenerimaSlaAlert(?int $picUserId): array
    {
        $ids = [];

        if ($picUserId) {
            $ids[] = $picUserId;
        }

        $tuAdmin = (new UserModel())
            ->select('id')
            ->whereIn('role', ['tu', 'admin'])
            ->where('status', 'aktif')
            ->findAll();

        foreach ($tuAdmin as $u) {
            $ids[] = (int)$u['id'];
        }

        return array_unique($ids);
    }
}
