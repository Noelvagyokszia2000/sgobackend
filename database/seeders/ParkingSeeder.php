<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParkingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('parkings')->delete();

        $rows = [];

        for ($i = 1; $i <= 64; $i++) {
            $rows[] = [
                'user_id' => null,
                'occupied' => false
            ];
        }

        DB::table('parkings')->insert($rows);
    }
}