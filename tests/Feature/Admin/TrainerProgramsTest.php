<?php

use App\Models\ExerciseProgram;
use App\Models\MealProgram;
use App\Models\Trainer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins see the trainer full program catalog', function () {
    $admin = User::factory()->staff()->create();
    $trainer = Trainer::factory()->create();

    ExerciseProgram::factory()->count(12)->create(['user_id' => $trainer->user_id]);
    MealProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Meal plan',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.trainers.programs', $trainer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/trainers/programs')
            ->has('programs', 12) // beyond the detail page's ten-item preview
            ->has('mealPrograms', 1)
            ->where('mealPrograms.0.share_url', fn ($url) => str_contains($url, '/shared/meal-programs/')));
});

test('admins can filter a trainer catalog to free programs only', function () {
    $admin = User::factory()->staff()->create();
    $trainer = Trainer::factory()->create();

    ExerciseProgram::factory()->create([
        'user_id' => $trainer->user_id,
        'name' => 'Freebie',
        'price_etb' => 0,
    ]);
    ExerciseProgram::factory()->paid(900)->create([
        'user_id' => $trainer->user_id,
        'name' => 'Premium',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.trainers.programs', [$trainer, 'free' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.free', true)
            ->has('programs', 1)
            ->where('programs.0.name', 'Freebie'));
});

test('non-staff users cannot view a trainer catalog', function () {
    $member = User::factory()->create();
    $trainer = Trainer::factory()->create();

    $this->actingAs($member)
        ->get(route('admin.trainers.programs', $trainer))
        ->assertForbidden();
});
