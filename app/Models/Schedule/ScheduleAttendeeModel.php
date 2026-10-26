<?php

namespace App\Models\Schedule;

use CodeIgniter\Model;

class ScheduleAttendeeModel extends Model
{
    protected $table         = 'schedule_attendees';
    protected $primaryKey    = 'id';
    protected $useTimestamps = false;
    protected $allowedFields = ['schedule_id', 'user_id', 'nama', 'email', 'status_rsvp', 'created_at'];
}
