<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run(): void
    {
        $this->call('UserSeeder');
        $this->call('TeamSeeder');
        $this->call('KegiatanSeeder');
    }
}
