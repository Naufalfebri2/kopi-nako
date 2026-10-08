<?php

namespace Database\Seeders;

use Database\Seeders\ReferenceSeeder;
use Database\Seeders\SampleActivitySeeder;
use Database\Seeders\StaffSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceSeeder::class,
            StaffSeeder::class,
            SampleActivitySeeder::class,
        ]);
    }
}
