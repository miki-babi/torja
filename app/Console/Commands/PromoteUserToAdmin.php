<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:promote {identifier : The email, phone number or username of the user to promote}')]
#[Description('Promote a user to the admin role')]
class PromoteUserToAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');

        $user = User::query()
            ->where('email', $identifier)
            ->orWhere('username', $identifier)
            ->orWhere('phone', PhoneNumber::normalize($identifier) ?? $identifier)
            ->first();

        if ($user === null) {
            $this->components->error("No user found matching [{$identifier}].");

            return self::FAILURE;
        }

        $label = $user->email ?? $user->phone ?? $user->username ?? "user #{$user->id}";

        if ($user->isAdmin()) {
            $this->components->info("{$label} is already an admin.");

            return self::SUCCESS;
        }

        $user->role = 'admin';
        $user->save();

        $this->components->info("{$label} has been promoted to admin.");

        return self::SUCCESS;
    }
}
