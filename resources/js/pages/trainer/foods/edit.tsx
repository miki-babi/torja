import { Head, useForm } from '@inertiajs/react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/trainer';
import { store, update } from '@/routes/trainer/foods';

type Food = {
    id: number;
    name_en: string;
    name_am: string | null;
    category: string;
    kcal_per_100g: number | null;
    protein_g: number | null;
    carbs_g: number | null;
    fat_g: number | null;
    fibre_g: number | null;
    image_url: string | null;
};

type Props = {
    food: Food | null;
};

export default function TrainerFoodEdit({ food }: Props) {
    const form = useForm<{
        name_en: string;
        name_am: string;
        category: string;
        kcal_per_100g: string;
        protein_g: string;
        carbs_g: string;
        fat_g: string;
        fibre_g: string;
        image: File | null;
    }>({
        name_en: food?.name_en ?? '',
        name_am: food?.name_am ?? '',
        category: food?.category ?? '',
        kcal_per_100g: food?.kcal_per_100g?.toString() ?? '',
        protein_g: food?.protein_g?.toString() ?? '',
        carbs_g: food?.carbs_g?.toString() ?? '',
        fat_g: food?.fat_g?.toString() ?? '',
        fibre_g: food?.fibre_g?.toString() ?? '',
        image: null,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(
        food?.image_url ?? null,
    );

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true };
        if (food === null) {
            form.post(store().url, options);
        } else {
            form.put(update(food.id).url, options);
        }
    }

    function pickImage(event: FormEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0] ?? null;
        form.setData('image', file);
        setImagePreview((previous) => {
            if (previous?.startsWith('blob:')) {
                URL.revokeObjectURL(previous);
            }
            return file ? URL.createObjectURL(file) : food?.image_url ?? null;
        });
    }

    return (
        <>
            <Head title={food === null ? 'New food' : food.name_en} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={food === null ? 'New food' : 'Edit food'}
                    description="Your own food — pickable when you build meal programs. Duplicate names are allowed."
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Food details
                        </CardTitle>
                        <CardDescription>
                            Macros are per 100g — all optional except the name.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name_en">Name</Label>
                                <Input
                                    id="name_en"
                                    value={form.data.name_en}
                                    onChange={(e) =>
                                        form.setData(
                                            'name_en',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="e.g. Shiro wot"
                                />
                                <InputError message={form.errors.name_en} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="name_am">
                                        Name (Amharic, optional)
                                    </Label>
                                    <Input
                                        id="name_am"
                                        value={form.data.name_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'name_am',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.name_am}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category">
                                        Category (optional)
                                    </Label>
                                    <Input
                                        id="category"
                                        value={form.data.category}
                                        onChange={(e) =>
                                            form.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. stew, grain, drink"
                                    />
                                    <InputError
                                        message={form.errors.category}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="kcal_per_100g">
                                        Calories / 100g
                                    </Label>
                                    <Input
                                        id="kcal_per_100g"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={form.data.kcal_per_100g}
                                        onChange={(e) =>
                                            form.setData(
                                                'kcal_per_100g',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.kcal_per_100g}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="protein_g">
                                        Protein (g) / 100g
                                    </Label>
                                    <Input
                                        id="protein_g"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={form.data.protein_g}
                                        onChange={(e) =>
                                            form.setData(
                                                'protein_g',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.protein_g}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="carbs_g">
                                        Carbs (g) / 100g
                                    </Label>
                                    <Input
                                        id="carbs_g"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={form.data.carbs_g}
                                        onChange={(e) =>
                                            form.setData(
                                                'carbs_g',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.carbs_g}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="fat_g">
                                        Fat (g) / 100g
                                    </Label>
                                    <Input
                                        id="fat_g"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={form.data.fat_g}
                                        onChange={(e) =>
                                            form.setData(
                                                'fat_g',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.fat_g}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="fibre_g">
                                        Fibre (g) / 100g
                                    </Label>
                                    <Input
                                        id="fibre_g"
                                        type="number"
                                        min="0"
                                        step="0.1"
                                        value={form.data.fibre_g}
                                        onChange={(e) =>
                                            form.setData(
                                                'fibre_g',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.fibre_g}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">
                                    Photo (optional)
                                </Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={pickImage}
                                />
                                {imagePreview && (
                                    <img
                                        src={imagePreview}
                                        alt=""
                                        className="mt-1 h-24 w-24 rounded object-cover"
                                    />
                                )}
                                <InputError message={form.errors.image} />
                            </div>

                            <div className="flex justify-end">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && (
                                        <Spinner className="size-4" />
                                    )}
                                    {food === null
                                        ? 'Create food'
                                        : 'Save changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TrainerFoodEdit.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
