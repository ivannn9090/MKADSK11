<?php

namespace App\Libraries\Ltk;

use App\Models\Ltk\DokumenOutputModel;

/**
 * NotaDinasGenerator — Generate Nota Dinas berdasarkan template Tata Naskah Dinas ANRI.
 *
 * Template disimpan sebagai file .docx di:
 *   writable/templates/ltk/<tipe>.docx
 *
 * Variabel placeholder dalam .docx menggunakan format ${NAMA_VARIABEL}.
 * Daftar variabel per tipe ada di TEMPLATE_VARS di bawah.
 *
 * Dependensi: composer require phpoffice/phpword mpdf/mpdf
 * Jika phpword belum terpasang, generate akan melempar RuntimeException
 * agar controller dapat menangani dengan pesan yang tepat.
 */
class NotaDinasGenerator
{
    /** Nama file template per tipe (relatif terhadap writable/templates/ltk/) */
    private const TEMPLATE_FILES = [
        'nota_dinas_pengajuan'                 => 'nd_pengajuan.docx',
        'nota_dinas_tanggapan'                 => 'nd_tanggapan.docx',
        'nota_dinas_permintaan_penyesuaian'    => 'nd_permintaan_penyesuaian.docx',
        'nota_dinas_hasil_telaah_akhir'        => 'nd_hasil_telaah_akhir.docx',
    ];

    /**
     * Variabel placeholder dalam template .docx menggunakan format ${nama_variabel}.
     *
     * Variabel UMUM (semua tipe):
     *   yth, dari, hal, tanggal, lampiran,
     *   jenis_dokumen, judul_dokumen,
     *   jabatan_pengirim, nama_pengirim
     *
     * Variabel TAMBAHAN per tipe:
     *   nota_dinas_tanggapan              : uraian_tambahan
     *   nota_dinas_permintaan_penyesuaian : uraian_tambahan, batas_waktu
     *   nota_dinas_hasil_telaah_akhir     : uraian_tambahan, rekomendasi
     */
    public const TEMPLATE_VARS = [
        'nota_dinas_pengajuan' => [
            'nomor_naskah', 'yth', 'dari', 'hal', 'tanggal', 'lampiran',
            'jenis_dokumen', 'judul_dokumen', 'uraian_tambahan',
            'jabatan_pengirim', 'nama_pengirim',
        ],
        'nota_dinas_tanggapan' => [
            'yth', 'dari', 'hal', 'tanggal', 'lampiran',
            'jenis_dokumen', 'judul_dokumen', 'uraian_tambahan',
            'jabatan_pengirim', 'nama_pengirim',
        ],
        'nota_dinas_permintaan_penyesuaian' => [
            'yth', 'dari', 'hal', 'tanggal', 'lampiran',
            'jenis_dokumen', 'judul_dokumen', 'uraian_tambahan',
            'jabatan_pengirim', 'nama_pengirim',
            'batas_waktu',
        ],
        'nota_dinas_hasil_telaah_akhir' => [
            'yth', 'dari', 'hal', 'tanggal', 'lampiran',
            'jenis_dokumen', 'judul_dokumen', 'uraian_tambahan',
            'jabatan_pengirim', 'nama_pengirim',
            'rekomendasi',
        ],
    ];

    private string $templateBase;
    private string $outputBase;

