<?php

namespace App\Models\Ltk;

use CodeIgniter\Model;

class JawabanFieldModel extends Model
{
    protected $table         = 'ltk_jawaban_field';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['permohonan_id', 'form_field_id', 'nilai_text', 'nilai_link'];
    protected $useTimestamps = false;

    /** Ambil semua jawaban untuk satu permohonan, join dengan definisi field. */
    public function getByPermohonan(int $permohonanId): array
    {
        return $this->select('ltk_jawaban_field.*, ltk_form_field.label, ltk_form_field.tipe, ltk_form_field.field_name, ltk_form_field.urutan')
                    ->join('ltk_form_field', 'ltk_form_field.id = ltk_jawaban_field.form_field_id')
                    ->where('ltk_jawaban_field.permohonan_id', $permohonanId)
                    ->orderBy('ltk_form_field.urutan', 'ASC')
                    ->findAll();
    }

    /**
     * Simpan bulk jawaban untuk satu permohonan.
     *
     * $jawaban berformat: [ field_id => nilai_atau_array ]
     * Untuk tipe link_cloud, nilai berupa URL string.
     * Untuk checkbox, nilai berupa array yang dijoin dengan koma.
     *
     * @param array $fields  Definisi field dari FormFieldModel::getByTemplate()
     */
    public function insertBulk(int $permohonanId, array $jawaban, array $fields = []): void
    {
        // Buat peta tipe berdasarkan id field
        $tipeMap = [];
        foreach ($fields as $f) {
            $tipeMap[(int)$f['id']] = $f['tipe'];
        }

        $rows = [];
        foreach ($jawaban as $fieldId => $nilai) {
            $tipe = $tipeMap[(int)$fieldId] ?? 'text';
            $row  = [
                'permohonan_id' => $permohonanId,
                'form_field_id' => (int)$fieldId,
                'nilai_text'    => null,
                'nilai_link'    => null,
                'created_at'    => date('Y-m-d H:i:s'),
            ];

            if ($tipe === 'link_cloud') {
                $row['nilai_link'] = is_string($nilai) ? trim($nilai) : null;
            } elseif (is_array($nilai)) {
                $row['nilai_text'] = implode(',', $nilai); // checkbox
            } else {
                $row['nilai_text'] = $nilai;
            }

            $rows[] = $row;
        }

        if (!empty($rows)) {
            $this->insertBatch($rows);
        }
    }
}
