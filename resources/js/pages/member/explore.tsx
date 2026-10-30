import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Check,
    Dumbbell,
    LayoutGrid,
    Package,
    Pill,
    Search,
    UtensilsCrossed,
    Weight,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { MobileShell } from '@/components/member/mobile-shell';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { index as exploreIndex } from '@/routes/explore';

type CatalogProgram = {
    key: string;
    kind: 'exercise' | 'meal';
    name: string;
    description: string | null;
    image_url: string | null;
    meta_count: number;
    view_url: string;
    save_url: string;
    claimed: boolean;
    member_url: string | null;
};

type Chip = 'all' | 'workouts' | 'nutrition' | 'equipment' | 'supplements';

type ShopProduct = {
    id: number;
    category: 'equipment' | 'supplement';
    name: string;
    description: string | null;
    image_url: string | null;
    price_etb: number;
    show_url: string;
};

function exploreQuery(
    chip: Chip,
    q: string,
): Record<string, string | undefined> {
    return {
        ...(chip === 'all' ? {} : { [chip]: '' }),
        q: q === '' ? undefined : q,
    };
}

const COUNT_KEYS = {
    exercise: {
        one: 'programs.workout_count',
        many: 'programs.workouts_count',
    },
    meal: { one: 'programs.day_count', many: 'programs.days_count' },
} as const;

function ProgramRow({ program }: { program: CatalogProgram }) {
    const { t } = useTranslation();
    const countKeys = COUNT_KEYS[program.kind];

    return (
        <Link
            href={program.view_url}
            prefetch
            className="flex items-center gap-3.5 py-3 transition-colors hover:bg-[#1F1F1F]/40"
        >
            {program.image_url !== null ? (
                <img
                    src={program.image_url}
                    alt=""
                    loading="lazy"
                    className="size-12 shrink-0 rounded-xl border border-[#2E2E2E] object-cover"
                />
            ) : (
                <div className="size-12 shrink-0 rounded-xl border border-[#2E2E2E] bg-[#1F1F1F]" />
            )}
            <div className="min-w-0 flex-1">
                <p className="block truncate text-sm font-bold text-[#F5F5F5]">
                    {program.name}
                </p>
                <p className="text-xs text-[#8E8E93]">
                    {t(
                        program.meta_count === 1
                            ? countKeys.one
                            : countKeys.many,
                        { count: program.meta_count },
                    )}
                </p>
            </div>
            {program.claimed && (
                <Badge className="shrink-0 bg-[#AFFA01] text-black font-bold">
                    <Check className="size-3 stroke-[3]" />
                    {t('programs.saved')}
                </Badge>
            )}
        </Link>
    );
}

function Section({
    title,
    programs,
}: {
    title: string;
    programs: CatalogProgram[];
}) {
    if (programs.length === 0) {
        return null;
    }

    return (
        <section className="mt-6">
            <h2 className="text-sm font-medium text-muted-foreground">
                {title}
            </h2>
            <div className="mt-1 divide-y">
                {programs.map((program) => (
                    <ProgramRow key={program.key} program={program} />
                ))}
            </div>
        </section>
    );
}

