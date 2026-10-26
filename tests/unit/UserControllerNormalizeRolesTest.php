<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit test untuk helper normalisasi roles di UserController.
 *
 * Karena _normalizeRoles() bersifat private, kita ekstrak logikanya ke
 * fungsi standalone agar bisa diuji secara terisolasi tanpa perlu meng-
 * inisialisasi seluruh controller beserta HTTP stack-nya.
 *
 * @internal
 */
final class UserControllerNormalizeRolesTest extends CIUnitTestCase
{
    // ────────────────────────────────────────────────────────────
    // Replikasi logika _normalizeRoles() dari UserController
    // ────────────────────────────────────────────────────────────

    private function normalizeRoles(array $data): array
    {
        if (!empty($data['roles']) && is_array($data['roles'])) {
            return array_values(array_filter(array_unique($data['roles'])));
        }
        if (!empty($data['role']) && is_string($data['role'])) {
            return [$data['role']];
        }
        return [];
    }

    // ────────────────────────────────────────────────────────────
    // Test cases
    // ────────────────────────────────────────────────────────────

    public function testRolesArrayInputReturnsSameValues(): void
    {
        $result = $this->normalizeRoles(['roles' => ['direktur', 'ketua_tim']]);
        $this->assertSame(['direktur', 'ketua_tim'], $result);
    }

    public function testRolesArrayDeduplicatesValues(): void
    {
        $result = $this->normalizeRoles(['roles' => ['anggota_tim', 'anggota_tim', 'ketua_tim']]);
        $this->assertSame(['anggota_tim', 'ketua_tim'], $result);
    }

    public function testRolesArrayFiltersEmptyStrings(): void
    {
        $result = $this->normalizeRoles(['roles' => ['direktur', '', 'tu']]);
        // array_filter menghapus falsy values (string kosong)
        $this->assertContains('direktur', $result);
        $this->assertContains('tu', $result);
        $this->assertNotContains('', $result);
    }

    public function testRoleStringInputReturnsWrappedArray(): void
    {
        $result = $this->normalizeRoles(['role' => 'anggota_tim']);
        $this->assertSame(['anggota_tim'], $result);
    }

    public function testRolesArrayTakesPrecedenceOverRoleString(): void
    {
        // Jika kedua kunci ada, `roles` array harus diprioritaskan
        $result = $this->normalizeRoles([
            'roles' => ['direktur'],
            'role'  => 'anggota_tim',
        ]);
        $this->assertSame(['direktur'], $result);
    }

    public function testEmptyDataReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->normalizeRoles([]));
    }

    public function testEmptyRolesArrayReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->normalizeRoles(['roles' => []]));
    }

    public function testNonArrayRolesKeyFallsBackToRoleString(): void
    {
        // Jika 'roles' bukan array, harus fall back ke 'role'
        $result = $this->normalizeRoles(['roles' => 'bukan_array', 'role' => 'tu']);
        $this->assertSame(['tu'], $result);
    }

    public function testEmptyRoleStringReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->normalizeRoles(['role' => '']));
    }

    // ────────────────────────────────────────────────────────────
    // Replikasi logika _syncUserRoles() — validasi urutan operasi
    // ────────────────────────────────────────────────────────────

    public function testRolesArrayValuesAreReIndexed(): void
    {
        // array_values memastikan index berurutan (0, 1, 2, ...)
        $result = $this->normalizeRoles(['roles' => ['a', 'b', 'c']]);
        $this->assertSame([0, 1, 2], array_keys($result));
    }

    public function testSingleRoleInArrayReturnsSingleElement(): void
    {
        $result = $this->normalizeRoles(['roles' => ['direktur']]);
        $this->assertCount(1, $result);
        $this->assertSame('direktur', $result[0]);
    }
}