    public function __construct()
    {
        $this->templateBase = WRITEPATH . 'templates/ltk/';
        $this->outputBase   = WRITEPATH . 'uploads/ltk/nota_dinas/';

        foreach ([$this->templateBase, $this->outputBase] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    /**
     * Generate Nota Dinas dan simpan hasilnya.
     *
     * @param  int    $permohonanId
     * @param  string $tipe          Salah satu kunci TEMPLATE_FILES
     * @param  array  $variabel      Key-value placeholder → nilai
     * @param  int    $userId        User yang men-trigger generate
     * @param  string|null $nomorNd  Nomor Nota Dinas (opsional, bisa diisi setelah generate)
     * @return array  { docx_path, pdf_path, dokumen_id }
     *
     * @throws \RuntimeException jika phpoffice/phpword belum terpasang atau template tidak ada
     */
    public function generate(
        int     $permohonanId,
        string  $tipe,
        array   $variabel,
        int     $userId,
        ?string $nomorNd = null
    ): array {
        if (!class_exists(\PhpOffice\PhpWord\TemplateProcessor::class)) {
            throw new \RuntimeException(
                'phpoffice/phpword belum terpasang. Jalankan: composer require phpoffice/phpword mpdf/mpdf'
            );
        }

        $templateFile = $this->templateBase . (self::TEMPLATE_FILES[$tipe] ?? '');
        if (!file_exists($templateFile)) {
            throw new \RuntimeException(
                "Template Nota Dinas tidak ditemukan: {$templateFile}. " .
                "Letakkan file template di writable/templates/ltk/" . (self::TEMPLATE_FILES[$tipe] ?? $tipe . '.docx')
            );
        }

        // Pastikan semua variabel wajib tersedia (isi string kosong jika tidak diberikan)
        $requiredVars = self::TEMPLATE_VARS[$tipe] ?? [];
        foreach ($requiredVars as $var) {
            if (!array_key_exists($var, $variabel)) {
                $variabel[$var] = '';
            }
        }

        // Auto-populate: tanggal diisi otomatis jika tidak diberikan
        if (empty($variabel['tanggal'])) {
            $variabel['tanggal'] = $this->formatTanggalIndonesia(date('Y-m-d'));
        }

        $outDir = $this->outputBase . $permohonanId . '/';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $basename = $tipe . '_' . date('YmdHis');
        $docxPath = $this->generateDocx($templateFile, $variabel, $outDir, $basename);
        $pdfPath  = $this->convertToPdf($docxPath, $outDir, $basename);

        // Simpan record ke ltk_dokumen_output
        $relBase = 'ltk/nota_dinas/' . $permohonanId . '/';
        $dokumenId = (new DokumenOutputModel())->insert([
            'permohonan_id'   => $permohonanId,
            'tipe_dokumen'    => $tipe,
            'nomor_dokumen'   => $nomorNd,
            'tgl_dokumen'     => date('Y-m-d'),
            'file_docx_path'  => $relBase . basename($docxPath),
            'file_pdf_path'   => $pdfPath ? $relBase . basename($pdfPath) : null,
            'upload_manual'   => 0,
            'digenerate_oleh' => $userId,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return [
            'dokumen_id' => (new DokumenOutputModel())->insertID(),
            'docx_path'  => $docxPath,
            'pdf_path'   => $pdfPath,
            'docx_rel'   => $relBase . basename($docxPath),
            'pdf_rel'    => $pdfPath ? $relBase . basename($pdfPath) : null,
        ];
    }

    /**
     * Periksa apakah template untuk tipe tertentu sudah tersedia di disk.
     */
    public function templateTersedia(string $tipe): bool
    {
        $file = $this->templateBase . (self::TEMPLATE_FILES[$tipe] ?? '');
        return !empty(self::TEMPLATE_FILES[$tipe]) && file_exists($file);
    }

    /**
     * Kembalikan path absolut template untuk tipe tertentu.
     */
    public function getTemplatePath(string $tipe): string
    {
        return $this->templateBase . (self::TEMPLATE_FILES[$tipe] ?? '');
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────

    private function generateDocx(string $templateFile, array $variabel, string $outDir, string $basename): string
    {
        $processor = new \PhpOffice\PhpWord\TemplateProcessor($templateFile);

        foreach ($variabel as $placeholder => $nilai) {
            // Template menggunakan format ${PLACEHOLDER} — PhpWord TemplateProcessor
            // mengenali format ${VAR} secara native.
            $processor->setValue($placeholder, htmlspecialchars((string)($nilai ?? ''), ENT_XML1));
        }

        $outPath = $outDir . $basename . '.docx';
        $processor->saveAs($outPath);
        return $outPath;
    }

    private function convertToPdf(string $docxPath, string $outDir, string $basename): ?string
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            return null;
        }

        try {
            $phpWord   = \PhpOffice\PhpWord\IOFactory::load($docxPath);
            $writer    = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');

            ob_start();
            $writer->save('php://output');
            $html = ob_get_clean();

            $mpdf = new \Mpdf\Mpdf([
                'mode'          => 'utf-8',
                'format'        => 'A4',
                'margin_top'    => 30,
                'margin_bottom' => 25,
                'margin_left'   => 30,
                'margin_right'  => 25,
            ]);
            $mpdf->SetTitle('Nota Dinas');
            $mpdf->WriteHTML($html);

            $pdfPath = $outDir . $basename . '.pdf';
            $mpdf->Output($pdfPath, 'F');
            return $pdfPath;

        } catch (\Throwable $e) {
            log_message('warning', '[NotaDinasGenerator] Konversi PDF gagal: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Format tanggal ke format Indonesia: "01 Januari 2025"
     */
    public function formatTanggalIndonesia(string $tanggal): string
    {
        $bulan = [
            '01' => 'Januari',  '02' => 'Februari', '03' => 'Maret',
            '04' => 'April',    '05' => 'Mei',       '06' => 'Juni',
            '07' => 'Juli',     '08' => 'Agustus',   '09' => 'September',
            '10' => 'Oktober',  '11' => 'November',  '12' => 'Desember',
        ];

        [$y, $m, $d] = explode('-', $tanggal);
        return ltrim($d, '0') . ' ' . ($bulan[$m] ?? $m) . ' ' . $y;
    }
}
