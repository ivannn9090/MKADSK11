<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class NotaDinasTemplateModel extends Model
{
    protected $table         = 'ltk_nota_dinas_template';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $allowedFields = ['tipe', 'nama', 'template_path', 'variabel', 'versi', 'is_aktif', 'created_by'];

    /** Ambil template aktif untuk tipe tertentu. */
    public function getAktifByTipe(string $tipe): ?array
    {
        return $this->where('tipe', $tipe)->where('is_aktif', 1)->orderBy('versi', 'DESC')->first();
    }

    /** Nonaktifkan semua template lama untuk tipe yang sama sebelum aktifkan yang baru. */
    public function nonaktifkanByTipe(string $tipe, int $exceptId = 0): void
    {
        $q = $this->where('tipe', $tipe);
        if ($exceptId) $q->where('id !=', $exceptId);
        $q->set(['is_aktif' => 0])->update();
    }
}
