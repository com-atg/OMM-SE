<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        // Use firstOrCreate so re-running the seeder does not clobber an
        // admin-customised template already saved in the database.
        AppSetting::firstOrCreate(
            ['key' => 'email_template'],
            ['value' => file_get_contents(resource_path('views/emails/evaluation.blade.php'))],
        );
    }
}
