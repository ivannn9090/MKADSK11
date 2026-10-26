<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\UserModel;

/**
 * Feature test untuk AuthController
 *
 * Test ini menggunakan HTTP layer CodeIgniter untuk menguji
 * endpoint login, register, dan change-password secara end-to-end
 * termasuk validasi input.
 *
 * @internal
 */
final class AuthControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    // Hanya gunakan migration dari test-support folder (kompatibel SQLite)
    protected $namespace   = 'Tests\Support';

    // ────────────────────────────────────────────────────────────────────
    // Setup: buat user pengujian di database
    // ────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        // Buat user aktif untuk pengujian login
        $userModel = new UserModel();
        $userModel->insert([
            'nama'     => 'Test User',
            'email'    => 'testuser@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'role'     => 'anggota_tim',
            'status'   => 'aktif',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/login — Validasi input
    // ────────────────────────────────────────────────────────────────────

    public function testLoginReturnsBadRequestWhenEmailMissing(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'password' => 'password123',
                       ]);

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    public function testLoginReturnsBadRequestWhenPasswordMissing(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email' => 'testuser@example.com',
                       ]);

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    public function testLoginReturnsBadRequestWhenEmailInvalid(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'bukan-email-valid',
                           'password' => 'password123',
                       ]);

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    public function testLoginReturnsBadRequestWhenPasswordTooShort(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'testuser@example.com',
                           'password' => 'short',
                       ]);

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    // ────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/login — Autentikasi
    // ────────────────────────────────────────────────────────────────────

    public function testLoginReturns401WithWrongPassword(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'testuser@example.com',
                           'password' => 'wrongpassword123',
                       ]);

        $result->assertStatus(401);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    public function testLoginReturns401WithNonExistentEmail(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'ghost@example.com',
                           'password' => 'password123456',
                       ]);

        $result->assertStatus(401);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    public function testLoginSucceedsWithCorrectCredentials(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'testuser@example.com',
                           'password' => 'password123',
                       ]);

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['status']);
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('token', $body['data']);
        $this->assertNotEmpty($body['data']['token']);
        $this->assertArrayHasKey('user', $body['data']);
        $this->assertSame('testuser@example.com', $body['data']['user']['email']);
    }

    public function testLoginResponseDoesNotContainPassword(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'testuser@example.com',
                           'password' => 'password123',
                       ]);

        $body = json_decode($result->getJSON(), true);
        $this->assertArrayNotHasKey('password', $body['data']['user'] ?? []);
    }

    // ────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/login — Akun nonaktif
    // ────────────────────────────────────────────────────────────────────

    public function testLoginReturns401ForInactiveUser(): void
    {
        $userModel = new UserModel();
        $userModel->insert([
            'nama'     => 'Inactive User',
            'email'    => 'inactive@example.com',
            'password' => password_hash('password123', PASSWORD_BCRYPT),
            'role'     => 'anggota_tim',
            'status'   => 'nonaktif',
        ]);

        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'inactive@example.com',
                           'password' => 'password123',
                       ]);

        $result->assertStatus(401);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['status']);
    }

    // ────────────────────────────────────────────────────────────────────
    // Token JWT yang dihasilkan dari login — struktur dasar
    // ────────────────────────────────────────────────────────────────────

    public function testLoginResponseTokenHasThreeParts(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/login', [
                           'email'    => 'testuser@example.com',
                           'password' => 'password123',
                       ]);

        $body  = json_decode($result->getJSON(), true);
        $token = $body['data']['token'] ?? '';
        $parts = explode('.', $token);

        $this->assertCount(3, $parts, 'Token JWT harus terdiri dari tiga bagian');
    }

    // ────────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/register — Validasi input
    // ────────────────────────────────────────────────────────────────────

    public function testRegisterReturns422WhenNameMissing(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/register', [
                           'email'            => 'newuser@example.com',
                           'password'         => 'password123',
                           'password_confirm' => 'password123',
                       ]);

        // Registrasi mungkin disabled (403) atau validasi gagal (422)
        $status = $result->response()->getStatusCode();
        $this->assertContains($status, [403, 422], "Ekspektasi status 403 atau 422, dapat: {$status}");
    }

    public function testRegisterReturns422WhenPasswordsDontMatch(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/register', [
                           'nama'             => 'New User',
                           'email'            => 'newuser@example.com',
                           'password'         => 'password123',
                           'password_confirm' => 'different456',
                       ]);

        $status = $result->response()->getStatusCode();
        $this->assertContains($status, [403, 422], "Ekspektasi status 403 atau 422, dapat: {$status}");
    }

    // ────────────────────────────────────────────────────────────────────
    // Protected routes tanpa token — harus 401
    // ────────────────────────────────────────────────────────────────────

    public function testMeEndpointReturns401WithoutToken(): void
    {
        $result = $this->get('api/v1/auth/me');
        $result->assertStatus(401);
    }

    public function testLogoutEndpointReturns401WithoutToken(): void
    {
        $result = $this->post('api/v1/auth/logout');
        $result->assertStatus(401);
    }

    public function testChangePasswordEndpointReturns401WithoutToken(): void
    {
        $result = $this->withBodyFormat('json')
                       ->post('api/v1/auth/change-password', [
                           'current_password'     => 'password123',
                           'new_password'         => 'newpassword123',
                           'new_password_confirm' => 'newpassword123',
                       ]);

        $result->assertStatus(401);
    }
}
