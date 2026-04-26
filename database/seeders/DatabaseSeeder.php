<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminMasterSeeder::class,
            DonoFazendaSeeder::class,
            SettingsSeeder::class,
            CmsPageSeeder::class,
            CmsMenuSeeder::class,
            CategorySeeder::class,
            AnimalSpeciesSeeder::class,
            DocumentCategorySeeder::class,
            TutorialSeeder::class,
        ]);
    }
}
