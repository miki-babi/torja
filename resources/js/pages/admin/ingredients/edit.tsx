import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
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
import { index, store, update } from '@/routes/admin/ingredients';
import type { AdminIngredient, AdminOption } from '@/types';

type Props = {
    ingredient: AdminIngredient | null;
    categories: string[];
    allergens: AdminOption[];
};

type IngredientForm = {
    name_en: string;
    name_am: string;
    category: string;
    allergens: string[];
    common_allergy_label: string;
    added_for: string;
    show_in_onboarding: boolean;
    is_vegan: boolean;
};

function toggle(list: string[], value: string): string[] {
    return list.includes(value)
        ? list.filter((item) => item !== value)
        : [...list, value];
}

export default function AdminIngredientEdit({
    ingredient,
    categories,
    allergens,
}: Props) {
    const isCreate = ingredient === null;
    const form = useForm<IngredientForm>({
        name_en: ingredient?.name_en ?? '',
        name_am: ingredient?.name_am ?? '',
        category: ingredient?.category ?? '',
        allergens: ingredient?.allergens ?? [],
        common_allergy_label: ingredient?.common_allergy_label ?? '',
        added_for: '',
        show_in_onboarding: ingredient?.show_in_onboarding ?? false,
        is_vegan: ingredient?.is_vegan ?? false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (isCreate) {
            form.post(store().url, { preserveScroll: true });
        } else {
            form.put(update(ingredient.id).url, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head
                title={
                    isCreate
                        ? 'Admin — new ingredient'
                        : `Admin — edit ${ingredient.name_en}`
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={
                        isCreate
                            ? 'New ingredient'
                            : `Edit ingredient — ${ingredient.name_en}`
                    }
                    description={
                        isCreate
                            ? 'Add an ingredient to the dataset manually'
                            : ingredient.category
                    }
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Ingredient data
                        </CardTitle>
                        <CardDescription>
                            Shown to users during onboarding and in allergen
                            matching
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
                                    required
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
                                <Label htmlFor="common_allergy_label">
                                    Common allergy label
                                </Label>
                                <Input
                                    id="common_allergy_label"
                                    value={form.data.common_allergy_label}
                                    onChange={(e) =>
                                        form.setData(
                                            'common_allergy_label',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Dairy, Gluten, Nuts"
                                />
                                <InputError
                                    message={form.errors.common_allergy_label}
                                />
                            </div>

                            {isCreate && (
                                <div className="grid gap-2">
                                    <Label htmlFor="added_for">
                                        Added for (optional)
                                    </Label>
                                    <Input
                                        id="added_for"
                                        value={form.data.added_for}
                                        onChange={(e) =>
                                            form.setData(
                                                'added_for',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Why this ingredient was added"
                                    />
                                    <InputError
                                        message={form.errors.added_for}
                                    />
                                </div>
                            )}

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="show_in_onboarding"
                                    checked={form.data.show_in_onboarding}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'show_in_onboarding',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="show_in_onboarding">
                                    Show in onboarding
                                </Label>
                                <InputError
                                    message={form.errors.show_in_onboarding}
                                />
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_vegan"
                                    checked={form.data.is_vegan}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_vegan',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_vegan">Vegan</Label>
                                <InputError message={form.errors.is_vegan} />
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {isCreate
                                        ? 'Create ingredient'
                                        : 'Save changes'}
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

AdminIngredientEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Ingredients', href: index() },
    ],
};
