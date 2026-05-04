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
            ['id' => 1, 'name' => 'Anfänger (1)', 'available' => true],
            ['id' => 2, 'name' => 'Associate (2)', 'available' => true],
            ['id' => 3, 'name' => 'Initiiert (3)', 'available' => true],
            ['id' => 4, 'name' => 'Kontrollierendes Mitglied (4)', 'available' => true],
            ['id' => 5, 'name' => 'Chefrekrutierer (5)', 'available' => true],
            ['id' => 6, 'name' => 'Supervisor (6)', 'available' => true],
            ['id' => 7, 'name' => 'Aktionsspezialist (7)', 'available' => true],
            ['id' => 8, 'name' => 'Aktionsorganisator (8)', 'available' => true],
            ['id' => 9, 'name' => 'Abteilungsleiter (9)', 'available' => false],
            ['id' => 10, 'name' => 'Vertrauliche (10)', 'available' => false],
            ['id' => 11, 'name' => 'V.I.P (11)', 'available' => false],
            ['id' => 12, 'name' => 'Regler (12)', 'available' => false],
            ['id' => 13, 'name' => 'Eigentümer (13)', 'available' => false],
        ];

        DB::table('ranks')->insert($ranks);
    }
}