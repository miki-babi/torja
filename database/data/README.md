# app-food-database.json — Schema

One JSON object with 6 top-level keys.

| Key            | Type         | Description                                                           |
| -------------- | ------------ | --------------------------------------------------------------------- |
| version        | string       | Dataset version, e.g. "1.0.0 (2026-10-24)"                            |
| sources        | string[]     | Data sources (main: EPHI & FAO Ethiopian Food Composition Table 2025) |
| goal_tag_rules | object       | Thresholds used to compute goal_tags (see below)                      |
| ingredients    | Ingredient[] | 92 canonical ingredients (use for allergy/diet onboarding)            |
| foods          | Food[]       | 705 foods (697 EPHI + 8 plan-only foods, ids 10001–10008)             |
| meal_plans     | MealPlan[]   | 2 plans: fat_loss_7day, muscle_gain_7day                              |

## Ingredient

| Field                | Type         | Notes                                             |
| -------------------- | ------------ | ------------------------------------------------- |
| id                   | string (PK)  | Slug, e.g. "teff", "niter_kibbeh"                 |
| name_en / name_am    | string       | English / Amharic (transliterated)                |
| category             | string       | grain, legume, meat, dairy, vegetable, spice, ... |
| allergens            | string[]     | Allergen codes (list below)                       |
| common_allergy_label | string\|null | Friendly label for onboarding                     |
| show_in_onboarding   | bool         | true = show in "Any allergies?" picker            |
| is_vegan             | bool         |                                                   |
| used_by_foods        | int          | How many foods contain it                         |

## Food

| Field                                             | Type                           | Notes                                                                                                 |
| ------------------------------------------------- | ------------------------------ | ----------------------------------------------------------------------------------------------------- |
| id                                                | int (PK)                       | EPHI ids 1–697; plan-only 10001+                                                                      |
| name_en / name_am                                 | string                         |                                                                                                       |
| category                                          | string                         | EPHI food group                                                                                       |
| kcal_per_100g, protein_g, carbs_g, fat_g, fibre_g | number\|null                   | Per 100 g edible portion. null = unknown (never estimated)                                            |
| ingredients                                       | string[]                       | FK → Ingredient.id                                                                                    |
| allergens                                         | string[]                       | Union of ingredient allergens                                                                         |
| diet_flags                                        | object                         | is_fasting_friendly, fish_ok_fasting, vegetarian, vegan, contains_meat (all bool)                     |
| goal_tags                                         | object                         | { fat_loss: bool, muscle_gain: bool, basis: string }                                                  |
| meal_types                                        | string[]                       | Subset of breakfast, lunch, dinner, snack. Empty = raw ingredient/drink/spice (not offered as a meal) |
| used_in_plans                                     | object[]                       | { plan, day, meal_type, raw_text } — where the food appears in meal_plans                             |
| swaps                                             | {food_id:int, reason:string}[] | 3–5 alternatives (FK → Food.id). Empty if no meal_types                                               |
| source                                            | string                         | Provenance                                                                                            |
| review_needed                                     | bool                           | true = data inferred/corrected; verify before showing as fact                                         |

## MealPlan

| Field              | Type         | Notes                                                                            |
| ------------------ | ------------ | -------------------------------------------------------------------------------- |
| id                 | string (PK)  | fat_loss_7day / muscle_gain_7day                                                 |
| plan_name          | string       |                                                                                  |
| goal               | string       | fat_loss / muscle_gain                                                           |
| tags               | object       | all_allergens_in_plan[], vegan_days[], fasting_friendly_meals_count, total_meals |
| meal_totals_stated | object\|null | kcal/protein as stated in the source PDF (not verified)                          |
| days               | Day[]        | { day:int, meals: Meal[] }                                                       |

Meal: { meal_type: breakfast\|snack\|lunch\|post_workout\|dinner\|before_bed, items: Item[], swap_food_ids: {food_id, why}[] }
Item: { food_id (FK → Food.id), raw_text, portion_note, match_confidence: exact\|close\|none }

## goal_tags rules (per 100 g)

