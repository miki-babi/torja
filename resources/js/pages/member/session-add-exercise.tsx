import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import type { ExerciseSummary } from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import type { WorkoutSession } from '@/components/member/types';
import { setsForExercise } from '@/components/member/workout-progress';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { upsert } from '@/routes/member/sessions/sets';

export default function MemberSessionAddExercise({
    session,
    backUrl,
}: {
    session: WorkoutSession;
    backUrl: string;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ExerciseSummary[]>([]);
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
        const timer = window.setTimeout(() => {
            searchExercises({ q: query, limit: 30 })
                .then((data) => {
                    if (!cancelled) {
                        setResults(data.exercises);
                    }
                })
                .catch(() => {});
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [query]);

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
            <Button variant="ghost" size="sm" asChild className="-ml-2">
                <Link href={returnUrl}>
                    <ChevronLeft className="size-4" />
                    Back to session
                </Link>
            </Button>

            <h1 className="mt-4 text-xl font-semibold tracking-tight">
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
        </MobileShell>
    );
}
