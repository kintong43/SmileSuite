<?php

namespace App\Console\Commands;

use App\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {name}';

    protected $description = 'Create a platform administrator who must enroll in MFA on first sign in';

    public function handle(): int
    {
        $data = ['email' => mb_strtolower(trim((string) $this->argument('email'))), 'name' => trim((string) $this->argument('name'))];
        $validator = Validator::make($data, ['email' => 'required|email|max:255|unique:platform_admins,email', 'name' => 'required|string|max:255']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        $data['password'] = $this->secret('Password (at least 12 characters with letters and numbers)');
        $validator = Validator::make($data, ['password' => ['required', 'string', Password::min(12)->letters()->numbers()]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        PlatformAdmin::create($data + ['status' => 'active']);
        $this->info('Platform administrator created. Sign in on the administrator host and enroll an authenticator.');

        return self::SUCCESS;
    }
}
