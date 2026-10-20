<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Edits an existing login, including password recovery without e-mail access.
 */
class EditHouseholdUser extends Command
{
    protected $signature = 'splitnest:user-edit
        {user : User ID or current e-mail address (see splitnest:users)}
        {--name= : New display name}
        {--email= : New e-mail address}
        {--password : Ask for a new password using a hidden prompt}
        {--admin : Grant administrator access}
        {--no-admin : Remove administrator access}';

    protected $description = 'Edits a household user. With no options, asks interactively for the account details.';

    /**
     * Resolve and validate account changes, protect the last administrator and save atomically.
     */
    public function handle(): int
    {
        if ($this->option('admin') && $this->option('no-admin')) {
            $this->error(__('users.edit_command.conflicting_roles'));

            return self::FAILURE;
        }

        $identifier = trim((string) $this->argument('user'));
        $user = User::query()->where(ctype_digit($identifier) ? 'id' : 'email', ctype_digit($identifier) ? $identifier : strtolower($identifier))->first();
        if ($user === null) {
            $this->error(__('users.edit_command.not_found', ['user' => $identifier]));

            return self::FAILURE;
        }

        $interactive = $this->option('name') === null && $this->option('email') === null
            && ! $this->option('password') && ! $this->option('admin') && ! $this->option('no-admin');
        if (! $this->input->isInteractive() && ($interactive || $this->option('password'))) {
            $this->error(__('users.edit_command.interaction_required'));

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?? ($interactive ? $this->ask(__('users.command.name'), $user->name) : $user->name),
            'email' => $this->option('email') ?? ($interactive ? $this->ask(__('users.command.email'), $user->email) : $user->email),
            'is_admin' => $this->option('admin') || (! $this->option('no-admin') && $user->is_admin),
        ];
        $data['name'] = trim((string) $data['name']);
        $data['email'] = strtolower(trim((string) $data['email']));

        if ($interactive || $this->option('password')) {
            $password = $this->secret(__($interactive ? 'users.edit_command.optional_password' : 'users.command.password'));
            if ($this->option('password') || filled($password)) {
                $data['password'] = $password;
                $data['password_confirmation'] = $this->secret(__('users.command.password_repeat'));
            }
        }
        if ($interactive) {
            $data['is_admin'] = $this->confirm(__('users.edit_command.admin_question'), $user->is_admin);
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['sometimes', 'required', 'string', 'confirmed', PasswordRule::min(12)],
            'is_admin' => ['required', 'boolean'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($user, $data): void {
                if (! $data['is_admin']) {
                    $admins = User::where('is_admin', true)->lockForUpdate()->get();
                    if ($admins->contains('id', $user->id) && $admins->count() === 1) {
                        throw ValidationException::withMessages(['is_admin' => __('users.edit_command.last_admin')]);
                    }
                }

                $emailChanged = $data['email'] !== $user->email;
                if (isset($data['password']) || $emailChanged) {
                    Password::broker()->deleteToken($user);
                    $user->setRememberToken(Str::random(60));
                }
                if ($emailChanged) {
                    $user->email_verified_at = null;
                }
                unset($data['password_confirmation']);
                $user->fill($data)->save();
            });
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info(__('users.edit_command.success', ['id' => $user->id, 'email' => $user->email]));

        return self::SUCCESS;
    }
}
