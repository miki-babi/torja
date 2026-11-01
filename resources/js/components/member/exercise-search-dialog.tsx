import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { type ExerciseSummary } from '@/components/member/exercise-data';
import { searchExercises } from '@/components/member/exercise-search';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';

/**
 * Pick-any-exercise dialog backed by the catalog search endpoint. Used by
 * the progress page's quick-log and backfill flows — the caller gets the
 * chosen ExerciseSummary.
 */
export function ExerciseSearchDialog({
    open,
    onOpenChange,
    onPick,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPick: (exercise: ExerciseSummary) => void;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<ExerciseSummary[]>([]);
    const [loading, setLoading] = useState(false);
    const skipFirst = useRef(true);

    useEffect(() => {
        if (!open) {
            return;
        }
        setQuery('');
        setResults([]);
        skipFirst.current = true;
        setLoading(true);
        searchExercises({ limit: 20 })
            .then((data) => setResults(data.exercises))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [open]);

    useEffect(() => {
        if (skipFirst.current) {
            skipFirst.current = false;
            return;
        }
        let cancelled = false;
        setLoading(true);
        const timer = window.setTimeout(() => {
            searchExercises({ q: query, limit: 20 })
                .then((data) => {
                    if (!cancelled) {
                        setResults(data.exercises);
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
    }, [query]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {t('member.progress.pick_exercise')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('member.progress.pick_exercise_hint')}
                    </DialogDescription>
                </DialogHeader>
                <div className="relative">
                    <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-[#8E8E93]" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={t('member.exercise.search_placeholder')}
                        className="pl-10"
                        autoFocus
                    />
                </div>
                <div className="max-h-72 divide-y divide-[#2E2E2E] overflow-y-auto rounded-xl border border-[#2E2E2E]">
                    {loading && results.length === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                            {t('common.loading')}
                        </p>
                    ) : results.length === 0 ? (
                        <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                            {t('member.exercise.no_results')}
                        </p>
                    ) : (
                        results.map((ex) => (
                            <button
                                key={ex.slug}
                                type="button"
                                onClick={() => {
                                    onPick(ex);
                                    onOpenChange(false);
                                }}
                                className="flex w-full items-center gap-3 px-3.5 py-2.5 text-left transition-colors hover:bg-[#262626]"
                            >
                                <img
                                    src={ex.image_url}
                                    alt=""
                                    loading="lazy"
                                    className="size-10 shrink-0 rounded-lg object-cover"
                                />
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-[#F5F5F5]">
                                        {ex.name}
                                    </p>
                                    <p className="truncate text-xs text-[#8E8E93]">
                                        {ex.detail}
                                    </p>
                                </div>
                            </button>
                        ))
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
