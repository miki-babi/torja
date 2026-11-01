import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/lib/i18n';

/**
 * One published program as it appears across Explore and the wizard
 * "Discover programs" sections — the payload App\Support\ProgramCatalog
 * builds.
 */
export type CatalogProgram = {
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

/**
 * Singular/plural translation keys for a program's meta count — workout
 * programs count workouts, meal programs count days.
 */
export const PROGRAM_COUNT_KEYS = {
    exercise: {
        one: 'programs.workout_count',
        many: 'programs.workouts_count',
    },
    meal: { one: 'programs.day_count', many: 'programs.days_count' },
} as const;

/**
 * A "Discover programs" teaser: a horizontally scrolling row of program
 * cards (image, name, workout/day count, Saved badge) that open each
 * program's shared detail page. Cards snap like the home banner
 * carousel; the section renders nothing when no programs are published.
 */
export function DiscoverPrograms({
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
        <section>
            <h2 className="text-sm font-medium text-muted-foreground">
                {title}
            </h2>
            <div className="-mx-4 mt-2 flex snap-x snap-mandatory scrollbar-none gap-3 overflow-x-auto px-4">
                {programs.map((program) => (
                    <ProgramCard key={program.key} program={program} />
                ))}
            </div>
        </section>
    );
}

function ProgramCard({ program }: { program: CatalogProgram }) {
    const { t } = useTranslation();
    const countKeys = PROGRAM_COUNT_KEYS[program.kind];

    return (
        <Link
            href={program.view_url}
            prefetch
            className="block w-32 shrink-0 snap-start"
        >
            <span className="relative block aspect-square overflow-hidden rounded-2xl border border-[#2E2E2E] bg-[#1F1F1F]">
                {program.image_url !== null && (
                    <img
                        src={program.image_url}
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 size-full object-cover"
                    />
                )}
                {program.claimed && (
                    <Badge className="absolute top-1.5 right-1.5 bg-[#AFFA01] font-bold text-black">
                        <Check className="size-3 stroke-[3]" />
                        {t('programs.saved')}
                    </Badge>
                )}
            </span>
            <span className="mt-1.5 block truncate text-sm font-bold text-[#F5F5F5]">
                {program.name}
            </span>
            <span className="block text-xs text-[#8E8E93]">
                {t(program.meta_count === 1 ? countKeys.one : countKeys.many, {
                    count: program.meta_count,
                })}
            </span>
        </Link>
    );
}
