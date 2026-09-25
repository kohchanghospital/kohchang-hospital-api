<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignUsername extends Command
{
    protected $signature = 'users:assign-username {userId : Existing user ID} {username : New login username}';
    protected $description = 'Assign a login username to an existing account without changing its password or two-factor settings';

    public function handle(): int
    {
        $username = strtolower(trim((string) $this->argument('username')));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/D', $username)) {
            $this->error('Username must be 3-64 ASCII letters, digits, dots, underscores or hyphens.');
            return self::FAILURE;
        }
        $user = User::find($this->argument('userId'));
        if (!$user) {
            $this->error('User not found.');
            return self::FAILURE;
        }
        if (User::whereRaw('LOWER(username) = ?', [$username])->whereKeyNot($user->id)->exists()) {
            $this->error('Username is already in use.');
            return self::FAILURE;
        }
        $user->username = $username;
        $user->save();
        $this->info("Username assigned to user {$user->id}.");
        return self::SUCCESS;
    }
}
