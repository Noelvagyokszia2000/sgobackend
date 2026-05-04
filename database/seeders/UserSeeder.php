<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->delete();

        $users = [];

        $users[] = [
            'username' => 'cigany1',
            'password' => Hash::make('cigany1'),
            'IgName' => 'DefaultUser',
            'createdAt' => now()->toDateString(),
            'warn' => 0,
            'weeklyPay' => now()->toDateString(),
            'isAdmin' => false,
            'rank_id' => 1
        ];

        $users[] = [
            'username' => 'cigany2',
            'password' => Hash::make('cigany2'),
            'IgName' => 'AdminUser',
            'createdAt' => now()->toDateString(),
            'warn' => 0,
            'weeklyPay' => now()->toDateString(),
            'isAdmin' => true,
            'rank_id' => 13
        ];

        DB::table('users')->insert($users);
    }
}