<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Support\Locales;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the English and Amharic languages. Amharic is the default — the
     * main audience is Amharic-speaking.
     */
    public function run(): void
    {
        Language::upsert([
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'is_active' => true, 'is_default' => false, 'position' => 0],
            ['code' => 'am', 'name' => 'Amharic', 'native_name' => 'አማርኛ', 'is_active' => true, 'is_default' => true, 'position' => 1],
        ], uniqueBy: ['code'], update: ['name', 'native_name', 'is_active', 'is_default', 'position']);

        Locales::flush();
    }
}
