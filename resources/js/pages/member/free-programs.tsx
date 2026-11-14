import { Head, Link } from '@inertiajs/react';
import { Dumbbell, Gift, UtensilsCrossed } from 'lucide-react';
import { MobileShell } from '@/components/member/mobile-shell';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useTranslation } from '@/lib/i18n';

type FreeItem = {
    id: number;
    name: string;
    description: string | null;
    image_url: string | null;
    items_count: number;
    url: string;
};

type CoachSection = {
    name: string;
    avatar: string | null;
    exercise: FreeItem[];
    meal: FreeItem[];
};

type Props = {
    coaches: CoachSection[];
};

function ProgramCard({
    item,
    countLabel,
    icon,
}: {
    item: FreeItem;
    countLabel: string;
    icon: React.ReactNode;
}) {
    return (
        <Link
            href={item.url}
            className="block overflow-hidden rounded-2xl border border-[#2E2E2E] bg-[#1A1A1A] transition-colors hover:border-[#AFFA01]/40"
        >
            {item.image_url !== null && (
                <img
                    src={item.image_url}
                    alt={item.name}
                    className="h-32 w-full object-cover"
                />
            )}
            <div className="flex items-center gap-3 p-4">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold">
                        {item.name}
                    </p>
                    {item.description !== null && (
                        <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                            {item.description}
                        </p>
                    )}
                    <p className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                        {icon}
                        {item.items_count} {countLabel}
                    </p>
                </div>
                <span className="rounded-full border border-[#AFFA01]/30 bg-[#AFFA01]/10 px-2.5 py-1 text-xs font-semibold text-[#AFFA01]">
                    Free
                </span>
            </div>
        </Link>
    );
}

export default function MemberFreePrograms({ coaches }: Props) {
    const { t } = useTranslation();
    const empty = coaches.every(
        (coach) => coach.exercise.length === 0 && coach.meal.length === 0,
    );

    return (
        <MobileShell>
            <Head title={t('member.programs.title')} />

            <div>
                <h1 className="text-xl font-semibold tracking-tight">
                    {t('member.programs.title')}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {t('member.programs.subtitle')}
                </p>
            </div>

            {empty ? (
                <div className="mt-6 rounded-2xl border border-dashed border-[#2E2E2E] p-8 text-center">
                    <Gift className="mx-auto size-8 text-muted-foreground" />
                    <p className="mt-3 text-sm font-medium">
                        {t('member.programs.empty')}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {t('member.programs.empty_hint')}
                    </p>
                </div>
            ) : (
                coaches
                    .filter(
                        (coach) =>
                            coach.exercise.length > 0 || coach.meal.length > 0,
                    )
                    .map((coach) => (
                    <section key={coach.name} className="mt-6 grid gap-3">
                        <div className="flex items-center gap-2.5">
                            <Avatar className="size-8">
                                <AvatarImage
                                    src={coach.avatar ?? undefined}
                                />
                                <AvatarFallback>
                                    {coach.name.slice(0, 1)}
                                </AvatarFallback>
                            </Avatar>
                            <p className="text-sm font-semibold">
                                {coach.name}
                            </p>
                        </div>

                        {coach.exercise.map((item) => (
                            <ProgramCard
                                key={`ex-${item.id}`}
                                item={item}
                                countLabel={t('member.programs.workouts')}
                                icon={
                                    <Dumbbell className="size-3.5 text-[#AFFA01]" />
                                }
                            />
                        ))}
                        {coach.meal.map((item) => (
                            <ProgramCard
                                key={`meal-${item.id}`}
                                item={item}
                                countLabel={t('member.programs.meals')}
                                icon={
                                    <UtensilsCrossed className="size-3.5 text-[#AFFA01]" />
                                }
                            />
                        ))}
                    </section>
                ))
            )}
        </MobileShell>
    );
}
