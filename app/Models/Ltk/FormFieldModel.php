<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class FormFieldModel extends Model
{
    protected $table         = 'ltk_form_field';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'form_template_id', 'label', 'field_name', 'tipe',
        'placeholder', 'opsi_pilihan', 'is_required', 'urutan', 'is_aktif',
    ];

    /**
     * Tipe field yang tersedia untuk form builder.
     * Klien tidak upload file — gunakan link_cloud untuk dokumen rujukan.
     */
    public const TIPE = ['text', 'textarea', 'number', 'date', 'dropdown', 'radio', 'checkbox', 'link_cloud'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /** Ambil semua field aktif milik template, urut berdasarkan urutan. */
    public function getByTemplate(int $templateId): array
    {
        return $this->where('form_template_id', $templateId)
                    ->where('is_aktif', 1)
                    ->orderBy('urutan', 'ASC')
                    ->findAll();
    }

    /** Update urutan field secara bulk. $items = [[id => X, urutan => Y], ...] */
    public function updateUrutan(array $items): void
    {
        foreach ($items as $item) {
            $this->update((int)$item['id'], ['urutan' => (int)$item['urutan']]);
        }
    }
}
