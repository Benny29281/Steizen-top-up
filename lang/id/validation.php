<?php

return [
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'boolean' => 'Pilihan :attribute tidak valid.',
    'array' => 'Format :attribute tidak sesuai.',
    'uuid' => 'Identitas formulir tidak valid. Muat ulang halaman.',
    'unique' => ':attribute sudah digunakan.',
    'confirmed' => 'Konfirmasi :attribute tidak sesuai.',
    'in' => 'Pilihan :attribute tidak tersedia.',
    'regex' => 'Format :attribute tidak valid.',
    'not_regex' => 'Format :attribute tidak valid.',
    'min' => ['numeric' => ':attribute minimal :min.', 'string' => ':attribute minimal :min karakter.'],
    'max' => ['numeric' => ':attribute maksimal :max.', 'string' => ':attribute maksimal :max karakter.'],
    'password' => [
        'letters' => ':attribute harus mengandung huruf.',
        'mixed' => ':attribute harus mengandung huruf besar dan kecil.',
        'numbers' => ':attribute harus mengandung angka.',
        'symbols' => ':attribute harus mengandung simbol.',
    ],
    'attributes' => ['email' => 'email', 'password' => 'kata sandi', 'name' => 'nama', 'price' => 'harga', 'active' => 'status'],
];
