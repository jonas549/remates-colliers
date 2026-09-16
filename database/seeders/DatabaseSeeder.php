<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Solo desarrollo local. El deploy no corre db:seed; lo que el servidor necesita va en colliers:instalar. */
    public function run(): void
    {
        $this->call(DesarrolloSeeder::class);
    }
}
