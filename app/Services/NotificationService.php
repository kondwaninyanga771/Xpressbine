<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function send(User $user, string $type, string $title, string $body, array $data = []): AppNotification
    {
        return AppNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
