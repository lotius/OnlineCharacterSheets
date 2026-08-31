<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdministrator extends Command
{
    protected $signature = 'app:make-admin {email}';

    protected $description = 'Grant system administrator access to an existing user';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $user->update(['is_admin' => true]);
        $this->info("{$user->email} is now an administrator.");

        return self::SUCCESS;
    }
}
