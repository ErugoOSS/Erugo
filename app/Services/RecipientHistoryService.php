<?php

namespace App\Services;

use App\Models\RecipientHistory;

class RecipientHistoryService
{
    public function touchRecipient(int $userId, string $email, ?string $name = null): RecipientHistory
    {
        $email = strtolower(trim($email));

        $name = $name !== null ? trim($name) : null;
        if ($name === '') {
            $name = null;
        }

        $history = RecipientHistory::where('user_id', $userId)
            ->where('email', $email)
            ->first();

        if ($history) {
            $history->use_count = (int) $history->use_count + 1;
            $history->last_used_at = now();

            if ($name && (!$history->name || trim($history->name) === '')) {
                $history->name = $name;
            }

            $history->save();

            return $history;
        }

        return RecipientHistory::create([
            'user_id' => $userId,
            'email' => $email,
            'name' => $name,
            'use_count' => 1,
            'last_used_at' => now(),
        ]);
    }
}
