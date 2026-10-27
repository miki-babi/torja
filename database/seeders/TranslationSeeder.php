<?php

namespace Database\Seeders;

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds UI strings from database/data/translations.json
 * ({group: {key: {locale: text}}}). Existing locale text is never
 * overwritten — admin edits survive reseeds; only missing keys/locales
 * are added.
 */
class TranslationSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $path = database_path('data/translations.json');

        if (! is_file($path)) {
            throw new RuntimeException("Translations file not found at [{$path}].");
        }

        $data = json_decode(file_get_contents($path) ?: '', true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new RuntimeException("Translations file at [{$path}] is not a JSON object.");
        }

        foreach ($data as $group => $lines) {
            foreach ($lines as $key => $text) {
                $line = LanguageLine::firstOrNew(['group' => $group, 'key' => $key]);
                $line->text = array_merge($text, $line->text ?? []);
                $line->save();
            }
        }

        Translator::flush();
    }
}
