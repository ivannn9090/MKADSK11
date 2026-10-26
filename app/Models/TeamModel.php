<?php

namespace App\Models;

use CodeIgniter\Model;

class TeamModel extends Model
{
    protected $table         = 'teams';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nama_tim', 'ketua_tim_id'];
    protected $useTimestamps = true;
}
