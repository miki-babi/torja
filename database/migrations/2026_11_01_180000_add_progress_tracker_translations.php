<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the expanded progress tracker (quick log, backfill,
     * analytics, measurements, photos) and the home weekly card.
     * Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.home' => [
            'week_title' => ['en' => 'This week', 'am' => 'ይህ ሳምንት'],
            'week_stats' => ['en' => ':sessions workouts · :volume kg lifted', 'am' => ':sessions ልምምዶች · :volume ኪግ ክብደት'],
        ],
        'member.progress' => [
            'log_set' => ['en' => 'Log a set', 'am' => 'ስብስብ ይመዝግቡ'],
            'log_past' => ['en' => 'Log past workout', 'am' => 'ያለፈ ልምምድ ይመዝግቡ'],
            'log_past_hint' => ['en' => 'Backfill a workout you already did.', 'am' => 'የሰሩትን ልምምድ በኋላ ይመዝግቡ።'],
            'workout_date' => ['en' => 'Date', 'am' => 'ቀን'],
            'add_set' => ['en' => 'Add set', 'am' => 'ስብስብ ጨምር'],
            'add_exercise' => ['en' => 'Add exercise', 'am' => 'ልምምድ ጨምር'],
            'save_workout' => ['en' => 'Save workout', 'am' => 'ልምምድ አስቀምጥ'],
            'pick_exercise' => ['en' => 'Pick an exercise', 'am' => 'ልምምድ ይምረጡ'],
            'pick_exercise_hint' => ['en' => 'Search the catalog and tap to select.', 'am' => 'ይፈልጉ እና ለመምረጥ ይንኩ።'],
            'vs_last_week' => ['en' => 'Last week: :sessions workouts · :volume kg', 'am' => 'ባለፈው ሳምንት: :sessions ልምምዶች · :volume ኪግ'],
            'consistency' => ['en' => 'Consistency', 'am' => 'ቀጣይነት'],
            'muscle_split' => ['en' => 'Volume by muscle', 'am' => 'በጭንቅላት ክብደት'],
            'measurements' => ['en' => 'Measurements', 'am' => 'መለኪያዎች'],
            'log_measurements' => ['en' => 'Log measurements', 'am' => 'መለኪያዎችን ይመዝግቡ'],
            'photos' => ['en' => 'Progress photos', 'am' => 'የእድገት ፎቶዎች'],
            'add_photo' => ['en' => 'Add photo', 'am' => 'ፎቶ ጨምር'],
            'photos_empty' => ['en' => 'No photos yet — upload your first check-in.', 'am' => 'እስካሁን ፎቶ የለም — የመጀመሪያዎን ይስቀሉ።'],
            'photo_failed' => ['en' => 'Could not upload photo', 'am' => 'ፎቶ መስቀል አልተቻለም'],
            'delete_photo' => ['en' => 'Delete photo', 'am' => 'ፎቶ ሰርዝ'],
            'metric.waist_cm' => ['en' => 'Waist', 'am' => 'ወገብ'],
            'metric.chest_cm' => ['en' => 'Chest', 'am' => 'ደረት'],
            'metric.arm_cm' => ['en' => 'Arm', 'am' => 'ክንድ'],
            'metric.hip_cm' => ['en' => 'Hip', 'am' => 'ወፍራም'],
            'metric.thigh_cm' => ['en' => 'Thigh', 'am' => 'ወዘን'],
        ],
    ];

    public function up(): void
    {
        foreach (self::LINES as $group => $lines) {
            foreach ($lines as $key => $text) {
                $line = LanguageLine::firstOrNew(['group' => $group, 'key' => $key]);
                $line->text = array_merge($text, $line->text ?? []);
                $line->save();
            }
        }

        Translator::flush();
    }

    public function down(): void
    {
        foreach (self::LINES as $group => $lines) {
            LanguageLine::where('group', $group)
                ->whereIn('key', array_keys($lines))
                ->delete();
        }

        Translator::flush();
    }
};
