<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lists login accounts so administrators can recover an unknown login.
 */
class ListHouseholdUsers extends Command
{
    protected $signature = 'splitnest:users {--admins : Only list administrators}';

    protected $description = 'Lists household users with their IDs, names, e-mail addresses and admin status.';

    /**
     * List matching accounts without exposing passwords or authentication tokens.
     */
    public function handle(): int
    {
        $users = User::query()
            ->when($this->option('admins'), fn (Builder $query): Builder => $query->where('is_admin', true))
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'is_admin']);

        if ($users->isEmpty()) {
            $this->info(__('users.list_command.empty'));

            return self::SUCCESS;
        }

        $this->table(
            ['ID', __('users.table.name'), __('users.table.email'), __('users.table.admin')],
            $users->map(fn (User $user): array => [
                $user->id, $user->name, $user->email,
                $user->is_admin ? __('users.list_command.yes') : __('users.list_command.no'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
