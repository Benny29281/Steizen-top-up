<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';
    protected $description = 'Membuat admin tanpa kata sandi default atau registrasi publik';

    public function handle(): int
    {
        $name = $this->ask('Nama admin');
        $email = mb_strtolower(trim((string) $this->ask('Email admin')));
        $password = $this->secret('Kata sandi (minimal 14 karakter, huruf besar/kecil, angka, simbol)');
        $confirmation = $this->secret('Ulangi kata sandi');
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
            'name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', 'confirmed', Password::min(14)->mixedCase()->numbers()->symbols(), function ($attribute, $value, $fail) {
                if (is_string($value) && strlen($value) > 72) $fail('Kata sandi maksimal 72 byte untuk hashing bcrypt.');
            }],
        ]);
        if ($validator->fails()) {
            // Jangan cetak validator input karena mengandung password.
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        $user = new User;
        $user->name = $name;
        $user->email = $email;
        $user->password = $password;
        $user->is_admin = true;
        $user->save();
        $this->info('Admin berhasil dibuat. Kata sandi disimpan menggunakan hashing Laravel.');
        return self::SUCCESS;
    }
}
