<?php

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function foodAdmin(): User
{
    return User::factory()->admin()->create();
}

test('non-admin users cannot manage foods or ingredients', function () {
    $food = Food::factory()->create();
    $ingredient = Ingredient::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.foods.create'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.foods.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.foods.update', $food), [])->assertForbidden();
    $this->actingAs($user)->get(route('admin.ingredients.create'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.ingredients.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.ingredients.update', $ingredient), [])->assertForbidden();
});

test('admins can create a manual food with image, flags and ingredients', function () {
    Storage::fake('public');
    $ingredient = Ingredient::factory()->create();
    $category = Food::factory()->create()->category;

    $response = $this->actingAs(foodAdmin())->post(route('admin.foods.store'), [
        'name_en' => 'Shiro wot',
        'name_am' => 'ሽሮ ወጥ',
        'category' => $category,
        'kcal_per_100g' => 150,
        'protein_g' => 8,
        'carbs_g' => 12,
        'fat_g' => 7,
        'image' => UploadedFile::fake()->image('shiro.jpg', 800, 600),
        'meal_types' => ['lunch', 'dinner'],
        'allergens' => ['soy'],
        'is_vegan' => '1',
        'is_vegetarian' => '1',
        'is_fasting_friendly' => '1',
        'goal_fat_loss' => '1',
        'ingredient_ids' => [$ingredient->id],
        'review_needed' => '0',
    ]);

    $food = Food::where('name_en', 'Shiro wot')->firstOrFail();
    $response->assertRedirect(route('admin.foods.edit', $food));

    expect($food->id)->toBeGreaterThanOrEqual(90000)
        ->and($food->source)->toBe('Manual entry')
        ->and($food->efct_code)->toBeNull()
        ->and($food->is_vegan)->toBeTrue()
        ->and($food->meal_types)->toBe(['lunch', 'dinner'])
        ->and($food->allergens)->toBe(['soy'])
        ->and($food->image_path)->not->toBeNull()
        ->and($food->ingredients)->toHaveCount(1);

    Storage::disk('public')->assertExists($food->image_path);
});

test('manual food ids stay in the reserved range', function () {
    Food::factory()->create(['id' => 99999]);

    $this->actingAs(foodAdmin())->post(route('admin.foods.store'), [
        'name_en' => 'Manual meal',
        'category' => 'Miscellaneous',
    ]);

    expect(Food::where('name_en', 'Manual meal')->firstOrFail()->id)->toBe(100000);
});

test('admins can update food metadata, image and ingredients', function () {
    Storage::fake('public');
    $food = Food::factory()->create(['image_path' => null, 'is_vegan' => false]);
    $ingredient = Ingredient::factory()->create();

    $this->actingAs(foodAdmin())->put(route('admin.foods.update', $food), [
        'name_en' => 'Renamed food',
        'category' => $food->category,
        'image' => UploadedFile::fake()->image('new.webp', 400, 300),
        'is_vegan' => '1',
        'ingredient_ids' => [$ingredient->id],
    ])->assertRedirect(route('admin.foods.edit', $food));

    $food->refresh();
    expect($food->name_en)->toBe('Renamed food')
        ->and($food->is_vegan)->toBeTrue()
        ->and($food->image_path)->not->toBeNull()
        ->and($food->ingredients->pluck('id')->all())->toBe([$ingredient->id]);
});

test('food edit and create pages expose form metadata', function () {
    $food = Food::factory()->create();

    $this->actingAs(foodAdmin())
        ->get(route('admin.foods.edit', $food))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/foods/edit')
            ->has('food.image_url')
            ->has('food.ingredient_ids')
            ->has('mealTypes')
            ->has('allergens', 14)
            ->has('ingredientOptions'));

    $this->actingAs(foodAdmin())
        ->get(route('admin.foods.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/foods/edit')
            ->where('food', null));
});

test('admins can create a manual ingredient with a custom slug', function () {
    $this->actingAs(foodAdmin())->post(route('admin.ingredients.store'), [
        'name_en' => 'Teff flour',
        'name_am' => 'ጤፍ',
        'category' => 'Grains',
        'allergens' => ['gluten_cereals'],
        'show_in_onboarding' => '1',
        'is_vegan' => '1',
    ])->assertRedirect();

    $ingredient = Ingredient::where('name_en', 'Teff flour')->firstOrFail();
    expect($ingredient->slug)->toBe('custom-teff-flour')
        ->and($ingredient->allergens)->toBe(['gluten_cereals'])
        ->and($ingredient->is_vegan)->toBeTrue();

    $this->actingAs(foodAdmin())->post(route('admin.ingredients.store'), [
        'name_en' => 'Teff flour',
        'name_am' => 'ጤፍ',
        'category' => 'Grains',
    ]);

    expect(Ingredient::where('name_en', 'Teff flour')->count())->toBe(2)
        ->and(Ingredient::where('slug', 'custom-teff-flour-2')->exists())->toBeTrue();
});

test('admins can update an ingredient category and allergens', function () {
    $ingredient = Ingredient::factory()->create(['allergens' => [], 'category' => 'Old']);

    $this->actingAs(foodAdmin())->put(route('admin.ingredients.update', $ingredient), [
        'name_en' => $ingredient->name_en,
        'name_am' => $ingredient->name_am,
        'category' => 'New cat',
        'allergens' => ['peanuts'],
    ])->assertRedirect(route('admin.ingredients.edit', $ingredient));

    expect($ingredient->refresh()->category)->toBe('New cat')
        ->and($ingredient->allergens)->toBe(['peanuts']);
});
