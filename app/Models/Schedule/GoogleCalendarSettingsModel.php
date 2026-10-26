<?php

namespace App\Models\Schedule;

use CodeIgniter\Model;

class GoogleCalendarSettingsModel extends Model
{
    protected $table         = 'google_calendar_settings';
    protected $primaryKey    = 'id';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'nama', 'client_id', 'client_secret', 'project_id', 'calendar_id',
        'redirect_url', 'access_token', 'refresh_token', 'oauth_state',
        'token_expires', 'is_connected', 'connected_email', 'is_default',
        'reminder_h7', 'reminder_h3', 'reminder_h1', 'reminder_aktif',
        'scope_type', 'scope_value', 'created_by', 'updated_by',
    ];

    public function getDefault(): ?array
    {
        return $this->where('is_default', 1)->first()
            ?? $this->first();
    }

    public function saveTokens(int $id, array $tokenData, string $email): void
    {
        $this->update($id, [
            'access_token'    => json_encode($tokenData),
            'refresh_token'   => $tokenData['refresh_token'] ?? null,
            'token_expires'   => isset($tokenData['expires_in'])
                ? date('Y-m-d H:i:s', time() + (int)$tokenData['expires_in'])
                : null,
            'is_connected'    => 1,
            'connected_email' => $email,
        ]);
    }

    public function disconnect(int $id): void
    {
        $this->update($id, [
            'access_token'    => null,
            'refresh_token'   => null,
            'oauth_state'     => null,
            'token_expires'   => null,
            'is_connected'    => 0,
            'connected_email' => null,
        ]);
    }
}
