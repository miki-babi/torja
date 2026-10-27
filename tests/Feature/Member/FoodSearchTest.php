<?php

use App\Models\Food;
use App\Models\User;
use Database\Seeders\FoodDatabaseSeeder;
use Illuminate\Support\Str;

test('guests are redirected to login', function () {
    $this->get(route('member.foods.search'))->assertRedirect(route('login'));
});

test('search returns foods matching the query', function () {
    $this->seed(FoodDatabaseSeeder::class);

    $user = User::factory()->create();
    $term = Str::substr(Food::query()->value('name_en'), 0, 5);

    $response = $this->actingAs($user)
        ->get(route('member.foods.search', ['q' => $term]))
        ->assertOk()
        ->assertJsonStructure([
            'foods' => [['id', 'name_en', 'kcal_per_100g', 'protein_g', 'carbs_g', 'fat_g']],
        ]);

    $foods = collect($response->json('foods'));

    expect($foods)->not->toBeEmpty()
        ->and($foods->every(fn (array $food): bool => str_contains(
            Str::lower($food['name_en']), Str::lower($term),
        )))->toBeTrue();
});
