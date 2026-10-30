import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Check,
    ChevronLeft,
    ChevronRight,
    Copy,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast } from 'sonner';
import { AdminPageHeader } from '@/components/admin/page-header';
import {
    type FoodSummaries,
    ProgramFoodPicker,
} from '@/components/admin/program-food-picker';
import InputError from '@/components/input-error';
import { memberApi } from '@/components/member/member-api';
import type { FoodSearchResult } from '@/components/member/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index, share, store, update } from '@/routes/admin/meal-programs';
import type {
    AdminMealProgramDay,
    AdminMealProgramDetail,
    AdminMealProgramMeal,
    MealTypeOption,
} from '@/types';

type Props = {
    program: AdminMealProgramDetail | null;
    foodSummaries: FoodSummaries;
    mealTypes: MealTypeOption[];
};

type ProgramForm = {
    name: string;
    name_am: string;
    description: string;
    description_am: string;
    image: File | null;
    highlights: string;
    highlights_am: string;
    price_etb: string;
    is_published: boolean;
    days: AdminMealProgramDay[];
};

type Step = 'details' | 'days' | 'review';

const STEPS: { id: Step; label: string }[] = [
    { id: 'details', label: 'Details' },
    { id: 'days', label: 'Days' },
    { id: 'review', label: 'Review' },
];

function move<T>(list: T[], index: number, dir: -1 | 1): T[] {
    const next = [...list];
    const target = index + dir;
    if (target < 0 || target >= next.length) {
        return list;
    }
    [next[index], next[target]] = [next[target], next[index]];
    return next;
}

function mealTypeLabel(mealTypes: MealTypeOption[], value: string): string {
    return mealTypes.find((type) => type.value === value)?.label ?? value;
}

