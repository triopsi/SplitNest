<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a household administrator login (password is asked hidden).
 */
class AddHouseholdUser extends Command
{
    protected $signature = 'splitnest:user {email?} {--name= : Display name}';

    protected $description = 'Creates a household administrator (the password is asked hidden).';

    /**
     * Ask for name, e-mail and password, validate them and create the admin user.
     */
    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email') ?: $this->ask(__('users.command.email'))));
        $name = $this->option('name') ?: $this->ask(__('users.command.name'));
        $password = $this->secret(__('users.command.password'));
        $confirmation = $this->secret(__('users.command.password_repeat'));
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->is_admin = true;
        $user->save();
        $this->info(__('users.command.success', ['email' => $email]));

        return self::SUCCESS;
    }
}
