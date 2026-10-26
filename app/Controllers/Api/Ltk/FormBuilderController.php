<?php

namespace App\Controllers\Api\Ltk;

use App\Controllers\Api\BaseApiController;
use App\Models\Ltk\FormFieldModel;
use App\Models\Ltk\FormTemplateModel;

/**
 * FormBuilderController — CRUD template form dinamis untuk TU/Admin.
 */
class FormBuilderController extends BaseApiController
{
    // GET /api/v1/ltk/form-template
    public function index()
    {
        $this->requirePermission('ltk.admin');
        return $this->success(['items' => (new FormTemplateModel())->getAktif()]);
    }

    // GET /api/v1/ltk/form-template/aktif  (Klien — hanya ambil yang aktif untuk pilihan)
    public function aktif()
    {
        $items = (new FormTemplateModel())
            ->select('id, nama, deskripsi')
            ->where('is_aktif', 1)
            ->orderBy('nama')
            ->findAll();
        return $this->success(['items' => $items]);
    }

    // GET /api/v1/ltk/form-template/:id
    public function show(int $id)
    {
        $this->requirePermission('ltk.view');

        $template = (new FormTemplateModel())->find($id);
        if (!$template) return $this->fail('Template tidak ditemukan.', 404);

        $template['fields'] = (new FormFieldModel())->getByTemplate($id);
        return $this->success($template);
    }

    // POST /api/v1/ltk/form-template
    public function create()
    {
        $this->requirePermission('ltk.admin');

        $data  = $this->request->getJSON(true) ?? [];
        $rules = ['nama' => 'required|max_length[200]'];
        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $id = (new FormTemplateModel())->insert([
            'nama'       => $data['nama'],
            'deskripsi'  => $data['deskripsi'] ?? null,
            'is_aktif'   => 1,
            'created_by' => $this->userId(),
        ]);

        $this->auditLog("Buat form template: {$data['nama']}", 'ltk_form_template', (int)(new FormTemplateModel())->insertID());
        return $this->success(['id' => (int)(new FormTemplateModel())->insertID()], 'Template berhasil dibuat.', 201);
    }

    // PUT /api/v1/ltk/form-template/:id
    public function update(int $id)
    {
        $this->requirePermission('ltk.admin');

        $model    = new FormTemplateModel();
        $template = $model->find($id);
        if (!$template) return $this->fail('Template tidak ditemukan.', 404);

        $data = $this->request->getJSON(true) ?? [];
        $allowed = array_intersect_key($data, array_flip(['nama', 'deskripsi', 'is_aktif']));
        if (empty($allowed)) return $this->fail('Tidak ada data untuk diperbarui.', 422);

        $model->update($id, $allowed);
        $this->auditLog("Update form template #{$id}", 'ltk_form_template', $id);
        return $this->success(null, 'Template berhasil diperbarui.');
    }

    // DELETE /api/v1/ltk/form-template/:id
    public function delete(int $id)
    {
        $this->requirePermission('ltk.admin');

        $model    = new FormTemplateModel();
        $template = $model->find($id);
        if (!$template) return $this->fail('Template tidak ditemukan.', 404);

        $model->update($id, ['is_aktif' => 0]);
        return $this->success(null, 'Template dinonaktifkan.');
    }

    // ──────────────────────────────────────────────────────────────
    // Field Management
    // ──────────────────────────────────────────────────────────────

    // GET /api/v1/ltk/form-template/:id/fields
    public function fields(int $templateId)
    {
        $this->requirePermission('ltk.view');

        $template = (new FormTemplateModel())->find($templateId);
        if (!$template) return $this->fail('Template tidak ditemukan.', 404);

        return $this->success(['items' => (new FormFieldModel())->getByTemplate($templateId)]);
    }

    // POST /api/v1/ltk/form-template/:id/fields
    public function addField(int $templateId)
    {
        $this->requirePermission('ltk.admin');

        $template = (new FormTemplateModel())->find($templateId);
        if (!$template) return $this->fail('Template tidak ditemukan.', 404);

        $data  = $this->request->getJSON(true) ?? [];
        $rules = [
            'label'      => 'required|max_length[255]',
            'field_name' => 'required|max_length[100]|alpha_dash',
            'tipe'       => 'required|in_list[' . implode(',', FormFieldModel::TIPE) . ']',
            'is_required'=> 'permit_empty|in_list[0,1]',
            'urutan'     => 'permit_empty|integer',
        ];

        if (!$this->validate($rules, $data)) {
            return $this->fail(implode(' ', $this->validator->getErrors()), 422);
        }

        $fieldModel = new FormFieldModel();
        $fieldModel->insert([
            'form_template_id' => $templateId,
            'label'            => $data['label'],
            'field_name'       => $data['field_name'],
            'tipe'             => $data['tipe'],
            'placeholder'      => $data['placeholder'] ?? null,
            'opsi_pilihan'     => isset($data['opsi_pilihan']) ? json_encode($data['opsi_pilihan']) : null,
            'is_required'      => (int)($data['is_required'] ?? 0),
            'urutan'           => (int)($data['urutan'] ?? 0),
            'is_aktif'         => 1,
        ]);

        return $this->success(['id' => (int)$fieldModel->insertID()], 'Field berhasil ditambahkan.', 201);
    }

    // PUT /api/v1/ltk/form-template/:id/fields/:fieldId
    public function updateField(int $templateId, int $fieldId)
    {
        $this->requirePermission('ltk.admin');

        $fieldModel = new FormFieldModel();
        $field      = $fieldModel->where('id', $fieldId)->where('form_template_id', $templateId)->first();
        if (!$field) return $this->fail('Field tidak ditemukan.', 404);

        $data    = $this->request->getJSON(true) ?? [];
        $allowed = array_intersect_key($data, array_flip([
            'label', 'field_name', 'tipe', 'placeholder',
            'opsi_pilihan', 'is_required', 'urutan', 'is_aktif',
        ]));

        if (isset($allowed['tipe']) && !in_array($allowed['tipe'], FormFieldModel::TIPE, true)) {
            return $this->fail('Tipe field tidak valid.', 422);
        }
        if (isset($allowed['opsi_pilihan']) && is_array($allowed['opsi_pilihan'])) {
            $allowed['opsi_pilihan'] = json_encode($allowed['opsi_pilihan']);
        }

        $fieldModel->update($fieldId, $allowed);
        return $this->success(null, 'Field berhasil diperbarui.');
    }

    // DELETE /api/v1/ltk/form-template/:id/fields/:fieldId
    public function deleteField(int $templateId, int $fieldId)
    {
        $this->requirePermission('ltk.admin');

        $fieldModel = new FormFieldModel();
        $field      = $fieldModel->where('id', $fieldId)->where('form_template_id', $templateId)->first();
        if (!$field) return $this->fail('Field tidak ditemukan.', 404);

        $fieldModel->update($fieldId, ['is_aktif' => 0]);
        return $this->success(null, 'Field dinonaktifkan.');
    }

    // PUT /api/v1/ltk/form-template/:id/fields/reorder
    // Body: { "urutan": [ { "id": 1, "urutan": 0 }, ... ] }
    public function reorderFields(int $templateId)
    {
        $this->requirePermission('ltk.admin');

        $data  = $this->request->getJSON(true) ?? [];
        $items = $data['urutan'] ?? [];
        if (empty($items) || !is_array($items)) {
            return $this->fail('Data urutan tidak valid.', 422);
        }

        (new FormFieldModel())->updateUrutan($items);
        return $this->success(null, 'Urutan field berhasil disimpan.');
    }
}
