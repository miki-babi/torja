import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Plus, X } from 'lucide-react';
import { useState } from 'react';
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
import { store as storePlan } from '@/routes/member/meal-plans';

type PlanItem = {
    name: string;
    portion_note?: string | null;
};

type PlanMeal = {
    meal_type: string;
    items: PlanItem[];
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
 * each has an "Add food" field where the member types the item name.
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
 * Food entry inside a meal card: a focused overlay with a single text
 * field — the member types the item name and taps Add (or Enter).
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

    const trimmedQuery = query.trim();

    function addTypedItem() {
        if (trimmedQuery === '') {
            return;
        }
        onAdd({ name: trimmedQuery });
        setQuery('');
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
                <Input
                    autoFocus
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            addTypedItem();
                        }
                    }}
                    placeholder={t('member.meal_builder.custom_placeholder')}
                    className="flex-1"
                />
                <Button
                    className="h-10 shrink-0 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-3.5 font-bold text-black hover:brightness-105"
                    onClick={addTypedItem}
                    disabled={trimmedQuery === ''}
                >
                    <Plus className="size-4" />
                    {t('member.meal_builder.add')}
                </Button>
            </div>
        </div>
    );
}
