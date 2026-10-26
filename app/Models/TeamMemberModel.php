<?php

namespace App\Models;

use CodeIgniter\Model;

class TeamMemberModel extends Model
{
    protected $table         = 'team_members';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['team_id', 'user_id'];
    protected $useTimestamps = false;

    public function getByTeam(int $teamId): array
    {
        return $this->select('team_members.*, users.nama, users.jabatan, users.role, users.email')
                    ->join('users', 'users.id = team_members.user_id')
                    ->where('team_id', $teamId)
                    ->findAll();
    }
}
