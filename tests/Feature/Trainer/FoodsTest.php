<?php

use App\Models\Food;
use App\Models\MealProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function foodsTrainer(): User
{
    $user = User::factory()->trainer()->create();
    $user->ensureTrainer();

    return $user;
}

test('a trainer creates a food with dataset defaults applied', function () {
    $user = foodsTrainer();

    $this->actingAs($user)
        ->post(route('trainer.foods.store'), [
            'name_en' => 'Injera', // same name as the dataset entry — allowed
            'name_am' => 'እንጀራ',
            'category' => 'staple',
            'kcal_per_100g' => 245,
            'protein_g' => 8.1,
        ])
        ->assertRedirect(route('trainer.foods.index'));

    $food = $user->foods()->sole();

    expect($food->name_en)->toBe('Injera')
        ->and($food->isCustom())->toBeTrue()
        ->and($food->source)->toBe('custom')
        ->and($food->efct_code)->toBeNull()
        ->and((float) $food->kcal_per_100g)->toBe(245.0);
});

test('a trainer uploads a food photo', function () {
    Storage::fake('public');
    $user = foodsTrainer();

    $this->actingAs($user)
        ->post(route('trainer.foods.store'), [
            'name_en' => 'Shiro wot',
            'image' => UploadedFile::fake()->image('shiro.jpg'),
        ])
        ->assertRedirect(route('trainer.foods.index'));

    $food = $user->foods()->sole();
    Storage::disk('public')->assertExists($food->image_path);
});

test('food search returns only the coach own foods', function () {
    $user = foodsTrainer();
    $other = foodsTrainer();

    Food::factory()->create(['name_en' => 'My shiro', 'user_id' => $user->id]);
    Food::factory()->create(['name_en' => 'Shiro rival', 'user_id' => $other->id]);
    Food::factory()->create(['name_en' => 'Shiro dataset', 'user_id' => null]);

    $this->actingAs($user)
        ->getJson(route('trainer.foods.search', ['q' => 'shiro']))
        ->assertOk()
        ->assertJsonCount(1, 'foods')
        ->assertJsonPath('foods.0.name_en', 'My shiro');
});

test('the food index lists only the coach own foods', function () {
    $user = foodsTrainer();
    $other = foodsTrainer();

    Food::factory()->create(['name_en' => 'Mine', 'user_id' => $user->id]);
    Food::factory()->create(['name_en' => 'Theirs', 'user_id' => $other->id]);
    Food::factory()->create(['name_en' => 'Dataset']);

    $this->actingAs($user)
        ->get(route('trainer.foods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('foods.data', 1)
            ->where('foods.data.0.name_en', 'Mine'));
});

test('a meal program cannot embed another coach food or a dataset food', function () {
    $user = foodsTrainer();
    $other = foodsTrainer();

    $otherFood = Food::factory()->create(['name_en' => 'Theirs', 'user_id' => $other->id]);
    $datasetFood = Food::factory()->create(['user_id' => null]);
    $ownFood = Food::factory()->create(['name_en' => 'Own', 'user_id' => $user->id]);

    $payload = fn (int $foodId): array => [
        'name' => 'Week plan',
        'price_etb' => 0,
        'days' => [
            ['meals' => [
                ['meal_type' => 'breakfast', 'items' => [
                    ['food_id' => $foodId],
                ]],
            ]],
        ],
    ];

    $this->actingAs($user)
        ->post(route('trainer.meal-programs.store'), $payload($otherFood->id))
        ->assertSessionHasErrors('days.0.meals.0.items.0.food_id');

    $this->actingAs($user)
        ->post(route('trainer.meal-programs.store'), $payload($datasetFood->id))
        ->assertSessionHasErrors('days.0.meals.0.items.0.food_id');

    $this->actingAs($user)
        ->post(route('trainer.meal-programs.store'), $payload($ownFood->id))
        ->assertSessionDoesntHaveErrors();
});

test('a trainer cannot touch another coach food', function () {
    $user = foodsTrainer();
    $other = foodsTrainer();
    $food = Food::factory()->create(['name_en' => 'Theirs', 'user_id' => $other->id]);

    $this->actingAs($user)->get(route('trainer.foods.edit', $food))->assertNotFound();
    $this->actingAs($user)
        ->put(route('trainer.foods.update', $food), ['name_en' => 'Hijacked'])
        ->assertNotFound();
    $this->actingAs($user)
        ->delete(route('trainer.foods.destroy', $food))
        ->assertNotFound();

    expect($food->fresh()->name_en)->toBe('Theirs');
});

test('a food referenced by a meal program cannot be deleted', function () {
    $user = foodsTrainer();
    $food = Food::factory()->create(['name_en' => 'In use', 'user_id' => $user->id]);

    $program = MealProgram::factory()->create(['user_id' => $user->id]);
    $program->meals()->create(['day' => 1, 'meal_type' => 'lunch', 'position' => 0])
        ->items()->create(['food_id' => $food->id, 'position' => 0]);

    $this->actingAs($user)
        ->delete(route('trainer.foods.destroy', $food))
        ->assertRedirect();

    expect(Food::find($food->id))->not->toBeNull();
});

test('an unused food can be deleted', function () {
    $user = foodsTrainer();
    $food = Food::factory()->create(['name_en' => 'Spare', 'user_id' => $user->id]);

    $this->actingAs($user)
        ->delete(route('trainer.foods.destroy', $food))
        ->assertRedirect(route('trainer.foods.index'));

    expect(Food::find($food->id))->toBeNull();
});