- fat_loss: kcal ≤ 200 AND protein ≥ 6 g per 100 kcal
- muscle_gain: protein ≥ 15 g OR (kcal ≥ 250 AND protein ≥ 6 g)
- Only foods with meal_types are tagged; null macros → both false.

## Allergen codes

gluten_cereals, crustaceans, eggs, fish, peanuts, soy, milk_dairy, tree_nuts, celery, mustard, sesame, sulphites, lupin, molluscs (EU/Codex 14 major allergens).

## App logic

1. Onboarding: list ingredients where show_in_onboarding = true; user picks allergies/exclusions.
2. Exclude any food whose ingredients or allergens intersect the user's picks.
3. Filter by diet_flags (vegan, fasting) and goal_tags for the user's goal.
4. Swaps: show food.swaps (or meal.swap_food_ids in a plan), skipping any swap excluded by step 2/3.
5. Portions: macros are per 100 g; multiply by grams served.

Relational version: app-db/*.csv (foods, ingredients, food_ingredients, food_swaps, meal_plan_items) — see app-db/README.md.

---

# exercises.json — Schema

One JSON array of 1,324 exercise objects (source: https://github.com/hasaneyldrm/exercises-dataset, MIT).

| Field                | Type     | Notes                                                                                                                                      |
| -------------------- | -------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| id                   | string   | Dataset id, e.g. "0001"                                                                                                                    |
| name                 | string   | English name (some rows contain mojibake, e.g. "45в°" for "45°")                                                                           |
| category             | string   | Mirrors body_part                                                                                                                          |
| body_part            | string   | waist, upper legs, back, lower legs, chest, upper arms, cardio, shoulders, lower arms, neck                                                |
| equipment            | string   | body weight, barbell, dumbbell, cable, ... (29 values)                                                                                     |
| target               | string   | Primary target muscle, e.g. "pectorals" (19 values)                                                                                        |
| muscle_group         | string   | Functional group, e.g. "hip flexors" (29 values)                                                                                           |
| secondary_muscles    | string[] |                                                                                                                                            |
| instructions         | string   | English paragraph                                                                                                                          |
| instruction_steps    | string[] |                                                                                                                                            |
| media_id             | string   | Media filename suffix                                                                                                                      |
| image                | string   | "images/{id}-{media_id}.jpg" relative to dataset root                                                                                      |
| gif_url              | string   | "videos/{id}-{media_id}.gif" relative to dataset root                                                                                      |
| created_at           | string   | ISO 8601                                                                                                                                   |
| tags                 | object   | exercise_type, level, location, movement_pattern, compound, isolation, full_body, women_home_friendly, goal_tags{fat_loss,muscle_gain}     |
| review_needed        | bool     | true = heuristic tags need review; reasons in review_reasons                                                                               |
| review_reasons       | string[] |                                                                                                                                            |
| name_am              | string   | English name transliterated into Amharic script, original kept in brackets (e.g. "ሲት አፕ (sit up)") — see App\Support\AmharicTransliterator |
| instructions_am      | string   |                                                                                                                                            |
| instruction_steps_am | string[] | Aligned 1:1 with instruction_steps                                                                                                         |
| translation_status   | string   | transliterated (name_am) \| machine_unreviewed (instructions) \| reviewed \| missing                                                       |

## Media

GIF demos and JPG thumbnails are committed to git under
storage/app/public/exercises/{images,videos} and are served at
/storage/exercises/... — just run `php artisan storage:link` per environment.

If the dataset is ever re-imported from source, refresh the media with:

    php artisan exercises:import-media "<path-to>/exercise-dataset"

## App logic

- Seeded by ExerciseDatabaseSeeder into the exercises table (upsert by slug).
- slug = Str::slug(name); duplicate names get "-{id}" appended (6 names).
- exercise_playlist_items.exercise_id / workout_sets.exercise_id store slugs.
- App\Support\ExerciseCatalog is the read API (cached); legacy ids from the old
  hardcoded catalog resolve via ExerciseCatalog::LEGACY_SLUGS.
