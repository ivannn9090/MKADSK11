<?php

namespace App\Models\Schedule;

use CodeIgniter\Model;

class ScheduleReminderModel extends Model
{
    protected $table         = 'schedule_reminders';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['schedule_id', 'menit', 'metode'];
}
