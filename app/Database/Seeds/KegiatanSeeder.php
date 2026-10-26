<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class KegiatanSeeder extends Seeder
{
    public function run(): void
    {
        $direktur = $this->db->table('users')->where('role', 'direktur')->get()->getFirstRow('array');
        $ketua    = $this->db->table('users')->where('role', 'ketua_tim')->get()->getFirstRow('array');
        $anggota1 = $this->db->table('users')->where('role', 'anggota_tim')->get()->getFirstRow('array');
        $anggota2 = $this->db->table('users')->where('role', 'anggota_tim')->orderBy('id', 'DESC')->get()->getFirstRow('array');
        $team     = $this->db->table('teams')->get()->getFirstRow('array');

        if (! $direktur || ! $team) return;

        // Kegiatan 1
        $this->db->table('kegiatan')->insert([
            'nama_kegiatan'   => 'Konsultasi Publik Rancangan Kebijakan',
            'deskripsi'       => 'Kegiatan konsultasi publik untuk mendapatkan masukan dari pemangku kepentingan terkait rancangan kebijakan strategis.',
            'tanggal_mulai'   => '2026-09-01',
            'tanggal_selesai' => '2026-09-30',
            'pagu_anggaran'   => 50000000,
            'status'          => 'berjalan',
            'team_id'         => $team['id'],
            'created_by'      => $direktur['id'],
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $k1 = $this->db->insertID();

        // Tasks for Kegiatan 1
        $tasks1 = [
            ['nama_task' => 'Penentuan Pakar yang Diundang',      'pic_id' => $ketua['id'],    'status' => 'selesai',        'target_date' => '2026-09-05', 'urutan' => 1],
            ['nama_task' => 'Penentuan Stakeholder yang Diundang', 'pic_id' => $anggota1['id'], 'status' => 'selesai',        'target_date' => '2026-09-06', 'urutan' => 2],
            ['nama_task' => 'Penentuan Tanggal',                  'pic_id' => $ketua['id'],    'status' => 'selesai',        'target_date' => '2026-09-07', 'urutan' => 3],
            ['nama_task' => 'Booking Tempat',                     'pic_id' => $anggota1['id'], 'status' => 'selesai',        'target_date' => '2026-09-08', 'urutan' => 4],
            ['nama_task' => 'Penunjukan Penanggung Jawab',         'pic_id' => $ketua['id'],    'status' => 'selesai',        'target_date' => '2026-09-09', 'urutan' => 5],
            ['nama_task' => 'Penyusunan TOR',                     'pic_id' => $anggota2['id'], 'status' => 'selesai',        'target_date' => '2026-09-12', 'urutan' => 6],
            ['nama_task' => 'Penyusunan Undangan',                'pic_id' => $anggota1['id'], 'status' => 'sedang_berjalan','target_date' => '2026-09-14', 'urutan' => 7],
            ['nama_task' => 'Pelaksanaan Kegiatan',               'pic_id' => $ketua['id'],    'status' => 'belum_dimulai',  'target_date' => '2026-09-20', 'urutan' => 8],
            ['nama_task' => 'Penyusunan Laporan',                 'pic_id' => $anggota2['id'], 'status' => 'belum_dimulai',  'target_date' => '2026-09-30', 'urutan' => 9],
        ];
        foreach ($tasks1 as $t) {
            $this->db->table('tasks')->insert(array_merge($t, [
                'kegiatan_id' => $k1,
                'completed_date' => $t['status'] === 'selesai' ? date('Y-m-d') : null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]));
        }

        // Realisasi Anggaran for Kegiatan 1
        $realisasi = [
            ['uraian' => 'Sewa Tempat Pelaksanaan', 'nominal' => 10000000, 'tanggal' => '2026-09-10'],
            ['uraian' => 'Konsumsi Peserta',         'nominal' => 5000000,  'tanggal' => '2026-09-10'],
            ['uraian' => 'Honorarium Narasumber',    'nominal' => 8000000,  'tanggal' => '2026-09-11'],
        ];
        foreach ($realisasi as $r) {
            $this->db->table('realisasi_anggaran')->insert(array_merge($r, [
                'kegiatan_id' => $k1,
                'created_by'  => $ketua['id'],
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]));
        }

        // Kegiatan 2
        $team2 = $this->db->table('teams')->orderBy('id', 'DESC')->get()->getFirstRow('array');
        $this->db->table('kegiatan')->insert([
            'nama_kegiatan'   => 'Workshop Pengembangan SDM',
            'deskripsi'       => 'Workshop peningkatan kapasitas dan kompetensi sumber daya manusia di lingkungan instansi.',
            'tanggal_mulai'   => '2026-10-01',
            'tanggal_selesai' => '2026-10-31',
            'pagu_anggaran'   => 35000000,
            'status'          => 'draft',
            'team_id'         => $team2['id'],
            'created_by'      => $direktur['id'],
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $k2 = $this->db->insertID();

        $tasks2 = [
            ['nama_task' => 'Persiapan Materi Workshop', 'pic_id' => $ketua['id'],    'status' => 'belum_dimulai', 'target_date' => '2026-10-05', 'urutan' => 1],
            ['nama_task' => 'Undangan Peserta',           'pic_id' => $anggota1['id'], 'status' => 'belum_dimulai', 'target_date' => '2026-10-07', 'urutan' => 2],
            ['nama_task' => 'Pelaksanaan Workshop',       'pic_id' => $ketua['id'],    'status' => 'belum_dimulai', 'target_date' => '2026-10-15', 'urutan' => 3],
            ['nama_task' => 'Evaluasi dan Pelaporan',     'pic_id' => $anggota2['id'], 'status' => 'belum_dimulai', 'target_date' => '2026-10-25', 'urutan' => 4],
        ];
        foreach ($tasks2 as $t) {
            $this->db->table('tasks')->insert(array_merge($t, [
                'kegiatan_id' => $k2,
                'completed_date' => null,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]));
        }
    }
}
