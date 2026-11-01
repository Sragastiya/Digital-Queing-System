<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

Artisan::command('pbl:staff {email} {--name=Staf restoran}', function () {
    $email = $this->argument('email');
    if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
        $this->error('Alamat email tidak valid.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('Akun sudah ada; akun tidak diubah.');

        return 1;
    }
    $password = $this->secret('Kata sandi baru (minimal 12 karakter)');
    if (! is_string($password) || strlen($password) < 12) {
        $this->error('Gunakan minimal 12 karakter.');

        return 1;
    }
    User::create(['name' => $this->option('name'), 'email' => $email, 'password' => Hash::make($password)]);
    $this->info('Akun staf dibuat.');
})->purpose('Buat akun staf tanpa menyimpan kata sandi di command history');
