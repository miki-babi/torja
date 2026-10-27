<?php

use App\Models\Food;
use App\Models\MealProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function mealProgramAdmin(): User
{
    return User::factory()->admin()->create();
}

test('non-admin users cannot manage meal programs', function () {
    $program = MealProgram::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.meal-programs.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.meal-programs.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.meal-programs.update', $program), [])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.meal-programs.destroy', $program))->assertForbidden();
    $this->actingAs($user)->post(route('admin.meal-programs.share', $program))->assertForbidden();
    $this->actingAs($user)->post(route('admin.meal-programs.publish', $program))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.meal-programs.unpublish', $program))->assertForbidden();
});

test('admins can list meal programs with counts', function () {
    MealProgram::factory()->count(2)->create();

    $this->actingAs(mealProgramAdmin())
        ->get(route('admin.meal-programs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/meal-programs/index')
            ->has('programs.data', 2));
});

test('admins can create a meal program with nested days and meals', function () {
    $injera = Food::factory()->create(['name_en' => 'Injera']);
    $shiro = Food::factory()->create(['name_en' => 'Shiro']);

    $this->actingAs(mealProgramAdmin())
        ->post(route('admin.meal-programs.store'), [
            'name' => 'Ethiopian Basics',
            'description' => 'Two day starter',
            'price_etb' => 250,
            'days' => [
                [
                    'meals' => [
                        [
                            'meal_type' => 'breakfast',
                            'items' => [
                                ['food_id' => $injera->id, 'portion_note' => '2 pieces'],
                            ],
                        ],
                        [
                            'meal_type' => 'lunch',
                            'items' => [
                                ['food_id' => $shiro->id],
                                ['food_id' => $injera->id],
                            ],
                        ],
                    ],
                ],
                [
                    'meals' => [
                        [
                            'meal_type' => 'dinner',
                            'items' => [
                                ['food_id' => $injera->id],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $program = MealProgram::sole();
    expect($program->name)->toBe('Ethiopian Basics')
        ->and($program->price_etb)->toBe(250)
        ->and($program->meals()->count())->toBe(3)
        ->and($program->meals()->where('day', 1)->count())->toBe(2)
        ->and($program->meals()->where('day', 2)->where('meal_type', 'dinner')->sole()
            ->items()->where('food_id', $injera->id)->exists())->toBeTrue();
});

test('creating a meal program rejects unknown foods', function () {
    $this->actingAs(mealProgramAdmin())
        ->post(route('admin.meal-programs.store'), [
            'name' => 'Bad Program',
            'price_etb' => 0,
            'days' => [
                [
                    'meals' => [
                        [
                            'meal_type' => 'breakfast',
                            'items' => [['food_id' => 999999]],
                        ],
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors('days.0.meals.0.items.0.food_id');
});

test('admins can update a meal program and sync its day tree', function () {
    $injera = Food::factory()->create();
    $teff = Food::factory()->create();

    $program = MealProgram::factory()->create();
    $breakfast = $program->meals()->create(['day' => 1, 'meal_type' => 'breakfast', 'position' => 0]);
    $breakfast->items()->create(['food_id' => $injera->id, 'position' => 0]);
    $removed = $program->meals()->create(['day' => 2, 'meal_type' => 'lunch', 'position' => 0]);

    $this->actingAs(mealProgramAdmin())
        ->put(route('admin.meal-programs.update', $program), [
            'name' => 'Renamed',
            'description' => null,
            'price_etb' => 0,
            'days' => [
                [
                    'meals' => [
                        [
                            'id' => $breakfast->id,
                            'meal_type' => 'breakfast',
                            'items' => [
                                ['food_id' => $teff->id, 'portion_note' => '1 cup'],
                                ['food_id' => $injera->id],
                            ],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-programs.edit', $program))
        ->assertSessionHasNoErrors();

    $program->refresh();
    expect($program->name)->toBe('Renamed')
        ->and($program->meals()->count())->toBe(1)
        ->and($program->meals()->where('id', $removed->id)->exists())->toBeFalse()
        ->and($breakfast->refresh()->items()->pluck('food_id')->all())->toBe([$teff->id, $injera->id])
        ->and($breakfast->items()->first()->portion_note)->toBe('1 cup');
});

test('admins can set a cover image and sales highlights', function () {
    Storage::fake('public');
    $injera = Food::factory()->create();

    $program = MealProgram::factory()->create();

    $this->actingAs(mealProgramAdmin())
        ->put(route('admin.meal-programs.update', $program), [
            'name' => 'With Cover',
            'name_am' => 'ምሽራ ስም',
            'price_etb' => 100,
            'image' => UploadedFile::fake()->image('hero.jpg'),
            'highlights' => ['Lose weight fast', 'Seven guided days'],
            'highlights_am' => ['በፍጥነት ይቀንሱ'],
            'days' => [
                [
                    'meals' => [
                        [
                            'meal_type' => 'lunch',
                            'items' => [['food_id' => $injera->id]],
                        ],
                    ],
                ],
            ],
        ])
        ->assertRedirect(route('admin.meal-programs.edit', $program))
        ->assertSessionHasNoErrors();

    $program->refresh();
    expect($program->image_path)->toStartWith('meal-programs/')
        ->and($program->name_am)->toBe('ምሽራ ስም')
        ->and($program->highlights)->toBe(['Lose weight fast', 'Seven guided days'])
        ->and($program->highlights_am)->toBe(['በፍጥነት ይቀንሱ'])
        ->and($program->image_url)->toContain('meal-programs/');

    Storage::disk('public')->assertExists($program->image_path);
});

test('a new cover image replaces the old file', function () {
    Storage::fake('public');
    Food::factory()->create();

    $program = MealProgram::factory()->create([
        'image_path' => 'meal-programs/old.jpg',
    ]);
    Storage::disk('public')->put('meal-programs/old.jpg', 'old');

    $this->actingAs(mealProgramAdmin())
        ->put(route('admin.meal-programs.update', $program), [
            'name' => $program->name,
            'price_etb' => 0,
            'image' => UploadedFile::fake()->image('new.jpg'),
            'days' => [],
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('meal-programs/old.jpg');
    expect($program->refresh()->image_path)->not->toBe('meal-programs/old.jpg');
});

test('admins can delete a meal program', function () {
    $program = MealProgram::factory()->create();

    $this->actingAs(mealProgramAdmin())
        ->delete(route('admin.meal-programs.destroy', $program))
        ->assertRedirect(route('admin.meal-programs.index'));

    expect(MealProgram::count())->toBe(0);
});

test('share mints a stable token and unshare clears it', function () {
    $program = MealProgram::factory()->unshared()->create();

    $this->actingAs(mealProgramAdmin());

    $url = $this->postJson(route('admin.meal-programs.share', $program))
        ->assertOk()
        ->json('share_url');

    $this->get($url)->assertRedirectContains('shared/meal-programs/');
    expect($program->refresh()->share_token)->not->toBeNull();

    $this->postJson(route('admin.meal-programs.share', $program))
        ->assertOk()
        ->assertJsonPath('share_url', $url);

    $this->deleteJson(route('admin.meal-programs.unshare', $program))
        ->assertOk()
        ->assertJsonPath('share_url', null);

    expect($program->refresh()->share_token)->toBeNull();
});

test('the edit page includes the day tree and food summaries', function () {
    $injera = Food::factory()->create(['name_en' => 'Injera']);

    $program = MealProgram::factory()->create();
    $meal = $program->meals()->create(['day' => 1, 'meal_type' => 'lunch', 'position' => 0]);
    $meal->items()->create(['food_id' => $injera->id, 'portion_note' => '2 pieces', 'position' => 0]);

    $this->actingAs(mealProgramAdmin())
        ->get(route('admin.meal-programs.edit', $program))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/meal-programs/edit')
            ->where('program.id', $program->id)
            ->has('program.image_url')
            ->has('program.highlights')
            ->has('program.days', 1)
            ->has('program.days.0.meals', 1)
            ->where('program.days.0.meals.0.meal_type', 'lunch')
            ->where('program.days.0.meals.0.items.0.food_id', $injera->id)
            ->where('program.days.0.meals.0.items.0.portion_note', '2 pieces')
            ->has("foodSummaries.{$injera->id}.name_en")
            ->has('mealTypes'));
});

test('admins can publish and unpublish a meal program', function () {
    $program = MealProgram::factory()->unshared()->create();

    $this->actingAs(mealProgramAdmin())
        ->postJson(route('admin.meal-programs.publish', $program))
        ->assertOk()
        ->assertJsonPath('published', true);

    expect($program->refresh()->isPublished())->toBeTrue()
        ->and($program->share_token)->not->toBeNull();

    $this->actingAs(mealProgramAdmin())
        ->deleteJson(route('admin.meal-programs.unpublish', $program))
        ->assertOk()
        ->assertJsonPath('published', false);

    expect($program->refresh()->isPublished())->toBeFalse()
        ->and($program->share_token)->not->toBeNull();
});

test('the update form can publish and unpublish a meal program', function () {
    $program = MealProgram::factory()->unshared()->create();

    $this->actingAs(mealProgramAdmin())
        ->put(route('admin.meal-programs.update', $program), [
            'name' => 'Listed Meal Program',
            'price_etb' => 0,
            'is_published' => true,
        ])
        ->assertRedirect();

    expect($program->refresh()->isPublished())->toBeTrue()
        ->and($program->share_token)->not->toBeNull();

    $this->actingAs(mealProgramAdmin())
        ->put(route('admin.meal-programs.update', $program), [
            'name' => 'Listed Meal Program',
            'price_etb' => 0,
            'is_published' => false,
        ])
        ->assertRedirect();

    expect($program->refresh()->isPublished())->toBeFalse();
});
