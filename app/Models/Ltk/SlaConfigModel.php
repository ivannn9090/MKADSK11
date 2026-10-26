<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class SlaConfigModel extends Model
{
    protected $table         = 'ltk_sla_config';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['skenario', 'hari_kerja', 'keterangan', 'updated_by', 'updated_at'];

    public function getBySkenario(string $skenario): ?array
    {
        return $this->where('skenario', $skenario)->first();
    }

    public function getHariKerja(string $skenario): int
    {
        $row = $this->getBySkenario($skenario);
        return $row ? (int)$row['hari_kerja'] : ($skenario === 'A' ? 6 : 28);
    }
}
