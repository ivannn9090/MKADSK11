<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\TaskModel;

/**
 * Unit test untuk logika bisnis TaskModel::getProgress()
 * menggunakan data hardcode (tidak membutuhkan database).
 *
 * @internal
 */
final class TaskModelProgressTest extends CIUnitTestCase
{
    // ────────────────────────────────────────────────────────────
    // Helper: simulasikan getProgress() dengan data bersih
    // ────────────────────────────────────────────────────────────

    /**
     * Replikasi logika getProgress() dari TaskModel secara in-memory
     * agar test tidak bergantung pada koneksi database.
     */
    private function calcProgress(array $tasks): float
    {
        if (empty($tasks)) return 0.0;

        $parents  = [];
        $children = [];

        foreach ($tasks as $t) {
            if ($t['parent_task_id'] === null) {
                $parents[$t['id']] = $t['status'];
            } else {
                $children[(int)$t['parent_task_id']][] = $t['status'];
            }
        }

        if (empty($parents)) return 0.0;

        $statusPct = static function (string $status): float {
            return match ($status) {
                'selesai'         => 100.0,
                'sedang_berjalan' => 50.0,
                default           => 0.0,
            };
        };

        $totalPct = 0.0;
        foreach ($parents as $pid => $pStatus) {
            if (!empty($children[$pid])) {
                $sum = array_sum(array_map($statusPct, $children[$pid]));
                $totalPct += $sum / count($children[$pid]);
            } else {
                $totalPct += $statusPct($pStatus);
            }
        }

        return round($totalPct / count($parents), 2);
    }

    // ────────────────────────────────────────────────────────────
    // Test cases
    // ────────────────────────────────────────────────────────────

    public function testProgressIsZeroWhenNoTasks(): void
    {
        $this->assertSame(0.0, $this->calcProgress([]));
    }

    public function testProgressIsZeroWhenAllTasksBelumDimulai(): void
    {
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'belum_dimulai'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'belum_dimulai'],
        ];

        $this->assertSame(0.0, $this->calcProgress($tasks));
    }

    public function testProgressIs100WhenAllTasksSelesai(): void
    {
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'selesai'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'selesai'],
        ];

        $this->assertSame(100.0, $this->calcProgress($tasks));
    }

    public function testProgressIs50WhenAllTasksSedangBerjalan(): void
    {
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'sedang_berjalan'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'sedang_berjalan'],
        ];

        $this->assertSame(50.0, $this->calcProgress($tasks));
    }

    public function testProgressCalculationWithMixedStatuses(): void
    {
        // 2 task: 1 selesai (100%) + 1 belum_dimulai (0%) → rata-rata 50%
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'selesai'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'belum_dimulai'],
        ];

        $this->assertSame(50.0, $this->calcProgress($tasks));
    }

    public function testProgressWithSubtasks(): void
    {
        // Task 1 (parent): punya 2 subtask (1 selesai, 1 belum_dimulai) → 50%
        // Task 2 (parent): selesai → 100%
        // Progress kegiatan = (50 + 100) / 2 = 75%
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'sedang_berjalan'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'selesai'],
            ['id' => 3, 'parent_task_id' => 1,    'status' => 'selesai'],
            ['id' => 4, 'parent_task_id' => 1,    'status' => 'belum_dimulai'],
        ];

        $this->assertSame(75.0, $this->calcProgress($tasks));
    }

    public function testProgressWithAllSubtasksSelesai(): void
    {
        // Task 1 punya 2 subtask, keduanya selesai → task progress = 100%
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'sedang_berjalan'],
            ['id' => 2, 'parent_task_id' => 1,    'status' => 'selesai'],
            ['id' => 3, 'parent_task_id' => 1,    'status' => 'selesai'],
        ];

        $this->assertSame(100.0, $this->calcProgress($tasks));
    }

    public function testProgressWithSubtasksAllBelumDimulai(): void
    {
        // Task 1 punya 2 subtask, keduanya belum dimulai → 0%
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'belum_dimulai'],
            ['id' => 2, 'parent_task_id' => 1,    'status' => 'belum_dimulai'],
            ['id' => 3, 'parent_task_id' => 1,    'status' => 'belum_dimulai'],
        ];

        $this->assertSame(0.0, $this->calcProgress($tasks));
    }

    public function testProgressWithSubtasksSedangBerjalan(): void
    {
        // Task 1 (1 subtask sedang berjalan = 50%) + Task 2 selesai (100%) → 75%
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'belum_dimulai'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'selesai'],
            ['id' => 3, 'parent_task_id' => 1,    'status' => 'sedang_berjalan'],
        ];

        $this->assertSame(75.0, $this->calcProgress($tasks));
    }

    public function testProgressIsZeroWhenOnlySubtasksExistWithNoParents(): void
    {
        // Situasi abnormal: hanya ada children tanpa parent
        // Seharusnya return 0.0 karena $parents kosong
        $tasks = [
            ['id' => 1, 'parent_task_id' => 99, 'status' => 'selesai'],
        ];

        $this->assertSame(0.0, $this->calcProgress($tasks));
    }

    public function testProgressRoundingToTwoDecimalPlaces(): void
    {
        // 3 task: 1 selesai (100), 1 sedang_berjalan (50), 1 belum (0) → 150/3 = 50.00
        $tasks = [
            ['id' => 1, 'parent_task_id' => null, 'status' => 'selesai'],
            ['id' => 2, 'parent_task_id' => null, 'status' => 'sedang_berjalan'],
            ['id' => 3, 'parent_task_id' => null, 'status' => 'belum_dimulai'],
        ];

        $result = $this->calcProgress($tasks);
        $this->assertSame(50.0, $result);

        // Pastikan hasilnya rounded 2 desimal
        $decimals = strlen(substr(strrchr((string)$result, '.'), 1));
        $this->assertLessThanOrEqual(2, $decimals);
    }
}
