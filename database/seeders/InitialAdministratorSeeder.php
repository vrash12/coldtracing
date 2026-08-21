<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class InitialAdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $name = trim((string) config('coldtrace.initial_admin.name'));
        $email = trim((string) config('coldtrace.initial_admin.email'));
        $password = (string) config('coldtrace.initial_admin.password');

        if ($name === '' && $email === '' && $password === '') {
            $this->command?->warn(
                'Initial administrator was not created because its environment values are empty.'
            );

            return;
        }

        if ($name === '' || $email === '' || $password === '') {
            throw new RuntimeException(
                'COLDTRACE_INITIAL_ADMIN_NAME, COLDTRACE_INITIAL_ADMIN_EMAIL, and '
                .'COLDTRACE_INITIAL_ADMIN_PASSWORD must all be provided.'
            );
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The initial administrator email is invalid.');
        }

        if (strlen($password) < 12) {
            throw new RuntimeException(
                'The initial administrator password must contain at least 12 characters.'
            );
        }

        $role = Role::where('name', 'Administrator')->firstOrFail();

        User::updateOrCreate(
            ['email' => $email],
            [
                'role_id' => $role->id,
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'active',
            ]
        );
    }
}
