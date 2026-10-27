import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes/admin';
import { index, store, update } from '@/routes/admin/foods';
import type { AdminFood, AdminOption, IngredientOption } from '@/types';

type Props = {
    food: AdminFood | null;
    categories: string[];
    mealTypes: AdminOption[];
    allergens: AdminOption[];
    ingredientOptions: IngredientOption[];
};

type FoodForm = {
    name_en: string;
    name_am: string;
    category: string;
    kcal_per_100g: string;
    protein_g: string;
    carbs_g: string;
    fat_g: string;
    fibre_g: string;
    image: File | null;
    meal_types: string[];
    allergens: string[];
    is_fasting_friendly: boolean;
    fish_ok_fasting: boolean;
    is_vegetarian: boolean;
    is_vegan: boolean;
    contains_meat: boolean;
    goal_fat_loss: boolean;
    goal_muscle_gain: boolean;
    ingredient_ids: number[];
    review_needed: boolean;
    data_fix_note: string;
};

const DIET_FLAGS = [
    ['is_fasting_friendly', 'Fasting friendly'],
    ['fish_ok_fasting', 'Fish OK on fasting days'],
    ['is_vegetarian', 'Vegetarian'],
    ['is_vegan', 'Vegan'],
    ['contains_meat', 'Contains meat'],
    ['goal_fat_loss', 'Fat-loss goal'],
    ['goal_muscle_gain', 'Muscle-gain goal'],
] as const;

function toggle(list: string[], value: string): string[] {
    return list.includes(value)
        ? list.filter((item) => item !== value)
        : [...list, value];
}