function MealEditor({
    meal,
    mealTypes,
    foodSummaries,
    onChange,
    onRemove,
}: {
    meal: AdminMealProgramMeal;
    mealTypes: MealTypeOption[];
    foodSummaries: FoodSummaries;
    onChange: (meal: AdminMealProgramMeal) => void;
    onRemove: () => void;
}) {
    const [summaries, setSummaries] = useState<FoodSummaries>({});
    const [pickerOpen, setPickerOpen] = useState(false);

    const mergedSummaries = { ...foodSummaries, ...summaries };

    function setItems(items: AdminMealProgramMeal['items']) {
        onChange({ ...meal, items });
    }

    function addFoods(foods: FoodSearchResult[]) {
        setSummaries((map) => ({
            ...map,
            ...Object.fromEntries(foods.map((food) => [food.id, food])),
        }));
        setItems([
            ...meal.items,
            ...foods
                .filter(
                    (food) =>
                        !meal.items.some((item) => item.food_id === food.id),
                )
                .map((food) => ({ food_id: food.id, portion_note: null })),
        ]);
    }

    return (
        <div className="rounded-lg border">
            <div className="flex items-center gap-2 border-b px-3 py-2">
                <Select
                    value={meal.meal_type}
                    onValueChange={(value) =>
                        onChange({ ...meal, meal_type: value })
                    }
                >
                    <SelectTrigger className="h-8 w-40">
                        <SelectValue placeholder="Meal type" />
                    </SelectTrigger>
                    <SelectContent>
                        {mealTypes.map((type) => (
                            <SelectItem key={type.value} value={type.value}>
                                {type.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <div className="flex-1" />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={onRemove}
                    aria-label="Remove meal"
                >
                    <Trash2 className="size-4" />
                </Button>
            </div>
            <div className="space-y-2 p-3">
                {meal.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No foods yet — add some below.
                    </p>
                ) : (
                    <div className="divide-y">
                        {meal.items.map((item, itemIndex) => {
                            const food = mergedSummaries[item.food_id];
                            return (
                                <div
                                    key={`${item.food_id}-${itemIndex}`}
                                    className="flex items-center gap-2 py-2"
                                >
                                    <div className="flex shrink-0 flex-col">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-6"
                                            disabled={itemIndex === 0}
                                            onClick={() =>
                                                setItems(
                                                    move(
                                                        meal.items,
                                                        itemIndex,
                                                        -1,
                                                    ),
                                                )
                                            }
                                            aria-label={`Move ${food?.name_en ?? 'item'} up`}
                                        >
                                            <ArrowUp className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-6"
                                            disabled={
                                                itemIndex ===
                                                meal.items.length - 1
                                            }
                                            onClick={() =>
                                                setItems(
                                                    move(
                                                        meal.items,
                                                        itemIndex,
                                                        1,
                                                    ),
                                                )
                                            }
                                            aria-label={`Move ${food?.name_en ?? 'item'} down`}
                                        >
                                            <ArrowDown className="size-3.5" />
                                        </Button>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium">
                                            {food?.name_en ??
                                                `Food #${item.food_id}`}
                                        </p>
                                        {food !== undefined && (
                                            <p className="text-xs text-muted-foreground">
                                                {food.kcal_per_100g ?? '—'}{' '}
                                                kcal/100g
                                            </p>
                                        )}
                                    </div>
                                    <Input
                                        value={item.portion_note ?? ''}
                                        onChange={(e) =>
                                            setItems(
                                                meal.items.map((it, i) =>
                                                    i === itemIndex
                                                        ? {
                                                              ...it,
                                                              portion_note:
                                                                  e.target
                                                                      .value ===
                                                                  ''
                                                                      ? null
                                                                      : e.target
                                                                            .value,
                                                          }
                                                        : it,
                                                ),
                                            )
                                        }
                                        placeholder="Portion (e.g. 2 cups)"
                                        className="h-8 w-36 text-xs"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 shrink-0"
                                        onClick={() =>
                                            setItems(
                                                meal.items.filter(
                                                    (_, i) => i !== itemIndex,
                                                ),
                                            )
                                        }
                                        aria-label={`Remove ${food?.name_en ?? 'item'}`}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                )}

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setPickerOpen(true)}
                >
                    <Plus className="size-4" />
                    Add foods
                </Button>

                <ProgramFoodPicker
                    open={pickerOpen}
                    onOpenChange={setPickerOpen}
                    exclude={meal.items.map((item) => item.food_id)}
                    onAdd={addFoods}
                />
            </div>
        </div>
    );
}

function DayEditor({
    day,
    index,
    dayCount,
    mealTypes,
    foodSummaries,
    onChange,
    onMove,
    onDuplicate,
    onRemove,
}: {
    day: AdminMealProgramDay;
    index: number;
    dayCount: number;
    mealTypes: MealTypeOption[];
    foodSummaries: FoodSummaries;
    onChange: (index: number, day: AdminMealProgramDay) => void;
    onMove: (index: number, dir: -1 | 1) => void;
    onDuplicate: (index: number) => void;
    onRemove: (index: number) => void;
}) {
    function setMeal(mealIndex: number, meal: AdminMealProgramMeal) {
        onChange(index, {
            ...day,
            meals: day.meals.map((m, i) => (i === mealIndex ? meal : m)),
        });
    }

    function addMeal() {
        const used = new Set(day.meals.map((meal) => meal.meal_type));
        const next =
            mealTypes.find((type) => !used.has(type.value))?.value ??
            mealTypes[0]?.value ??
            'breakfast';
        onChange(index, {
            ...day,
            meals: [...day.meals, { meal_type: next, items: [] }],
        });
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2">
                <CardTitle className="text-base">Day {index + 1}</CardTitle>
                <div className="flex shrink-0 items-center">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={index === 0}
                        onClick={() => onMove(index, -1)}
                        aria-label="Move day up"
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={index === dayCount - 1}
                        onClick={() => onMove(index, 1)}
                        aria-label="Move day down"
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => onDuplicate(index)}
                        aria-label={`Duplicate day ${index + 1}`}
                    >
                        <Copy className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => onRemove(index)}
                        aria-label={`Remove day ${index + 1}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {day.meals.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No meals yet — add one below.
                    </p>
                )}
                {day.meals.map((meal, mealIndex) => (
                    <MealEditor
                        key={meal.id ?? `new-${mealIndex}`}
                        meal={meal}
                        mealTypes={mealTypes}
                        foodSummaries={foodSummaries}
                        onChange={(m) => setMeal(mealIndex, m)}
                        onRemove={() =>
                            onChange(index, {
                                ...day,
                                meals: day.meals.filter(
                                    (_, i) => i !== mealIndex,
                                ),
                            })
                        }
                    />
                ))}
                <Button type="button" variant="outline" onClick={addMeal}>
                    <Plus className="size-4" />
                    Add meal
                </Button>
            </CardContent>
        </Card>
    );
}

export default function AdminMealProgramEdit({
    program,
    foodSummaries,
    mealTypes,
}: Props) {
    const form = useForm<ProgramForm>({
        name: program?.name ?? '',
        name_am: program?.name_am ?? '',
        description: program?.description ?? '',
        description_am: program?.description_am ?? '',
        image: null,
        highlights: program?.highlights.join('\n') ?? '',
        highlights_am: program?.highlights_am.join('\n') ?? '',
        price_etb: String(program?.price_etb ?? 0),
        is_published: program?.published ?? false,
        days: program?.days ?? [],
    });
    const [step, setStep] = useState<Step>('details');
    const [imagePreview, setImagePreview] = useState<string | null>(
        program?.image_url ?? null,
    );
    const [shareUrl, setShareUrl] = useState(program?.share_url ?? null);
    const [sharing, setSharing] = useState(false);
    const [, copy] = useClipboard();

    const stepIndex = STEPS.findIndex((s) => s.id === step);

    const itemCount = form.data.days.reduce(
        (total, day) =>
            total + day.meals.reduce((sum, meal) => sum + meal.items.length, 0),
        0,
    );
    const mealCount = form.data.days.reduce(
        (total, day) => total + day.meals.length,
        0,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const lines = (text: string) =>
            text
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line !== '');
        form.transform((data) => ({
            ...data,
            price_etb: Number(data.price_etb) || 0,
            highlights: lines(data.highlights),
            highlights_am: lines(data.highlights_am),
        }));
        if (program === null) {
            form.post(store().url, { preserveScroll: true });
        } else {
            form.put(update(program.id).url, { preserveScroll: true });
        }
    }

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

    function setDay(index: number, day: AdminMealProgramDay) {
        form.setData(
            'days',
            form.data.days.map((d, i) => (i === index ? day : d)),
        );
    }

    function moveDay(index: number, dir: -1 | 1) {
        form.setData('days', move(form.data.days, index, dir));
    }

    function duplicateDay(index: number) {
        const source = form.data.days[index];
        form.setData('days', [
            ...form.data.days.slice(0, index + 1),
            {
                meals: source.meals.map((meal) => ({
                    meal_type: meal.meal_type,
                    items: meal.items.map((item) => ({ ...item })),
                })),
            },
            ...form.data.days.slice(index + 1),
        ]);
    }

    function removeDay(index: number) {
        form.setData(
            'days',
            form.data.days.filter((_, i) => i !== index),
        );
    }

    function addDay() {
        form.setData('days', [...form.data.days, { meals: [] }]);
    }

    function next() {
        if (step === 'details') {
            if (form.data.name.trim() === '') {
                toast.error('Give the program a name first');
                return;
            }
            setStep('days');
        } else if (step === 'days') {
            if (form.data.days.length === 0) {
                toast.error('Add at least one day');
                return;
            }
            if (
                form.data.days.some(
                    (day) =>
                        day.meals.length === 0 ||
                        day.meals.some((meal) => meal.items.length === 0),
                )
            ) {
                toast.error('Every day needs a meal with foods');
                return;
            }
            setStep('review');
        }
    }

    async function enableShare() {
        if (program === null || sharing) {
            return;
        }
        setSharing(true);
        try {
            const { share_url } = await memberApi<{ share_url: string | null }>(
                share(program.id).url,
                'POST',
            );
            setShareUrl(share_url);
        } catch {
            toast.error('Could not create share link');
        } finally {
            setSharing(false);
        }
    }

    async function copyLink() {
        if (shareUrl && (await copy(shareUrl))) {
            toast.success('Link copied');
        }
    }

    return (
        <>
            <Head
                title={
                    program === null
                        ? 'Admin — new meal program'
                        : `Admin — edit ${program.name}`
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <AdminPageHeader
                    title={
                        program === null
                            ? 'New meal program'
                            : `Edit meal program — ${program.name}`
                    }
                    description="Days in a program become a meal plan in the member's account"
                />

                <nav className="flex items-center gap-2">
                    {STEPS.map((s, i) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => {
                                if (i < stepIndex) {
                                    setStep(s.id);
                                }
                            }}
                            className={cn(
                                'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                                step === s.id
                                    ? 'border-lime-500 bg-lime-300/40 font-medium'
                                    : i < stepIndex
                                      ? 'border-lime-500/40 text-muted-foreground'
                                      : 'border-input text-muted-foreground',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-5 items-center justify-center rounded-full text-xs',
                                    i <= stepIndex
                                        ? 'bg-lime-400 text-lime-950'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {i < stepIndex ? (
                                    <Check className="size-3" />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            {s.label}
                        </button>
                    ))}
                </nav>

                <form onSubmit={submit} className="space-y-4">
                    {step === 'details' && (
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Program details
                                </CardTitle>
                                <CardDescription>
                                    Price 0 makes the shared link free to claim
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name_am">Name (አማርኛ)</Label>
                                    <Input
                                        id="name_am"
                                        value={form.data.name_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'name_am',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="የምግብ ፕሮግራም ስም"
                                    />
                                    <InputError message={form.errors.name_am} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="What this program is for"
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description_am">
                                        Description (አማርኛ)
                                    </Label>
                                    <Input
                                        id="description_am"
                                        value={form.data.description_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'description_am',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="ይህ የምግብ ፕሮግራም ለምንድን ነው"
                                    />
                                    <InputError
                                        message={form.errors.description_am}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="image">Cover image</Label>
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
                                            alt="Cover preview"
                                            className="aspect-video w-full max-w-sm rounded-lg border object-cover"
                                        />
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Shown as the hero on the shared
                                            sales page.
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="highlights">
                                        Highlights — one per line
                                    </Label>
                                    <textarea
                                        id="highlights"
                                        value={form.data.highlights}
                                        onChange={(e) =>
                                            form.setData(
                                                'highlights',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                        placeholder={
                                            '7 days of balanced meals\nFasting-friendly options'
                                        }
                                        className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                    />
                                    <InputError
                                        message={form.errors.highlights}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Bullet points shown in the "What's
                                        included" section of the sales page.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="highlights_am">
                                        Highlights (አማርኛ) — one per line
                                    </Label>
                                    <textarea
                                        id="highlights_am"
                                        value={form.data.highlights_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'highlights_am',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                        placeholder={
                                            '7 ቀናት የተመጣጠነ ምግብ\nየጾም-ተስማሚ አማራጮች'
                                        }
                                        className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                    />
                                    <InputError
                                        message={form.errors.highlights_am}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="price_etb">
                                        Price (ETB)
                                    </Label>
                                    <Input
                                        id="price_etb"
                                        type="number"
                                        min="0"
                                        step="1"
                                        value={form.data.price_etb}
                                        onChange={(e) =>
                                            form.setData(
                                                'price_etb',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.price_etb}
                                    />
                                    {Number(form.data.price_etb) > 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            Included in Pro — members claim it
                                            into their meal plans.
                                        </p>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            className="w-fit"
                                        >
                                            Free program
                                        </Badge>
                                    )}
                                </div>

                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="is_published"
                                        checked={form.data.is_published}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'is_published',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="is_published">
                                            Publish to catalog
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Listed publicly at /explore so
                                            members can browse and save it. A
                                            share link is created if needed.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {step === 'days' && (
                        <>
                            <div className="space-y-4">
                                {form.data.days.map((day, index) => (
                                    <DayEditor
                                        key={`day-${index}`}
                                        day={day}
                                        index={index}
                                        dayCount={form.data.days.length}
                                        mealTypes={mealTypes}
                                        foodSummaries={foodSummaries}
                                        onChange={setDay}
                                        onMove={moveDay}
                                        onDuplicate={duplicateDay}
                                        onRemove={removeDay}
                                    />
                                ))}
                            </div>

                            <InputError message={form.errors.days} />

                            <Button
                                type="button"
                                variant="outline"
                                onClick={addDay}
                            >
                                <Plus className="size-4" />
                                Add day
                            </Button>
                        </>
                    )}

                    {step === 'review' && (
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {form.data.name || 'Untitled program'}
                                </CardTitle>
                                <CardDescription>
                                    {form.data.days.length}{' '}
                                    {form.data.days.length === 1
                                        ? 'day'
                                        : 'days'}{' '}
                                    · {mealCount} meal
                                    {mealCount === 1 ? '' : 's'} · {itemCount}{' '}
                                    foods ·{' '}
                                    {Number(form.data.price_etb) > 0
                                        ? `${form.data.price_etb} ETB`
                                        : 'Free'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {imagePreview !== null && (
                                    <img
                                        src={imagePreview}
                                        alt="Cover preview"
                                        className="aspect-video w-full rounded-lg border object-cover"
                                    />
                                )}
                                {form.data.description !== '' && (
                                    <p className="text-sm text-muted-foreground">
                                        {form.data.description}
                                    </p>
                                )}
                                <div className="space-y-3">
                                    {form.data.days.map((day, index) => (
                                        <div
                                            key={`review-${index}`}
                                            className="rounded-lg border p-3"
                                        >
                                            <p className="text-sm font-medium">
                                                Day {index + 1}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {day.meals
                                                    .map(
                                                        (meal) =>
                                                            `${mealTypeLabel(mealTypes, meal.meal_type)} (${meal.items.length})`,
                                                    )
                                                    .join(' · ')}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                {program !== null && (
                                    <div className="flex items-center gap-2 border-t pt-4">
                                        {shareUrl ? (
                                            <>
                                                <Input
                                                    readOnly
                                                    value={shareUrl}
                                                    onFocus={(e) =>
                                                        e.target.select()
                                                    }
                                                    className="max-w-sm text-xs"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={copyLink}
                                                >
                                                    <Copy className="size-4" />
                                                    Copy share link
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={sharing}
                                                onClick={enableShare}
                                            >
                                                {sharing
                                                    ? 'Creating…'
                                                    : 'Create share link'}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center gap-2">
                        {stepIndex > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep(STEPS[stepIndex - 1].id)}
                            >
                                <ChevronLeft className="size-4" />
                                Back
                            </Button>
                        )}
                        {step !== 'review' ? (
                            <Button
                                type="button"
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                                onClick={next}
                            >
                                Next
                                <ChevronRight className="size-4" />
                            </Button>
                        ) : (
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                            >
                                {program === null
                                    ? 'Create meal program'
                                    : 'Save changes'}
                            </Button>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AdminMealProgramEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Meal programs', href: index() },
    ],
};
