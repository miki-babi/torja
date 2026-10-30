import { Head, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { BodyPartPicker } from '@/components/member/body-part-picker';
import { EquipmentPicker } from '@/components/member/equipment-picker';
import type {
    BodyPart,
    ExerciseSummary,
} from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import type { WorkoutSession } from '@/components/member/types';
import { setsForExercise } from '@/components/member/workout-progress';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';
import { upsert } from '@/routes/member/sessions/sets';

export default function MemberSessionAddExercise({
    session,
    backUrl,
    bodyParts,
    equipment,
}: {
    session: WorkoutSession;
    backUrl: string;
    bodyParts: BodyPart[];
    equipment: string[];
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [bodyPart, setBodyPart] = useState<string[]>([]);
    const [equipmentSel, setEquipmentSel] = useState<string[]>([]);
    const [results, setResults] = useState<ExerciseSummary[]>([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);

    const loggedCount = new Map<string, number>();
    for (const set of session.sets) {
        loggedCount.set(
            set.exercise_id,
            (loggedCount.get(set.exercise_id) ?? 0) + 1,
        );
    }

    const returnUrl =
        backUrl + (backUrl.includes('?') ? '&' : '?') + 'session=' + session.id;

    useEffect(() => {
        let cancelled = false;
        setLoading(true);

        const timer = window.setTimeout(() => {
            searchExercises({
                q: query,
                body_part: bodyPart,
                equipment: equipmentSel,
                limit: 30,
            })
                .then((data) => {
                    if (!cancelled) {
                        setResults(data.exercises);
                        setTotal(data.total);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    if (!cancelled) {
                        setLoading(false);
                    }
                });
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [query, bodyPart, equipmentSel]);

    function loadMore() {
        setLoading(true);
        searchExercises({
            q: query,
            body_part: bodyPart,
            equipment: equipmentSel,
            offset: results.length,
        })
            .then((data) => {
                setResults((list) => [...list, ...data.exercises]);
                setTotal(data.total);
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }

    async function add(ex: ExerciseSummary) {
        if (busy) {
            return;
        }
        setBusy(true);
        try {
            await memberApi<{ session: WorkoutSession }>(
                upsert(session.id).url,
                'PUT',
                {
                    exercise_id: ex.slug,
                    set_index: setsForExercise(session, ex.slug).length,
                    weight_kg: null,
                    reps: null,
                },
            );
            router.visit(returnUrl);
        } catch {
            toast.error('Could not add exercise');
            setBusy(false);
        }
    }

    return (
        <MobileShell>
            <Head title="Add exercise" />
            <h1 className="text-xl font-semibold tracking-tight">
                Add exercise
            </h1>

            <div className="relative mt-4">
                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="Search exercises"
                    className="pl-9"
                    autoFocus
                />
            </div>

            <div className="mt-4">
                <h2 className="mb-2.5 text-xs font-bold tracking-wider text-[#AFFA01] uppercase">
                    {t('member.exercise.choose_body_part')}
                </h2>
                <BodyPartPicker
                    bodyParts={bodyParts}
                    value={bodyPart}
                    onChange={setBodyPart}
                />
            </div>

            <div className="mt-4">
                <h2 className="mb-2 text-sm font-medium">
                    {t('member.exercise.choose_equipment')}
                </h2>
                <EquipmentPicker
                    equipment={equipment}
                    value={equipmentSel}
                    onChange={setEquipmentSel}
                />
            </div>

            <Card className="mt-4">
                <CardContent className="divide-y p-0">
                    {results.length === 0 && (
                        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                            No exercises found.
                        </p>
                    )}
                    {results.map((ex) => {
                        const logged = loggedCount.get(ex.slug);
                        return (
                            <button
                                key={ex.slug}
                                type="button"
                                disabled={busy}
                                onClick={() => add(ex)}
                                className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-accent/50"
                            >
                                <img
                                    src={ex.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-10 shrink-0 rounded-lg object-cover"
                                />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium capitalize">
                                        {ex.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {ex.detail}
                                        {logged !== undefined &&
                                            ` · ${logged} set${logged === 1 ? '' : 's'} logged`}
                                    </p>
                                </div>
                                <Plus className="size-4 shrink-0 text-muted-foreground" />
                            </button>
                        );
                    })}
                </CardContent>
            </Card>

            {results.length < total && (
                <Button
                    variant="outline"
                    className="mt-4 w-full"
                    disabled={loading}
                    onClick={loadMore}
                >
                    {loading
                        ? t('common.loading')
                        : `${t('member.exercise.load_more')} (${total - results.length})`}
                </Button>
            )}
        </MobileShell>
    );
}
