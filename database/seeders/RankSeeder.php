<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('ranks')->delete();

        $ranks = [
            ['id' => 1, 'name' => 'Anfanger (1)', 'available' => true],
            ['id' => 2, 'name' => 'Nutzlicher Teilnehmer (2)', 'available' => true],
            ['id' => 3, 'name' => 'Initiiert (3)', 'available' => true],
            ['id' => 4, 'name' => 'Kontrollierendes Mitglied (4)', 'available' => true],
            ['id' => 5, 'name' => 'Supervisor (5)', 'available' => true],
            ['id' => 6, 'name' => 'Schattendirektor (6)', 'available' => true],
            ['id' => 7, 'name' => 'Aktionsspezialist (7)', 'available' => true],
            ['id' => 8, 'name' => 'Aktionsorganisator (8)', 'available' => true],
            ['id' => 9, 'name' => 'Versorgungsleiter (9)', 'available' => true],
            ['id' => 10, 'name' => 'Abteilungsleiter (10)', 'available' => true],
            ['id' => 11, 'name' => 'Vertrauliche (11)', 'available' => false],
            ['id' => 12, 'name' => 'VIP (12)', 'available' => false],
            ['id' => 13, 'name' => 'Chefrekrutierer (13)', 'available' => false],
            ['id' => 14, 'name' => 'Regler (14)', 'available' => false],
            ['id' => 15, 'name' => 'Eigentumer (15)', 'available' => false],
        ];

        DB::table('ranks')->insert($ranks);
    }
}
