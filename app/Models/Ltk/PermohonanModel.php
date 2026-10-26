<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class PermohonanModel extends Model
{
    protected $table         = 'ltk_permohonan';
    protected $primaryKey    = 'id';
    protected $useSoftDeletes = true;
    protected $deletedField   = 'deleted_at';
    protected $useTimestamps  = true;
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = [
        'kode', 'sumber_permohonan', 'klien_user_id',
        'unit_kerja', 'nama_pengusul', 'nodin_srikandi',
        'judul', 'deskripsi', 'form_template_id',
        'status', 'skenario', 'jenis_antrian',
        'nomor_nota_dinas_masuk', 'tgl_nota_dinas_masuk',
        'pic_user_id', 'tgl_penugasan', 'tgl_terima_pic',
        'sla_hari_kerja', 'tgl_deadline', 'tgl_selesai', 'is_sla_breach',
        'catatan_tu', 'created_by', 'updated_by',
    ];

    /** Generate kode unik LTK-YYYY-NNNN. */
    public function generateKode(): string
    {
        $year  = date('Y');
        $count = $this->where("kode LIKE 'LTK-{$year}-%'")->withDeleted()->countAllResults();
        return 'LTK-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /** List permohonan dengan data pengusul (klien atau TU input) dan PIC. */
    public function getListLengkap(array $filter = []): array
    {
        $q = $this->select(
                'ltk_permohonan.*, ' .
                'u_klien.nama AS klien_nama, u_klien.email AS klien_email, ' .
                // Nama pengusul: pakai nama_pengusul jika TU input, else nama klien
                'COALESCE(ltk_permohonan.nama_pengusul, u_klien.nama) AS pengusul_nama, ' .
                'u_pic.nama AS pic_nama'
            )
            ->join('users AS u_klien', 'u_klien.id = ltk_permohonan.klien_user_id', 'left')
            ->join('users AS u_pic',   'u_pic.id = ltk_permohonan.pic_user_id',   'left');

        if (!empty($filter['status'])) {
            $q->where('ltk_permohonan.status', $filter['status']);
        }
        if (!empty($filter['klien_user_id'])) {
            $q->where('ltk_permohonan.klien_user_id', (int)$filter['klien_user_id']);
        }
        if (!empty($filter['pic_user_id'])) {
            $q->where('ltk_permohonan.pic_user_id', (int)$filter['pic_user_id']);
        }
        if (!empty($filter['jenis_antrian'])) {
            $q->where('ltk_permohonan.jenis_antrian', $filter['jenis_antrian']);
        }
        if (!empty($filter['is_sla_breach'])) {
            $q->where('ltk_permohonan.is_sla_breach', 1);
        }
        if (!empty($filter['q'])) {
            $q->groupStart()
              ->like('ltk_permohonan.kode', $filter['q'])
              ->orLike('ltk_permohonan.judul', $filter['q'])
              ->orLike('u_klien.nama', $filter['q'])
              ->groupEnd();
        }

        return $q->orderBy('ltk_permohonan.jenis_antrian', 'DESC') // prioritas duluan
                 ->orderBy('ltk_permohonan.created_at', 'DESC')
                 ->findAll();
    }

    /** Permohonan yang masuk antrian, urut prioritas → created_at. */
    public function getAntrian(): array
    {
        return $this->select('ltk_permohonan.*, u_klien.nama AS klien_nama')
                    ->join('users AS u_klien', 'u_klien.id = ltk_permohonan.klien_user_id', 'left')
                    ->whereIn('ltk_permohonan.status', ['masuk_antrian'])
                    ->orderBy('FIELD(ltk_permohonan.jenis_antrian, "prioritas", "regular")', '', false)
                    ->orderBy('ltk_permohonan.created_at', 'ASC')
                    ->findAll();
    }

    /** Statistik agregat untuk dashboard TU. */
    public function getStatsTU(): array
    {
        $db    = $this->db;
        $total = $this->countAllResults(false);

        $byStatus = $db->query(
            "SELECT status, COUNT(*) AS total FROM ltk_permohonan WHERE deleted_at IS NULL GROUP BY status"
        )->getResultArray();

        $map = [];
        foreach ($byStatus as $row) {
            $map[$row['status']] = (int)$row['total'];
        }

        $mendekati = $db->query(
            "SELECT COUNT(*) AS total FROM ltk_permohonan
             WHERE deleted_at IS NULL AND is_sla_breach = 0 AND tgl_deadline IS NOT NULL
             AND DATEDIFF(tgl_deadline, CURDATE()) BETWEEN 0 AND 3
             AND status NOT IN ('selesai','ditutup')"
        )->getRow()->total ?? 0;

        return [
            'total'               => $total,
            'dalam_antrian'       => $map['masuk_antrian'] ?? 0,
            'sedang_ditelaah'     => array_sum(array_intersect_key($map, array_flip([
                'ditugaskan_pic','diterima_pic','asesmen_awal',
                'tidak_perlu_tindak_lanjut','penyusunan_policy_brief',
                'penyusunan_nota_dinas_tanggapan','perlu_tindak_lanjut',
                'nota_dinas_permintaan_penyesuaian','kajian_lanjutan',
                'penyusunan_policy_paper','nota_dinas_hasil_telaah_akhir',
            ]))),
            'selesai'             => ($map['selesai'] ?? 0) + ($map['ditutup'] ?? 0),
            'mendekati_sla'       => (int)$mendekati,
            'terlambat_sla'       => $this->where('is_sla_breach', 1)->where('deleted_at', null)->countAllResults(false),
        ];
    }

    /** Statistik agregat untuk dashboard PIC. */
    public function getStatsPIC(int $picUserId): array
    {
        $aktif = $this->select('status, jenis_antrian')
                      ->where('pic_user_id', $picUserId)
                      ->whereNotIn('status', ['selesai','ditutup'])
                      ->where('deleted_at', null)
                      ->findAll();

        $regular   = 0;
        $prioritas = 0;
        foreach ($aktif as $r) {
            if ($r['jenis_antrian'] === 'prioritas') $prioritas++;
            else $regular++;
        }

        $mendekati = $this->where('pic_user_id', $picUserId)
                          ->where('is_sla_breach', 0)
                          ->where('tgl_deadline IS NOT NULL', null, false)
                          ->where('DATEDIFF(tgl_deadline, CURDATE()) BETWEEN 0 AND 3', null, false)
                          ->whereNotIn('status', ['selesai','ditutup'])
                          ->where('deleted_at', null)
                          ->countAllResults(false);

        $breach = $this->where('pic_user_id', $picUserId)
                       ->where('is_sla_breach', 1)
                       ->where('deleted_at', null)
                       ->countAllResults(false);

        return [
            'tugas_aktif'   => count($aktif),
            'regular'       => $regular,
            'prioritas'     => $prioritas,
            'mendekati_sla' => $mendekati,
            'terlambat_sla' => $breach,
        ];
    }

    /** Statistik agregat untuk dashboard Klien. */
    public function getStatsKlien(int $klienUserId): array
    {
        $rows = $this->select('status, COUNT(*) AS total')
                     ->where('klien_user_id', $klienUserId)
                     ->where('deleted_at', null)
                     ->groupBy('status')
                     ->findAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['status']] = (int)$r['total'];
        }

        return [
            'total'            => array_sum(array_column($rows, 'total')),
            'menunggu_verifikasi' => $map['menunggu_verifikasi_tu'] ?? 0,
            'dalam_antrian'    => $map['masuk_antrian'] ?? 0,
            'sedang_diproses'  => array_sum(array_intersect_key($map, array_flip([
                'ditugaskan_pic','diterima_pic','asesmen_awal',
                'tidak_perlu_tindak_lanjut','penyusunan_policy_brief',
                'penyusunan_nota_dinas_tanggapan','perlu_tindak_lanjut',
                'nota_dinas_permintaan_penyesuaian','kajian_lanjutan',
                'penyusunan_policy_paper','nota_dinas_hasil_telaah_akhir',
            ]))),
            'selesai'          => ($map['selesai'] ?? 0) + ($map['ditutup'] ?? 0),
        ];
    }
}
