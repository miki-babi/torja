<?php

use App\Models\Food;
use App\Models\Ingredient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('admins can list and search foods', function () {
    Food::factory()->create(['name_en' => 'Injera', 'category' => 'Cereals and their products']);
    Food::factory()->create(['name_en' => 'Shiro', 'category' => 'Legumes and their products']);

    $this->actingAs($this->admin)
        ->get(route('admin.foods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/foods/index')
            ->has('foods.data', 2)
            ->has('categories'));

    $this->actingAs($this->admin)
        ->get(route('admin.foods.index', ['search' => 'injera']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('foods.data', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.foods.index', ['category' => 'Legumes and their products']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('foods.data', 1)
            ->where('foods.data.0.name_en', 'Shiro'));
});

test('admins can update a food', function () {
    $food = Food::factory()->create(['name_en' => 'Old name', 'review_needed' => true]);

    $this->actingAs($this->admin)
        ->get(route('admin.foods.edit', $food))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/foods/edit')
            ->where('food.id', $food->id));

    $this->actingAs($this->admin)
        ->put(route('admin.foods.update', $food), [
            'name_en' => 'New name',
            'name_am' => 'አዲስ',
            'category' => $food->category,
            'kcal_per_100g' => 123.4,
            'protein_g' => 10,
            'carbs_g' => 20,
            'fat_g' => 5,
            'fibre_g' => 3,
            'review_needed' => false,
            'data_fix_note' => 'Fixed from EFCT reprint',
        ])
        ->assertRedirect(route('admin.foods.edit', $food));

    $food->refresh();
    expect($food->name_en)->toBe('New name')
        ->and($food->review_needed)->toBeFalse()
        ->and($food->data_fix_note)->toBe('Fixed from EFCT reprint')
        ->and((float) $food->kcal_per_100g)->toBe(123.4);
});

test('food update validates its input', function () {
    $food = Food::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.foods.update', $food), ['name_en' => ''])
        ->assertSessionHasErrors('name_en');
});

test('admins can list and update ingredients', function () {
    $ingredient = Ingredient::factory()->create([
        'name_en' => 'Berbere',
        'show_in_onboarding' => false,
        'is_vegan' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.ingredients.index', ['search' => 'berbere']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/ingredients/index')
            ->has('ingredients.data', 1));

    $this->actingAs($this->admin)
        ->put(route('admin.ingredients.update', $ingredient), [
            'name_en' => 'Berbere spice',
            'name_am' => 'በርበሬ',
            'category' => $ingredient->category,
            'common_allergy_label' => 'Spices',
            'show_in_onboarding' => true,
            'is_vegan' => true,
        ])
        ->assertRedirect(route('admin.ingredients.edit', $ingredient));

    $ingredient->refresh();
    expect($ingredient->name_en)->toBe('Berbere spice')
        ->and($ingredient->show_in_onboarding)->toBeTrue()
        ->and($ingredient->common_allergy_label)->toBe('Spices');
});
