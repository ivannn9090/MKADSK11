<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        // Get user IDs
        $ketua = $this->db->table('users')->where('role', 'ketua_tim')->get()->getFirstRow('array');
        if (! $ketua) return;

        $this->db->table('teams')->insert([
            'nama_tim'     => 'Tim Konsultasi Publik',
            'ketua_tim_id' => $ketua['id'],
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $teamId = $this->db->insertID();

        // Add all users as team members
        $users = $this->db->table('users')->get()->getResultArray();
        foreach ($users as $user) {
            $this->db->table('team_members')->insert([
                'team_id'    => $teamId,
                'user_id'    => $user['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Second team
        $this->db->table('teams')->insert([
            'nama_tim'     => 'Tim Pengembangan Regulasi',
            'ketua_tim_id' => $ketua['id'],
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $team2Id = $this->db->insertID();

        $users2 = $this->db->table('users')->whereIn('role', ['ketua_tim', 'anggota_tim'])->get()->getResultArray();
        foreach ($users2 as $user) {
            $this->db->table('team_members')->insert([
                'team_id'    => $team2Id,
                'user_id'    => $user['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
