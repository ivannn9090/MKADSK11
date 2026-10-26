<?php

use App\Models\OtpModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Unit test untuk OtpModel
 *
 * @internal
 */
final class OtpModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    // Hanya gunakan migration dari test-support folder (kompatibel SQLite)
    protected $namespace   = 'Tests\Support';

    private OtpModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OtpModel();
    }

    // ────────────────────────────────────────────────
    // generate()
    // ────────────────────────────────────────────────

    public function testGenerateReturnsEightDigitString(): void
    {
        $email = 'test@example.com';
        $otp   = $this->model->generate($email, 'register');

        $this->assertIsString($otp);
        $this->assertMatchesRegularExpression('/^\d{8}$/', $otp, 'OTP harus berupa 8 digit angka');
    }

    public function testGenerateSavesHashedOtpToDatabase(): void
    {
        $email = 'otp@example.com';
        $otp   = $this->model->generate($email, 'register');

        $record = $this->model->where('email', $email)->where('purpose', 'register')->first();

        $this->assertNotNull($record, 'Record OTP harus tersimpan di database');
        $this->assertSame(0, (int)$record['used']);

        // Pastikan yang disimpan adalah hash SHA-256, bukan plain OTP
        $this->assertSame(hash('sha256', $otp), $record['otp']);
    }

    public function testGenerateDeletesOldOtpForSameEmailAndPurpose(): void
    {
        $email = 'regentest@example.com';

        // Generate pertama
        $this->model->generate($email, 'register');

        // Generate kedua — OTP lama harus dihapus
        $this->model->generate($email, 'register');

        $count = $this->model->where('email', $email)->where('purpose', 'register')->countAllResults();
        $this->assertSame(1, $count, 'Hanya satu OTP yang boleh aktif per email+purpose');
    }

    public function testGenerateForDifferentPurposesCoexist(): void
    {
        $email = 'multiuse@example.com';
        $this->model->generate($email, 'register');
        $this->model->generate($email, 'reset_password');

        $count = $this->model->where('email', $email)->countAllResults();
        $this->assertSame(2, $count, 'OTP untuk purpose berbeda harus bisa koeksistensi');
    }

    // ────────────────────────────────────────────────
    // verify()
    // ────────────────────────────────────────────────

    public function testVerifyValidOtpReturnsTrue(): void
    {
        $email = 'verify@example.com';
        $otp   = $this->model->generate($email, 'register');

        $this->assertTrue($this->model->verify($email, $otp, 'register'));
    }

    public function testVerifyMarksOtpAsUsedAfterSuccess(): void
    {
        $email = 'used@example.com';
        $otp   = $this->model->generate($email, 'register');

        $this->model->verify($email, $otp, 'register');

        $record = $this->model->where('email', $email)->first();
        $this->assertSame(1, (int)$record['used'], 'OTP harus ditandai used=1 setelah verifikasi berhasil');
    }

    public function testVerifyReturnsFalseForWrongOtp(): void
    {
        $email = 'wrong@example.com';
        $this->model->generate($email, 'register');

        $this->assertFalse($this->model->verify($email, '00000000', 'register'));
    }

    public function testVerifyInvalidatesOtpAfterFailedAttempt(): void
    {
        $email = 'brute@example.com';
        $otp   = $this->model->generate($email, 'register');

        // Percobaan pertama dengan OTP salah
        $this->model->verify($email, '00000000', 'register');

        // Percobaan kedua dengan OTP benar harus gagal (OTP sudah dinonaktifkan)
        $this->assertFalse(
            $this->model->verify($email, $otp, 'register'),
            'OTP yang benar tidak boleh berhasil setelah percobaan gagal sebelumnya'
        );
    }

    public function testVerifyReturnsFalseForAlreadyUsedOtp(): void
    {
        $email = 'reuse@example.com';
        $otp   = $this->model->generate($email, 'register');

        // Verifikasi pertama — berhasil
        $this->assertTrue($this->model->verify($email, $otp, 'register'));

        // Verifikasi kedua dengan OTP yang sama — harus gagal
        $this->assertFalse($this->model->verify($email, $otp, 'register'));
    }

    public function testVerifyReturnsFalseForNonExistentEmail(): void
    {
        $this->assertFalse($this->model->verify('ghost@example.com', '12345678', 'register'));
    }

    // ────────────────────────────────────────────────
    // cleanExpired()
    // ────────────────────────────────────────────────

    public function testCleanExpiredRemovesExpiredRecords(): void
    {
        $db = \Config\Database::connect();

        // Insert OTP yang sudah expired secara manual
        $db->table('otp_codes')->insert([
            'email'      => 'expired@example.com',
            'otp'        => hash('sha256', '12345678'),
            'purpose'    => 'register',
            'expires_at' => date('Y-m-d H:i:s', time() - 3600), // 1 jam lalu
            'used'       => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->model->cleanExpired();

        $count = $this->model->where('email', 'expired@example.com')->countAllResults();
        $this->assertSame(0, $count, 'OTP expired harus terhapus oleh cleanExpired()');
    }

    public function testCleanExpiredKeepsValidRecords(): void
    {
        $email = 'valid@example.com';
        $this->model->generate($email, 'register'); // valid 10 menit

        $this->model->cleanExpired();

        $count = $this->model->where('email', $email)->countAllResults();
        $this->assertSame(1, $count, 'OTP yang masih valid tidak boleh dihapus oleh cleanExpired()');
    }
}
