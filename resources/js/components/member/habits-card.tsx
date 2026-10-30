import { Link } from '@inertiajs/react';
import { Check, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import type { HomeHabit } from '@/components/member/types';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { habits } from '@/routes/member';
import { toggle } from '@/routes/member/habits';

export function HabitsCard({ habits: initial }: { habits: HomeHabit[] }) {
    const [list, setList] = useState<HomeHabit[]>(initial);

    async function flip(habit: HomeHabit) {
        const done = !habit.done;
        setList((l) => l.map((h) => (h.id === habit.id ? { ...h, done } : h)));
        try {
            await memberApi(toggle(habit.id).url, 'POST', {});
        } catch {
            setList((l) =>
                l.map((h) => (h.id === habit.id ? { ...h, done: !done } : h)),
            );
            toast.error('Could not update habit');
        }
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">Habits</CardTitle>
                <Link
                    href={habits()}
                    className="flex items-center text-xs text-muted-foreground"
                >
                    Manage
                    <ChevronRight className="size-4" />
                </Link>
            </CardHeader>
            <CardContent>
                {list.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No habits yet —{' '}
                        <Link href={habits()} className="underline">
                            add some
                        </Link>
                    </p>
                ) : (
                    <div className="space-y-3">
                        {list.map((h) => (
                            <button
                                key={h.id}
                                type="button"
                                onClick={() => flip(h)}
                                className="flex w-full items-center gap-3 text-left"
                            >
                                <span
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full border transition-all',
                                        h.done
                                            ? 'border-[#AFFA01] bg-[#AFFA01] text-black shadow-[0_0_10px_rgba(175,250,1,0.4)]'
                                            : 'border-[#3E3E3E] bg-[#121212]',
                                    )}
                                >
                                    {h.done && <Check className="size-3.5 stroke-[3]" />}
                                </span>
                                <span
                                    className={cn(
                                        'text-sm',
                                        h.done &&
                                            'text-muted-foreground line-through',
                                    )}
                                >
                                    {h.name}
                                </span>
                            </button>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
