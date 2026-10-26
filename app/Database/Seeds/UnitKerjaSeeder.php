<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UnitKerjaSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['kode' => 'KD1',   'nama' => 'Kegiatan Direktorat 1',    'urutan' => 1],
            ['kode' => 'KD2',   'nama' => 'Kegiatan Direktorat 2',    'urutan' => 2],
            ['kode' => 'KP',    'nama' => 'Kegiatan Penunjang',        'urutan' => 3],
            ['kode' => 'SDMKS', 'nama' => 'SDM dan Kegiatan Strategis','urutan' => 4],
        ];

        foreach ($data as $row) {
            $row['created_at'] = date('Y-m-d H:i:s');
            $row['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('unit_kerja')->ignore(true)->insert($row);
        }
    }
}