function ProductRow({ product }: { product: ShopProduct }) {
    const { t } = useTranslation();

    return (
        <Link
            href={product.show_url}
            prefetch
            className="flex items-center gap-3.5 py-3 transition-colors hover:bg-[#1F1F1F]/40"
        >
            {product.image_url !== null ? (
                <img
                    src={product.image_url}
                    alt=""
                    loading="lazy"
                    className="size-12 shrink-0 rounded-xl border border-[#2E2E2E] object-cover"
                />
            ) : (
                <div className="flex size-12 shrink-0 items-center justify-center rounded-xl border border-[#2E2E2E] bg-[#1F1F1F]">
                    <Package className="size-5 text-[#AFFA01]" />
                </div>
            )}
            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold text-[#F5F5F5]">
                    {product.name}
                </p>
                <p className="truncate text-xs text-[#8E8E93]">
                    {product.description ??
                        `${product.price_etb.toLocaleString()} ETB`}
                </p>
            </div>
            <span className="shrink-0 text-xs font-bold text-[#AFFA01]">
                {product.price_etb.toLocaleString()} ETB
            </span>
        </Link>
    );
}

export default function MemberExplore({
    exercisePrograms,
    mealPrograms,
    products,
    filters,
}: {
    exercisePrograms: CatalogProgram[];
    mealPrograms: CatalogProgram[];
    products: ShopProduct[];
    filters: { q: string; chip: Chip };
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const isGuest = auth.user === null;

    const [q, setQ] = useState(filters.q);
    const [chip, setChip] = useState<Chip>(filters.chip);
    const isFirstSearch = useRef(true);

    // Keep the chip in sync when navigating back/forward through history.
    useEffect(() => {
        setChip(filters.chip);
    }, [filters.chip]);

    useEffect(() => {
        if (isFirstSearch.current) {
            isFirstSearch.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                exploreIndex.url({ query: exploreQuery(chip, q) }),
                {},
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['exercisePrograms', 'mealPrograms', 'products', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [q]);

    function selectChip(next: Chip) {
        if (next === chip) {
            return;
        }

        setChip(next);
        router.get(
            exploreIndex.url({ query: exploreQuery(next, q) }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['filters'],
            },
        );
    }

    const allPrograms = [...exercisePrograms, ...mealPrograms];
    const savedPrograms = allPrograms.filter((program) => program.claimed);
    const chipPrograms =
        chip === 'all'
            ? allPrograms
            : chip === 'workouts'
              ? exercisePrograms
              : chip === 'nutrition'
                ? mealPrograms
                : [];
    const isShop = chip === 'equipment' || chip === 'supplements';
    const category = chip === 'equipment' ? 'equipment' : 'supplement';
    const shopProducts = isShop
        ? products.filter((product) => product.category === category)
        : chip === 'all'
          ? products
          : [];

    const chips = [
        {
            value: 'all',
            icon: LayoutGrid,
            label: t('programs.chip_all'),
        },
        {
            value: 'workouts',
            icon: Dumbbell,
            label: t('programs.chip_workouts'),
        },
        {
            value: 'nutrition',
            icon: UtensilsCrossed,
            label: t('programs.chip_nutrition'),
        },
        {
            value: 'equipment',
            icon: Weight,
            label: t('programs.chip_equipment'),
        },
        {
            value: 'supplements',
            icon: Pill,
            label: t('programs.chip_supplements'),
        },
    ] as const;

    return (
        <MobileShell>
            <Head title={t('programs.title')} />

            <h1 className="text-xl font-semibold tracking-tight">
                {t('programs.title')}
            </h1>

            <div className="relative mt-4">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={q}
                    onChange={(event) => setQ(event.target.value)}
                    placeholder={t('programs.search_placeholder')}
                    className="pl-9"
                />
            </div>

            {!isGuest && (
                <Section
                    title={t('programs.saved_section')}
                    programs={savedPrograms}
                />
            )}

            <div className="mt-5 flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                {chips.map(({ value, icon: Icon, label }) => (
                    <button
                        key={value}
                        type="button"
                        onClick={() => selectChip(value)}
                        className={cn(
                            'flex shrink-0 items-center gap-1.5 rounded-full border px-3.5 py-2 text-xs font-bold uppercase tracking-wider transition-all',
                            chip === value
                                ? 'border-transparent bg-gradient-to-r from-[#C5FF4D] to-[#AFFA01] text-black shadow-md'
                                : 'border-[#2E2E2E] bg-[#1F1F1F] text-[#8E8E93] hover:text-[#F5F5F5]',
                        )}
                    >
                        <Icon className="size-4" />
                        {label}
                    </button>
                ))}
            </div>

            {isShop ? (
                shopProducts.length === 0 ? (
                    <p className="mt-10 text-center text-sm text-[#8E8E93]">
                        {t('programs.coming_soon')}
                    </p>
                ) : (
                    <div className="mt-4 divide-y divide-[#2E2E2E]">
                        {shopProducts.map((product) => (
                            <ProductRow
                                key={product.id}
                                product={product}
                            />
                        ))}
                    </div>
                )
            ) : chipPrograms.length === 0 && shopProducts.length === 0 ? (
                <p className="mt-10 text-center text-sm text-[#8E8E93]">
                    {t('programs.empty')}
                </p>
            ) : (
                <>
                    {chipPrograms.length > 0 && (
                        <div className="mt-4 divide-y divide-[#2E2E2E]">
                            {chipPrograms.map((program) => (
                                <ProgramRow
                                    key={program.key}
                                    program={program}
                                />
                            ))}
                        </div>
                    )}
                    {shopProducts.length > 0 && (
                        <div className="mt-4 divide-y divide-[#2E2E2E] border-t border-[#2E2E2E]">
                            {shopProducts.map((product) => (
                                <ProductRow
                                    key={product.id}
                                    product={product}
                                />
                            ))}
                        </div>
                    )}
                </>
            )}

        </MobileShell>
    );
}
