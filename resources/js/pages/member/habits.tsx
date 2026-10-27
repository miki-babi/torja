import { Head } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Flame,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { memberApi } from '@/components/member/member-api';
import { MobileShell } from '@/components/member/mobile-shell';
import type { Habit } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { destroy, store, toggle, update } from '@/routes/member/habits';

const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

function HabitCalendar({ dates }: { dates: string[] }) {
    const [offset, setOffset] = useState(0);
    const base = new Date();
    base.setDate(1);
    base.setMonth(base.getMonth() + offset);
    const year = base.getFullYear();
    const month = base.getMonth();
    const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const todayKey = new Date().toISOString().slice(0, 10);
    const done = new Set(dates);

    const cells: (number | null)[] = [
        ...Array<null>(firstWeekday).fill(null),
        ...Array.from({ length: daysInMonth }, (_, i) => i + 1),
    ];

    return (
        <div>
            <div className="mb-2 flex items-center justify-between">
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    onClick={() => setOffset((o) => o - 1)}
                >
                    <ChevronLeft className="size-4" />
                </Button>
                <span className="text-sm font-medium">
                    {base.toLocaleDateString(undefined, {
                        month: 'long',
                        year: 'numeric',
                    })}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    disabled={offset >= 0}
                    onClick={() => setOffset((o) => o + 1)}
                >
                    <ChevronRight className="size-4" />
                </Button>
            </div>
            <div className="grid grid-cols-7 gap-1 text-center text-xs">
                {WEEKDAYS.map((d) => (
                    <span key={d} className="text-muted-foreground">
                        {d}
                    </span>
                ))}
                {cells.map((day, i) => {
                    if (day === null) {
                        return <span key={i} />;
                    }
                    const key = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const marked = done.has(key);
                    return (
                        <span
                            key={i}
                            className={cn(
                                'flex aspect-square items-center justify-center rounded-full',
                                marked &&
                                    'bg-lime-400 font-medium text-lime-950',
                                key === todayKey && 'ring-1 ring-foreground/40',
                            )}
                        >
                            {day}
                        </span>
                    );
                })}
            </div>
        </div>
    );
}

export default function MemberHabits({
    habits: initialHabits,
}: {
    habits: Habit[];
}) {
    const [habits, setHabits] = useState<Habit[]>(initialHabits);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Habit | null>(null);
    const [name, setName] = useState('');
    const [expanded, setExpanded] = useState<number | null>(null);

    const todayKey = new Date().toISOString().slice(0, 10);

    function upsert(habit: Habit) {
        setHabits((list) =>
            list.some((h) => h.id === habit.id)
                ? list.map((h) => (h.id === habit.id ? habit : h))
                : [...list, habit],
        );
    }

    async function save() {
        const trimmed = name.trim();
        if (trimmed === '') return;
        try {
            if (editing === null) {
                const { habit } = await memberApi<{ habit: Habit }>(
                    store().url,
                    'POST',
                    { name: trimmed },
                );
                upsert(habit);
            } else {
                const { habit } = await memberApi<{ habit: Habit }>(
                    update(editing.id).url,
                    'PATCH',
                    { name: trimmed },
                );
                upsert(habit);
            }
            setDialogOpen(false);
        } catch {
            toast.error('Could not save habit');
        }
    }

    async function remove(habit: Habit) {
        if (!window.confirm(`Delete "${habit.name}" and its history?`)) return;
        try {
            await memberApi(destroy(habit.id).url, 'DELETE');
            setHabits((list) => list.filter((h) => h.id !== habit.id));
        } catch {
            toast.error('Could not delete habit');
        }
    }

    async function flip(habit: Habit) {
        try {
            const { habit: updated } = await memberApi<{ habit: Habit }>(
                toggle(habit.id).url,
                'POST',
                {},
            );
            upsert(updated);
        } catch {
            toast.error('Could not update habit');
        }
    }

    return (
        <MobileShell>
            <Head title="Habits" />
            <div className="space-y-4">
                <Card>
                    <CardHeader className="flex-row items-center justify-between">
                        <CardTitle className="text-base">My habits</CardTitle>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setEditing(null);
                                setName('');
                                setDialogOpen(true);
                            }}
                        >
                            <Plus className="size-4" />
                            New
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {habits.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                No habits yet — create one to start tracking.
                            </p>
                        )}
                        <div className="divide-y">
                            {habits.map((h) => {
                                const doneToday = h.dates.includes(todayKey);
                                const open = expanded === h.id;
                                return (
                                    <div key={h.id} className="py-3">
                                        <div className="flex items-center gap-3">
                                            <button
                                                type="button"
                                                onClick={() => flip(h)}
                                                aria-label={`Toggle ${h.name} today`}
                                                className={cn(
                                                    'flex size-6 shrink-0 items-center justify-center rounded-full border-2',
                                                    doneToday
                                                        ? 'border-lime-500 bg-lime-400 text-lime-950'
                                                        : 'border-muted-foreground/40',
                                                )}
                                            >
                                                {doneToday && (
                                                    <Check className="size-3.5" />
                                                )}
                                            </button>
                                            <button
                                                type="button"
                                                className="min-w-0 flex-1 text-left"
                                                onClick={() =>
                                                    setExpanded(
                                                        open ? null : h.id,
                                                    )
                                                }
                                            >
                                                <p className="truncate text-sm font-medium">
                                                    {h.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <Flame className="mr-0.5 inline size-3" />
                                                    {h.streak} day streak ·{' '}
                                                    {h.total} total
                                                </p>
                                            </button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 shrink-0"
                                                onClick={() => {
                                                    setEditing(h);
                                                    setName(h.name);
                                                    setDialogOpen(true);
                                                }}
                                                aria-label={`Rename ${h.name}`}
                                            >
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-7 shrink-0"
                                                onClick={() => remove(h)}
                                                aria-label={`Delete ${h.name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                        {open && (
                                            <div className="mt-3 pl-9">
                                                <HabitCalendar
                                                    dates={h.dates}
                                                />
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>
                            {editing === null ? 'New habit' : 'Rename habit'}
                        </DialogTitle>
                    </DialogHeader>
                    <Input
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="Drink 2.5 L water"
                        onKeyDown={(e) => e.key === 'Enter' && save()}
                    />
                    <div className="flex justify-end">
                        <Button onClick={save} disabled={name.trim() === ''}>
                            Save
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </MobileShell>
    );
}
