<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'app:create-super-admin {--name=} {--username=} {--email=}';

    protected $description = 'Buat akun Super Admin (password diminta secara interaktif).';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?: $this->ask('Nama lengkap'));
        $username = Str::lower((string) ($this->option('username') ?: $this->ask('Username')));
        $email = Str::lower((string) ($this->option('email') ?: $this->ask('Email')));
        $password = (string) $this->secret('Password (minimal 12 karakter)');
        $confirmation = (string) $this->secret('Ulangi password');

        $validator = Validator::make(
            ['name' => $name, 'username' => $username, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12', 'max:100', 'confirmed'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'username' => $username, 'email' => $email, 'password' => $password]);
        $user->forceFill(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true, 'email_verified_at' => now()])->save();

        AuditLog::record('create', $user, null, ['role' => 'super_admin', 'via' => 'cli']);
        $this->info("Super Admin \"{$username}\" berhasil dibuat.");

        return self::SUCCESS;
    }
}
