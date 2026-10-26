<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class FormTemplateModel extends Model
{
    protected $table         = 'ltk_form_template';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['nama', 'deskripsi', 'is_aktif', 'created_by'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Ambil template aktif beserta jumlah field-nya. */
    public function getAktif(): array
    {
        return $this->select('ltk_form_template.*, COUNT(ltk_form_field.id) AS jumlah_field')
                    ->join('ltk_form_field', 'ltk_form_field.form_template_id = ltk_form_template.id AND ltk_form_field.is_aktif = 1', 'left')
                    ->where('ltk_form_template.is_aktif', 1)
                    ->groupBy('ltk_form_template.id')
                    ->orderBy('ltk_form_template.nama')
                    ->findAll();
    }
}
