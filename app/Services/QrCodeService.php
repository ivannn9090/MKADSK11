<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;

/**
 * Service pembungkus chillerlan/php-qrcode.
 * Menghasilkan file PNG ke public/uploads/qrcodes/ dan
 * mengembalikan path relatif-publik-nya.
 */
class QrCodeService
{
    /** Direktori simpan file QR (relatif terhadap FCPATH) */
    private string $storageDir;

    /** Base URL publik untuk direktori tersebut */
    private string $publicDir;

    public function __construct()
    {
        $this->storageDir = FCPATH . 'uploads/qrcodes/';
        $this->publicDir  = 'uploads/qrcodes/';

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Generate QR Code PNG untuk $content dan simpan ke disk.
     *
     * @param  string $content  Teks/URL yang di-encode ke QR
     * @param  string $filename Nama file tanpa ekstensi (default: random)
     * @return string           Path relatif-publik (misalnya uploads/qrcodes/abc123.png)
     */
    public function generate(string $content, string $filename = ''): string
    {
        if ($filename === '') {
            $filename = bin2hex(random_bytes(8));
        }

        // Hapus ekstensi jika sudah disertakan
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $filePath = $this->storageDir . $filename . '.png';

        $options = new QROptions([
            'outputType'     => QROutputInterface::GDIMAGE_PNG,
            'eccLevel'       => EccLevel::M,
            'scale'          => 10,      // piksel per modul → 10×10 = 250-an px untuk ~25 modul
            'imageBase64'    => false,
            'marginSize'     => 2,
            'imageTransparent' => false,
            'imageTransparencyBG' => [255, 255, 255],
        ]);

        $qrcode = new QRCode($options);
        $qrcode->render($content, $filePath);

        return $this->publicDir . $filename . '.png';
    }

    /**
     * Hapus file QR dari disk jika ada.
     */
    public function delete(string $relativePath): void
    {
        if (!$relativePath) return;
        $abs = FCPATH . ltrim($relativePath, '/');
        if (is_file($abs)) {
            unlink($abs);
        }
    }

    /**
     * Kembalikan URL penuh yang bisa diakses browser.
     */
    public function publicUrl(string $relativePath): string
    {
        return base_url($relativePath);
    }
}
