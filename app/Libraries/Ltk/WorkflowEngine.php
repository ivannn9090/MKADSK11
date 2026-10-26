<?php

namespace App\Libraries\Ltk;

use App\Models\Ltk\AktivitasLogModel;
use App\Models\Ltk\LtkNotifikasiModel;
use App\Models\Ltk\PermohonanModel;
use App\Models\Ltk\SlaConfigModel;

/**
 * WorkflowEngine — State machine untuk status workflow Layanan Telaah Konsep.
 *
 * Memastikan hanya transisi status yang diizinkan yang dapat dilakukan,
 * dan secara otomatis mencatat log aktivitas + mengirim notifikasi.
 */
class WorkflowEngine
{
    /**
     * Peta transisi: status_dari => [status_ke yang diizinkan]
     */
    private array $transitions = [
        'draft'                              => ['menunggu_srikandi', 'masuk_antrian'],  // masuk_antrian untuk TU input langsung
        'menunggu_srikandi'                  => ['menunggu_verifikasi_tu'],
        'menunggu_verifikasi_tu'             => ['terverifikasi'],
        'terverifikasi'                      => ['masuk_antrian'],
        'masuk_antrian'                      => ['ditugaskan_pic'],
        'ditugaskan_pic'                     => ['diterima_pic'],
        'diterima_pic'                       => ['asesmen_awal'],
        'asesmen_awal'                       => ['tidak_perlu_tindak_lanjut', 'perlu_tindak_lanjut'],
        'tidak_perlu_tindak_lanjut'          => ['penyusunan_policy_brief'],
        'penyusunan_policy_brief'            => ['penyusunan_nota_dinas_tanggapan', 'nota_dinas_permintaan_penyesuaian'],
        'penyusunan_nota_dinas_tanggapan'    => ['selesai'],
        'perlu_tindak_lanjut'                => ['penyusunan_policy_brief'],
        'nota_dinas_permintaan_penyesuaian'  => ['kajian_lanjutan'],
        'kajian_lanjutan'                    => ['penyusunan_policy_paper'],
        'penyusunan_policy_paper'            => ['nota_dinas_hasil_telaah_akhir'],
        'nota_dinas_hasil_telaah_akhir'      => ['selesai'],
        'selesai'                            => ['ditutup'],
    ];

    /**
     * Label ramah untuk setiap status — digunakan di timeline Klien.
     */
    private array $labels = [
        'draft'                              => 'Formulir Dibuat',
        'menunggu_srikandi'                  => 'Nota Dinas Digenerate — Menunggu Pengiriman SRIKANDI',
        'menunggu_verifikasi_tu'             => 'Menunggu Verifikasi TU',
        'terverifikasi'                      => 'Diverifikasi TU',
        'masuk_antrian'                      => 'Masuk Antrian',
        'ditugaskan_pic'                     => 'Ditugaskan ke PIC',
        'diterima_pic'                       => 'PIC Menerima Penugasan — SLA Dimulai',
        'asesmen_awal'                       => 'Asesmen Awal Dilakukan',
        'tidak_perlu_tindak_lanjut'          => 'Tidak Memerlukan Tindak Lanjut',
        'penyusunan_policy_brief'            => 'Policy Brief Sedang Disusun',
        'penyusunan_nota_dinas_tanggapan'    => 'Nota Dinas Tanggapan Diterbitkan',
        'perlu_tindak_lanjut'                => 'Memerlukan Tindak Lanjut',
        'nota_dinas_permintaan_penyesuaian'  => 'Nota Dinas Permintaan Penyesuaian Diterbitkan',
        'kajian_lanjutan'                    => 'Kajian Lanjutan Dimulai',
        'penyusunan_policy_paper'            => 'Policy Paper Sedang Disusun',
        'nota_dinas_hasil_telaah_akhir'      => 'Nota Dinas Hasil Telaah Akhir Diterbitkan',
        'selesai'                            => 'Proses Selesai',
        'ditutup'                            => 'Permohonan Ditutup',
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, $this->transitions[$from] ?? [], true);
    }

    /**
     * Lakukan transisi status permohonan.
     *
     * @throws \RuntimeException jika transisi tidak diizinkan
     */
    public function transition(
        int     $permohonanId,
        string  $toStatus,
        int     $userId,
        ?string $catatan  = null,
        array   $extraUpdate = []
    ): bool {
        $model       = new PermohonanModel();
        $permohonan  = $model->find($permohonanId);

        if (!$permohonan) {
            throw new \RuntimeException("Permohonan #{$permohonanId} tidak ditemukan.");
        }

        $fromStatus = $permohonan['status'];

        if (!$this->canTransition($fromStatus, $toStatus)) {
            throw new \RuntimeException(
                "Transisi tidak diizinkan: {$fromStatus} → {$toStatus}."
            );
        }

        // Update status + field tambahan
        $updateData = array_merge(['status' => $toStatus, 'updated_by' => $userId], $extraUpdate);
        $model->update($permohonanId, $updateData);

        // Catat log timeline
        $label = $catatan
            ? ($this->labels[$toStatus] ?? $toStatus) . ' — ' . $catatan
            : ($this->labels[$toStatus] ?? $toStatus);

        (new AktivitasLogModel())->catat(
            $permohonanId,
            $toStatus,
            $this->labels[$toStatus] ?? $toStatus,
            $userId,
            $catatan,
            $fromStatus
        );

        return true;
    }

    /**
     * Kirim notifikasi ke penerima yang relevan setelah transisi.
     *
     * @param int[]  $recipientIds
     */
    public function sendNotif(int $permohonanId, string $status, array $recipientIds, ?string $extraMsg = null): void
    {
        if (empty($recipientIds)) return;

        $label  = $this->labels[$status] ?? $status;
        $judul  = "Update Permohonan #LTK — {$label}";
        $pesan  = $extraMsg ?? "Status permohonan Anda telah diperbarui menjadi: {$label}.";

        (new LtkNotifikasiModel())->kirimBulk($recipientIds, $status, $judul, $pesan, $permohonanId);
    }

    public function getLabel(string $status): string
    {
        return $this->labels[$status] ?? $status;
    }

    public function getAllowedTransitions(string $fromStatus): array
    {
        return $this->transitions[$fromStatus] ?? [];
    }
}
