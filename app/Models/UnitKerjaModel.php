<?php

namespace App\Models;

use CodeIgniter\Model;

class UnitKerjaModel extends Model
{
    protected $table         = 'unit_kerja';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['kode', 'nama', 'urutan'];
    protected $useTimestamps = true;
}
