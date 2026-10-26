<?php

namespace App\Libraries;

use App\Models\AppSettingsModel;

/**
 * EmailService — kirim email menggunakan konfigurasi SMTP dari app_settings (DB).
 * Fallback ke .env jika setting DB kosong.
 */
class EmailService
{
    private AppSettingsModel $settings;

    public function __construct()
    {
        $this->settings = new AppSettingsModel();
    }

    /**
     * Bangun konfigurasi email dari DB, fallback ke .env.
     */
    private function buildConfig(): array
    {
        $s = $this->settings;

        return [
            'protocol'  => 'smtp',
            'SMTPHost'  => $s->get('smtp_host',       env('email.SMTPHost',  'localhost')),
            'SMTPPort'  => (int) $s->get('smtp_port',  env('email.SMTPPort',  587)),
            'SMTPUser'  => $s->get('smtp_user',        env('email.SMTPUser',  '')),
            'SMTPPass'  => $s->get('smtp_pass',        env('email.SMTPPass',  '')),
            'SMTPCrypto'=> $s->get('smtp_crypto',      env('email.SMTPCrypto', 'tls')),
            'fromEmail' => $s->get('smtp_from_email',  env('email.fromEmail', '')),
            'fromName'  => $s->get('smtp_from_name',   env('email.fromName',  'MKA System')),
            'mailType'  => 'html',
            'charset'   => 'utf-8',
        ];
    }

    /**
     * Kirim OTP ke user.
     *
     * @param string $toEmail   Alamat email tujuan
     * @param string $toName    Nama penerima
     * @param string $otp       Kode OTP 6 digit (plain)
     * @param string $purpose   'register' | 'reset_password'
     */
    public function sendOtp(string $toEmail, string $toName, string $otp, string $purpose = 'register'): bool
    {
        $appName = $this->settings->get('app_name', 'MKA System');

        $subjectMap = [
            'register'       => 'Kode Verifikasi Pendaftaran',
            'reset_password' => 'Kode Reset Password',
        ];
        $subject = $subjectMap[$purpose] ?? 'Kode Verifikasi';

        $purposeLabel = $purpose === 'register' ? 'mendaftarkan akun' : 'mereset password';

        // Escape semua nilai user-controlled sebelum dimasukkan ke HTML email
        $toNameSafe  = htmlspecialchars($toName,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $appNameSafe = htmlspecialchars($appName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // OTP hanya berisi digit — aman, tapi tetap di-escape untuk konsistensi
        $otpSafe     = htmlspecialchars($otp, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $message = "
        <html><body style='font-family:sans-serif;color:#1f2328;'>
        <p>Halo, <b>{$toNameSafe}</b></p>
        <p>Berikut adalah kode OTP untuk {$purposeLabel} di <b>{$appNameSafe}</b>:</p>
        <div style='font-size:32px;font-weight:700;letter-spacing:8px;color:#1e40af;margin:20px 0;'>
            {$otpSafe}
        </div>
        <p style='color:#57606a;font-size:13px;'>Kode ini berlaku selama <b>10 menit</b> dan hanya dapat digunakan sekali.</p>
        <p style='color:#57606a;font-size:13px;'>Jika Anda tidak meminta kode ini, abaikan email ini.</p>
        </body></html>";

        return $this->send($toEmail, $toName, $subject, $message);
    }

    /**
     * Kirim welcome email setelah registrasi berhasil.
     */
    public function sendWelcome(string $toEmail, string $toName): bool
    {
        $appName = $this->settings->get('app_name', 'MKA System');
        $appUrl  = $this->settings->get('app_url',  base_url());

        $toNameSafe  = htmlspecialchars($toName,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $appNameSafe = htmlspecialchars($appName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // URL di-encode untuk safety di href attribute
        $appUrlSafe  = htmlspecialchars($appUrl,  ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $subject = "Selamat Datang di {$appNameSafe}";
        $message = "
        <html><body style='font-family:sans-serif;color:#1f2328;'>
        <p>Halo, <b>{$toNameSafe}</b></p>
        <p>Akun Anda di <b>{$appNameSafe}</b> telah berhasil dibuat.</p>
        <p>Silakan login melalui: <a href='{$appUrlSafe}/login'>{$appUrlSafe}/login</a></p>
        <p style='color:#57606a;font-size:13px;'>Terima kasih telah mendaftar.</p>
        </body></html>";

        return $this->send($toEmail, $toName, $subject, $message);
    }

    /**
     * Test koneksi SMTP — kirim email percobaan ke alamat tertentu.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $appName  = $this->settings->get('app_name', 'MKA System');
        $fromEmail = $this->settings->get('smtp_from_email', '');

        if (empty($fromEmail)) {
            return ['success' => false, 'message' => 'Konfigurasi smtp_from_email belum diisi.'];
        }

        $appNameSafe = htmlspecialchars($appName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $subject = "[{$appNameSafe}] Test Koneksi Email";
        $message = "<html><body><p>Email test berhasil dikirim dari <b>{$appNameSafe}</b>.</p></body></html>";

        $ok = $this->send($fromEmail, 'Test', $subject, $message);

        return [
            'success' => $ok,
            'message' => $ok ? 'Koneksi email berhasil.' : 'Gagal mengirim email test.',
        ];
    }

    /**
     * Internal — build email service, kirim, return bool.
     */
    private function send(string $toEmail, string $toName, string $subject, string $message): bool
    {
        try {
            $config = $this->buildConfig();
            $email  = \Config\Services::email();
            $email->initialize($config);
            $email->setFrom($config['fromEmail'], $config['fromName']);
            $email->setTo($toEmail, $toName);
            $email->setSubject($subject);
            $email->setMessage($message);

            return $email->send(false);
        } catch (\Throwable $e) {
            log_message('error', '[EmailService] ' . $e->getMessage());
            return false;
        }
    }
}
