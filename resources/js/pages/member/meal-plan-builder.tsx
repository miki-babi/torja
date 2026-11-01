import { Head, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, Plus, Search, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import { mealTypeLabel } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
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
};

/**
 * Step-by-step meal plan builder: name + day count → one step per day
 * (add meals and foods from the catalog or custom items) → review →
 * save. Saved plans render on /meal.
 */
export default function MealPlanBuilder({
    mealTypes,
    maxDays,
}: {
    mealTypes: string[];
    maxDays: number;
}) {
    const { t } = useTranslation();
    const [step, setStep] = useState(0);
    const [name, setName] = useState('');
    const [dayCount, setDayCount] = useState(1);
    const [days, setDays] = useState<PlanMeal[][]>([[]]);
    const [busy, setBusy] = useState(false);

    const totalSteps = dayCount + 2;

    function setDayCountAndDays(count: number) {
        setDayCount(count);
        setDays((prev) =>
            Array.from(
                { length: count },
                (_, i) => prev[i] ?? ([] as PlanMeal[]),
            ),
        );
    }

    function updateDay(index: number, meals: PlanMeal[]) {
        setDays((prev) => prev.map((d, i) => (i === index ? meals : d)));
    }

    function dayReady(meals: PlanMeal[]): boolean {
        return meals.length > 0 && meals.every((m) => m.items.length > 0);
    }

    const canNext =
        step === 0
            ? name.trim() !== '' && dayCount >= 1
            : step <= dayCount
              ? dayReady(days[step - 1])
              : true;

    async function save() {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            await memberApi(storePlan().url, 'POST', {
                name: name.trim(),
                days: days.map((meals) => ({
                    meals: meals.map((m) => ({
                        meal_type: m.meal_type,
                        items: m.items.map((i) => ({
                            food_id: i.food_id,
                            name: i.name,
                            portion_note: i.portion_note ?? null,
                        })),
                    })),
                })),
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

    function next() {
        if (step >= totalSteps - 1) {
            void save();
        } else {
            setStep((s) => s + 1);
        }
    }

    return (
        <MobileShell>
            <Head title={t('member.meal_builder.title')} />
            <h1 className="text-xl font-semibold tracking-tight">
                {t('member.meal_builder.title')}
            </h1>

            <div className="mt-4 space-y-5">
                <p className="text-[11px] font-bold tracking-wider text-[#AFFA01] uppercase">
                    {t('member.builder.step', {
                        current: step + 1,
                        total: totalSteps,
                    })}
                </p>

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
                        <div>
                            <p className="mb-1.5 text-sm font-medium text-[#F5F5F5]">
                                {t('member.meal_builder.days_label')}
                            </p>
                            <div className="grid grid-cols-4 gap-2">
                                {Array.from(
                                    { length: maxDays },
                                    (_, i) => i + 1,
                                ).map((n) => (
                                    <button
                                        key={n}
                                        type="button"
                                        onClick={() => setDayCountAndDays(n)}
                                        className={cn(
                                            'flex h-14 flex-col items-center justify-center rounded-xl border text-lg font-bold',
                                            dayCount === n
                                                ? 'border-lime-500 bg-lime-300/40 text-[#F5F5F5]'
                                                : 'border-input text-muted-foreground',
                                        )}
                                    >
                                        {n}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                )}

                {step >= 1 && step <= dayCount && (
                    <DayStep
                        day={step}
                        mealTypes={mealTypes}
                        meals={days[step - 1]}
                        onChange={(meals) => updateDay(step - 1, meals)}
                    />
                )}

                {step === totalSteps - 1 && (
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
                                {meals.map((m, i) => (
                                    <p
                                        key={i}
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
                    <Button
                        className="ml-auto h-11 rounded-xl bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] px-5 font-bold text-black hover:brightness-105"
                        onClick={next}
                        disabled={!canNext || busy}
                    >
                        {step === totalSteps - 1
                            ? t('member.meal_builder.save')
                            : t('member.builder.continue')}
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            </div>
        </MobileShell>
    );
}

/**
 * One day in the wizard: meal cards for each added MealType, an "Add
 * meal" grid of the types not yet used, and a per-meal food picker
 * (catalog search + custom item).
 */
function DayStep({
    day,
    mealTypes,
    meals,
    onChange,
}: {
    day: number;
    mealTypes: string[];
    meals: PlanMeal[];
    onChange: (meals: PlanMeal[]) => void;
}) {
    const { t } = useTranslation();
    const [addingMeal, setAddingMeal] = useState<number | null>(null);

    const usedTypes = new Set(meals.map((m) => m.meal_type));
    const remaining = mealTypes.filter((type) => !usedTypes.has(type));

    function addMeal(type: string) {
        onChange([...meals, { meal_type: type, items: [] }]);
        setAddingMeal(meals.length);
    }

    function setItems(index: number, items: PlanItem[]) {
        onChange(meals.map((m, i) => (i === index ? { ...m, items } : m)));
    }

    return (
        <div className="space-y-4">
            <h2 className="text-lg font-bold text-[#F5F5F5]">
                {t('member.meal_builder.day_title', { day })}
            </h2>

            {meals.map((m, i) => (
                <div
                    key={m.meal_type}
                    className="space-y-2 rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F] p-4"
                >
                    <div className="flex items-center justify-between">
                        <p className="text-sm font-bold text-[#F5F5F5]">
                            {mealTypeLabel(m.meal_type)}
                        </p>
                        <button
                            type="button"
                            onClick={() =>
                                onChange(meals.filter((_, j) => j !== i))
                            }
                            aria-label="Remove meal"
                            className="text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                        >
                            <X className="size-4" />
                        </button>
                    </div>

                    {m.items.map((item, j) => (
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
                                        i,
                                        m.items.filter((_, k) => k !== j),
                                    )
                                }
                                aria-label="Remove item"
                                className="shrink-0 text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    ))}

                    {addingMeal === i ? (
                        <ItemPicker
                            onAdd={(item) => {
                                setItems(i, [...m.items, item]);
                            }}
                            onDone={() => setAddingMeal(null)}
                        />
                    ) : (
                        <button
                            type="button"
                            onClick={() => setAddingMeal(i)}
                            className="flex items-center gap-1.5 text-xs font-semibold text-[#AFFA01]"
                        >
                            <Plus className="size-3.5" />
                            {t('member.meal_builder.add_food')}
                        </button>
                    )}
                </div>
            ))}

            {remaining.length > 0 && (
                <div>
                    <p className="mb-1.5 text-xs font-semibold text-[#8E8E93]">
                        {t('member.meal_builder.add_meal')}
                    </p>
                    <div className="grid grid-cols-3 gap-2">
                        {remaining.map((type) => (
                            <button
                                key={type}
                                type="button"
                                onClick={() => addMeal(type)}
                                className="flex items-center justify-center rounded-xl border border-input px-2 py-3 text-xs font-medium text-muted-foreground transition-colors hover:border-lime-500 hover:text-[#F5F5F5]"
                            >
                                {mealTypeLabel(type)}
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Food picker inside a meal card: catalog search results (tap to add)
 * plus a free-text custom item field.
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
    const [custom, setCustom] = useState('');
    const [searching, setSearching] = useState(false);

    async function search() {
        setSearching(true);
        try {
            const res = await fetch(
                `${foodSearch().url}?q=${encodeURIComponent(query)}`,
            );
            const { foods } = (await res.json()) as { foods: FoodResult[] };
            setResults(foods);
        } catch {
            toast.error('Could not search foods');
        } finally {
            setSearching(false);
        }
    }

    function addCustom() {
        const name = custom.trim();
        if (name === '') {
            return;
        }
        onAdd({ food_id: null, name });
        setCustom('');
    }

    return (
        <div className="space-y-2 border-t border-[#2E2E2E] pt-2">
            <div className="relative">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#8E8E93]" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            void search();
                        }
                    }}
                    placeholder={t('member.meal_builder.search_foods')}
                    className="pl-9"
                />
            </div>

            <div className="max-h-48 space-y-1 overflow-y-auto">
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
                        className="flex w-full items-center gap-2.5 rounded-xl border border-[#2E2E2E] bg-[#141414] px-3 py-2 text-left transition-colors hover:border-[#AFFA01]/40"
                    >
                        {food.image_url !== null && (
                            <img
                                src={food.image_url}
                                alt=""
                                className="size-8 shrink-0 rounded-lg object-cover"
                            />
                        )}
                        <span className="min-w-0 flex-1 truncate text-sm text-[#F5F5F5]">
                            {food.name_en}
                        </span>
                        {food.kcal_per_100g !== null && (
                            <span className="shrink-0 text-xs text-[#8E8E93]">
                                {food.kcal_per_100g} kcal
                            </span>
                        )}
                    </button>
                ))}
                {searching && <p className="py-1 text-xs text-[#8E8E93]">…</p>}
            </div>

            <div className="flex items-center gap-2">
                <Input
                    value={custom}
                    onChange={(e) => setCustom(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            addCustom();
                        }
                    }}
                    placeholder={t('member.meal_builder.custom_placeholder')}
                    className="h-9 text-xs"
                />
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={addCustom}
                    disabled={custom.trim() === ''}
                >
                    {t('member.meal_builder.custom_item')}
                </Button>
            </div>

            <button
                type="button"
                onClick={onDone}
                className="text-xs font-semibold text-[#8E8E93] transition-colors hover:text-[#F5F5F5]"
            >
                {t('member.meal_builder.done')}
            </button>
        </div>
    );
}
