import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import {
    DiscoverPrograms,
    type CatalogProgram,
} from '@/components/member/discover-programs';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { mealTypeLabel } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { meal } from '@/routes/member';
import { search as foodSearch } from '@/routes/member/foods';
import { store as storePlan } from '@/routes/member/meal-plans';

type PlanItem = {
    food_id: number | null;
    name: string;
    image_url?: string | null;
    portion_note?: string | null;
};

type PlanMeal = {
    meal_type: string;
    items: PlanItem[];
};

type FoodResult = {
    id: number;
    name_en: string;
    image_url: string | null;
    kcal_per_100g: number | null;
    protein_g: number | null;
    carbs_g: number | null;
    fat_g: number | null;
};

function seedDay(mealTypes: string[]): PlanMeal[] {
    return mealTypes.map((type) => ({ meal_type: type, items: [] }));
}

function dayHasItems(meals: PlanMeal[]): boolean {
    return meals.some((m) => m.items.length > 0);
}

/**
 * Step-by-step meal plan builder: name → one day at a time (every meal
 * slot is already on the card — just add foods) → "Add day" grows the
 * plan → review → save. Empty meal slots are dropped on save.
 */
export default function MealPlanBuilder({
    mealTypes,
    maxDays,
    mealPrograms,
}: {
    mealTypes: string[];
    maxDays: number;
    mealPrograms: CatalogProgram[];
}) {
    const { t } = useTranslation();
    const [step, setStep] = useState(0);
    const [name, setName] = useState('');
    const [days, setDays] = useState<PlanMeal[][]>(() => [seedDay(mealTypes)]);
    const [busy, setBusy] = useState(false);

    const reviewStep = days.length + 1;
    const onDayStep = step >= 1 && step <= days.length;
    const currentMeals = onDayStep ? days[step - 1] : [];

    function updateDay(index: number, meals: PlanMeal[]) {
        setDays((prev) => prev.map((d, i) => (i === index ? meals : d)));
    }

    function addDay() {
        setDays((prev) => [...prev, seedDay(mealTypes)]);
        setStep((s) => s + 1);
    }

    async function save() {
        if (busy) {
            return;
        }

        const payloadDays = days
            .map((meals) => ({
                meals: meals
                    .filter((m) => m.items.length > 0)
                    .map((m) => ({
                        meal_type: m.meal_type,
                        items: m.items.map((i) => ({
                            food_id: i.food_id,
                            name: i.name,
                            portion_note: i.portion_note ?? null,
                        })),
                    })),
            }))
            .filter((d) => d.meals.length > 0);

        if (payloadDays.length === 0) {
            return;
        }

        setBusy(true);
        try {
            await memberApi(storePlan().url, 'POST', {
                name: name.trim(),
                days: payloadDays,
            });
            router.visit(meal().url);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Could not save meal plan',
            );
            setBusy(false);
        }
    }

    return (
        <MobileShell>
            <Head title={t('member.meal_builder.title')} />
            <h1 className="text-xl font-semibold tracking-tight">
                {t('member.meal_builder.title')}
            </h1>

            <div className="mt-4 space-y-5">
                {(onDayStep || step === reviewStep) && (
                    <p className="text-[11px] font-bold tracking-wider text-[#AFFA01] uppercase">
                        {onDayStep
                            ? t('member.meal_builder.day_title', {
                                  day: step,
                              })
                            : t('member.meal_builder.review_title')}
                    </p>
                )}

                {step === 0 && (
                    <div className="space-y-4">
                        <p className="text-sm text-[#8E8E93]">
                            {t('member.meal_builder.subtitle')}
                        </p>
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-[#F5F5F5]">
                                {t('member.meal_builder.name_label')}
                            </label>
                            <Input
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder={t(
                                    'member.meal_builder.name_placeholder',
                                )}
                            />
                        </div>
                        <DiscoverPrograms
                            title={t('programs.discover_meals')}
                            programs={mealPrograms}
                        />
                    </div>
                )}

                {onDayStep && (
                    <DayStep
                        mealTypes={mealTypes}
                        meals={currentMeals}
                        onChange={(meals) => updateDay(step - 1, meals)}
                    />
                )}

                {step === reviewStep && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-bold text-[#F5F5F5]">
                            {name}
                        </h2>
                        {days.map((meals, d) => (
                            <div key={d} className="space-y-1">
                                <p className="text-xs font-bold tracking-wider text-[#AFFA01] uppercase">
                                    {t('member.meal_builder.day_title', {
                                        day: d + 1,
                                    })}
                                </p>
                                {meals
                                    .filter((m) => m.items.length > 0)
                                    .map((m) => (
                                        <p
                                            key={m.meal_type}
                                            className="text-sm text-[#F5F5F5]"
                                        >
                                            <span className="font-semibold">
                                                {mealTypeLabel(m.meal_type)}
                                            </span>
                                            <span className="text-[#8E8E93]">
                                                {' '}
                                                —{' '}
                                                {m.items
                                                    .map((i) => i.name)
                                                    .join(', ')}
                                            </span>
                                        </p>
                                    ))}
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex items-center gap-2 pt-1 pb-4">
                    {step > 0 && (
                        <button
                            type="button"
                            onClick={() => setStep((s) => s - 1)}
                            className="flex items-center gap-1 text-xs font-semibold text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                        >
                            <ArrowLeft className="size-3.5" />
                            {t('member.builder.back')}
                        </button>
                    )}

                    {onDayStep && (
                        <>
                            <button
                                type="button"
                                onClick={() => setStep(reviewStep)}
                                disabled={!dayHasItems(currentMeals)}
                                className="ml-auto text-xs font-semibold text-[#8E8E93] transition-colors hover:text-[#F5F5F5] disabled:opacity-40"
                            >
                                {t('member.meal_builder.review_title')}
                            </button>
                            {days.length < maxDays && (
                                <Button
                                    className="h-11 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 font-bold text-black hover:brightness-105"
                                    onClick={addDay}
                                    disabled={!dayHasItems(currentMeals)}
                                >
                                    <Plus className="size-4" />
                                    {t('member.meal_builder.add_day')}
                                </Button>
                            )}
                        </>
                    )}

                    {step === 0 && (
                        <Button
                            className="ml-auto h-11 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 font-bold text-black hover:brightness-105"
                            onClick={() => setStep(1)}
                            disabled={name.trim() === ''}
                        >
                            {t('member.builder.continue')}
                            <ChevronRight className="size-4" />
                        </Button>
                    )}

                    {step === reviewStep && (
                        <Button
                            className="ml-auto h-11 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 font-bold text-black hover:brightness-105"
                            onClick={() => void save()}
                            disabled={busy}
                        >
                            {t('member.meal_builder.save')}
                            <ChevronRight className="size-4" />
                        </Button>
                    )}
                </div>
            </div>
        </MobileShell>
    );
}

/**
 * One day in the wizard: every MealType slot is pre-seeded on the card;
 * each has an "Add food" picker (catalog search + custom item).
 */
function DayStep({
    mealTypes,
    meals,
    onChange,
}: {
    mealTypes: string[];
    meals: PlanMeal[];
    onChange: (meals: PlanMeal[]) => void;
}) {
    const { t } = useTranslation();
    const [addingMeal, setAddingMeal] = useState<string | null>(null);

    function setItems(type: string, items: PlanItem[]) {
        onChange(
            meals.map((m) => (m.meal_type === type ? { ...m, items } : m)),
        );
    }

    return (
        <div className="space-y-4">
            {mealTypes.map((type) => {
                const meal =
                    meals.find((m) => m.meal_type === type) ??
                    ({ meal_type: type, items: [] } as PlanMeal);

                return (
                    <div
                        key={type}
                        className="space-y-2 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] p-4"
                    >
                        <p className="text-sm font-bold text-[#F5F5F5]">
                            {mealTypeLabel(type)}
                        </p>

                        {meal.items.map((item, j) => (
                            <div
                                key={j}
                                className="flex items-center gap-2.5 rounded-xl border border-[#2E2E2E] bg-[#141414] px-3 py-2"
                            >
                                {item.image_url !== undefined &&
                                    item.image_url !== null && (
                                        <img
                                            src={item.image_url}
                                            alt=""
                                            className="size-8 shrink-0 rounded-lg object-cover"
                                        />
                                    )}
                                <span className="min-w-0 flex-1 truncate text-sm text-[#F5F5F5]">
                                    {item.name}
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setItems(
                                            type,
                                            meal.items.filter(
                                                (_, k) => k !== j,
                                            ),
                                        )
                                    }
                                    aria-label="Remove item"
                                    className="shrink-0 text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </div>
                        ))}

                        {addingMeal === type ? (
                            <ItemPicker
                                onAdd={(item) =>
                                    setItems(type, [...meal.items, item])
                                }
                                onDone={() => setAddingMeal(null)}
                            />
                        ) : (
                            <button
                                type="button"
                                onClick={() => setAddingMeal(type)}
                                className="flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]"
                            >
                                <Plus className="size-3.5" />
                                {t('member.meal_builder.add_food')}
                            </button>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

/**
 * Food picker inside a meal card: a single field that live-searches the
 * catalog on every keystroke — tap a result to add it; when nothing
 * matches, an Add button appends the typed text as an item.
 */
function ItemPicker({
    onAdd,
    onDone,
}: {
    onAdd: (item: PlanItem) => void;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<FoodResult[]>([]);
    const latest = useRef<symbol>(Symbol('init'));

    useEffect(() => {
        const trimmed = query.trim();
        if (trimmed === '') {
            setResults([]);
            return;
        }

        const token = Symbol('search');
        latest.current = token;

        const timer = setTimeout(async () => {
            try {
                const res = await fetch(
                    `${foodSearch().url}?q=${encodeURIComponent(trimmed)}`,
                );
                const { foods } = (await res.json()) as {
                    foods: FoodResult[];
                };
                if (latest.current === token) {
                    setResults(foods);
                }
            } catch {
                if (latest.current === token) {
                    toast.error('Could not search foods');
                }
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    const trimmedQuery = query.trim();

    function addTypedItem() {
        if (trimmedQuery === '') {
            return;
        }
        onAdd({ food_id: null, name: trimmedQuery });
        setQuery('');
        setResults([]);
    }

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-background">
            <div className="flex items-center gap-2 px-4 pt-6 pb-3">
                <button
                    type="button"
                    onClick={onDone}
                    aria-label={t('member.meal_builder.done')}
                    className="flex size-9 shrink-0 items-center justify-center rounded-full border border-[#2E2E2E] bg-[#1F1F1F] text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                >
                    <ArrowLeft className="size-4" />
                </button>
                <div className="relative flex-1">
                    <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8E8E93]" />
                    <Input
                        autoFocus
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                addTypedItem();
                            }
                        }}
                        placeholder={t('member.meal_builder.search_foods')}
                        className="pl-9"
                    />
                </div>
                <Button
                    className="h-10 shrink-0 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-3.5 font-bold text-black hover:brightness-105"
                    onClick={addTypedItem}
                    disabled={trimmedQuery === ''}
                >
                    <Plus className="size-4" />
                    {t('member.meal_builder.add')}
                </Button>
            </div>

            <div className="flex-1 space-y-2 overflow-y-auto px-4 pb-6">
                {results.map((food) => (
                    <button
                        key={food.id}
                        type="button"
                        onClick={() =>
                            onAdd({
                                food_id: food.id,
                                name: food.name_en,
                                image_url: food.image_url,
                            })
                        }
                        className="flex w-full items-center gap-3 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] p-3.5 text-left transition-colors hover:border-[#AFFA01]/40"
                    >
                        {food.image_url !== null ? (
                            <span className="shrink-0 overflow-hidden rounded-xl border border-[#2E2E2E] bg-white">
                                <img
                                    src={food.image_url}
                                    alt=""
                                    className="size-14 object-cover"
                                />
                            </span>
                        ) : (
                            <span className="size-14 shrink-0 rounded-xl border border-[#2E2E2E] bg-[#141414]" />
                        )}
                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-[#F5F5F5]">
                                {food.name_en}
                            </span>
                            <span className="mt-0.5 block truncate text-xs text-[#8E8E93]">
                                {[
                                    food.kcal_per_100g !== null
                                        ? `${food.kcal_per_100g} kcal`
                                        : null,
                                    food.protein_g !== null
                                        ? `P ${food.protein_g}g`
                                        : null,
                                    food.carbs_g !== null
                                        ? `C ${food.carbs_g}g`
                                        : null,
                                    food.fat_g !== null
                                        ? `F ${food.fat_g}g`
                                        : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </span>
                        <Plus className="size-4 shrink-0 text-[#AFFA01]" />
                    </button>
                ))}
            </div>
        </div>
    );
}