export default function AdminFoodEdit({
    food,
    categories,
    mealTypes,
    allergens,
    ingredientOptions,
}: Props) {
    const form = useForm<FoodForm>({
        name_en: food?.name_en ?? '',
        name_am: food?.name_am ?? '',
        category: food?.category ?? '',
        kcal_per_100g: food?.kcal_per_100g ?? '',
        protein_g: food?.protein_g ?? '',
        carbs_g: food?.carbs_g ?? '',
        fat_g: food?.fat_g ?? '',
        fibre_g: food?.fibre_g ?? '',
        image: null,
        meal_types: food?.meal_types ?? [],
        allergens: food?.allergens ?? [],
        is_fasting_friendly: food?.is_fasting_friendly ?? false,
        fish_ok_fasting: food?.fish_ok_fasting ?? false,
        is_vegetarian: food?.is_vegetarian ?? false,
        is_vegan: food?.is_vegan ?? false,
        contains_meat: food?.contains_meat ?? false,
        goal_fat_loss: food?.goal_fat_loss ?? false,
        goal_muscle_gain: food?.goal_muscle_gain ?? false,
        ingredient_ids: food?.ingredient_ids ?? [],
        review_needed: food?.review_needed ?? false,
        data_fix_note: food?.data_fix_note ?? '',
    });
    const [imagePreview, setImagePreview] = useState<string | null>(
        food?.image_url ?? null,
    );
    const [ingredientQuery, setIngredientQuery] = useState('');

    const isCreate = food === null;

    function pickImage(event: FormEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0] ?? null;
        form.setData('image', file);
        setImagePreview((previous) => {
            if (previous?.startsWith('blob:')) {
                URL.revokeObjectURL(previous);
            }
            return file ? URL.createObjectURL(file) : null;
        });
    }

    function toggleIngredient(id: number) {
        form.setData(
            'ingredient_ids',
            form.data.ingredient_ids.includes(id)
                ? form.data.ingredient_ids.filter((item) => item !== id)
                : [...form.data.ingredient_ids, id],
        );
    }

    const visibleIngredients = ingredientOptions.filter((option) => {
        const query = ingredientQuery.trim().toLowerCase();
        return (
            query === '' ||
            option.name_en.toLowerCase().includes(query) ||
            (option.name_am ?? '').includes(ingredientQuery.trim())
        );
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (isCreate) {
            form.post(store().url, { preserveScroll: true });
        } else {
            form.put(update(food.id).url, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head
                title={
                    isCreate
                        ? 'Admin — new food'
                        : `Admin — edit ${food.name_en}`
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={
                        isCreate ? 'New food' : `Edit food — ${food.name_en}`
                    }
                    description={
                        isCreate
                            ? 'Add a meal to the dataset manually'
                            : food.category
                    }
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">Food data</CardTitle>
                        <CardDescription>
                            Macros are per 100 g; flags drive dietary exclusions
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">Name (English)</Label>
                                <Input
                                    id="name_en"
                                    value={form.data.name_en}
                                    onChange={(e) =>
                                        form.setData('name_en', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={form.errors.name_en} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_am">Name (Amharic)</Label>
                                <Input
                                    id="name_am"
                                    value={form.data.name_am}
                                    onChange={(e) =>
                                        form.setData('name_am', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.name_am} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category">Category</Label>
                                <Select
                                    value={form.data.category}
                                    onValueChange={(value) =>
                                        form.setData('category', value)
                                    }
                                >
                                    <SelectTrigger id="category">
                                        <SelectValue placeholder="Pick a category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem
                                                key={category}
                                                value={category}
                                            >
                                                {category}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.category} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                {(
                                    [
                                        ['kcal_per_100g', 'kcal / 100 g'],
                                        ['protein_g', 'Protein (g)'],
                                        ['carbs_g', 'Carbs (g)'],
                                        ['fat_g', 'Fat (g)'],
                                        ['fibre_g', 'Fibre (g)'],
                                    ] as const
                                ).map(([field, label]) => (
                                    <div key={field} className="grid gap-2">
                                        <Label htmlFor={field}>{label}</Label>
                                        <Input
                                            id={field}
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            value={form.data[field]}
                                            onChange={(e) =>
                                                form.setData(
                                                    field,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors[field]}
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">Image</Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={pickImage}
                                />
                                <InputError message={form.errors.image} />
                                {imagePreview !== null ? (
                                    <img
                                        src={imagePreview}
                                        alt="Food preview"
                                        className="aspect-video w-full max-w-sm rounded-lg border object-cover"
                                    />
                                ) : (
                                    <p className="text-xs text-muted-foreground">
                                        Shown next to the food in meal plans and
                                        the food search.
                                    </p>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label>Meal types</Label>
                                <div className="flex flex-wrap gap-3">
                                    {mealTypes.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex items-center gap-1.5 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.data.meal_types.includes(
                                                    option.value,
                                                )}
                                                onCheckedChange={() =>
                                                    form.setData(
                                                        'meal_types',
                                                        toggle(
                                                            form.data
                                                                .meal_types,
                                                            option.value,
                                                        ),
                                                    )
                                                }
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={form.errors.meal_types} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Allergens</Label>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {allergens.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex items-center gap-1.5 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.data.allergens.includes(
                                                    option.value,
                                                )}
                                                onCheckedChange={() =>
                                                    form.setData(
                                                        'allergens',
                                                        toggle(
                                                            form.data.allergens,
                                                            option.value,
                                                        ),
                                                    )
                                                }
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={form.errors.allergens} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Diet &amp; goal flags</Label>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {DIET_FLAGS.map(([field, label]) => (
                                        <label
                                            key={field}
                                            className="flex items-center gap-1.5 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.data[field]}
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        field,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label>Ingredients</Label>
                                <Input
                                    value={ingredientQuery}
                                    onChange={(e) =>
                                        setIngredientQuery(e.target.value)
                                    }
                                    placeholder="Filter ingredients…"
                                />
                                <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border p-2">
                                    {visibleIngredients.map((option) => (
                                        <label
                                            key={option.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <Checkbox
                                                checked={form.data.ingredient_ids.includes(
                                                    option.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleIngredient(option.id)
                                                }
                                            />
                                            {option.name_en}
                                            {option.name_am !== null && (
                                                <span className="text-muted-foreground">
                                                    {option.name_am}
                                                </span>
                                            )}
                                        </label>
                                    ))}
                                </div>
                                <InputError
                                    message={form.errors.ingredient_ids}
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="review_needed"
                                    checked={form.data.review_needed}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'review_needed',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="review_needed">
                                    Needs data review
                                </Label>
                                <InputError
                                    message={form.errors.review_needed}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="data_fix_note">
                                    Data fix note
                                </Label>
                                <Input
                                    id="data_fix_note"
                                    value={form.data.data_fix_note}
                                    onChange={(e) =>
                                        form.setData(
                                            'data_fix_note',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="What was corrected and why"
                                />
                                <InputError
                                    message={form.errors.data_fix_note}
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {isCreate ? 'Create food' : 'Save changes'}
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={index()}>Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminFoodEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Foods', href: index() },
    ],
};
