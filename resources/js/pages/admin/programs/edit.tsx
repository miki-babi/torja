import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Check,
    ChevronLeft,
    ChevronRight,
    Copy,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import { toast } from 'sonner';
import { AdminPageHeader } from '@/components/admin/page-header';
import { ProgramExercisePicker } from '@/components/admin/program-exercise-picker';
import InputError from '@/components/input-error';
import type { ExerciseSummaries } from '@/components/member/exercise-data';
import { memberApi } from '@/components/member/member-api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import { index, share, store, update } from '@/routes/admin/programs';
import type {
    AdminProgramDetail,
    AdminProgramExercise,
    AdminProgramWorkout,
} from '@/types';

type BodyPartOption = { id: string; label: string; count: number };

type Props = {
    program: AdminProgramDetail | null;
    exerciseSummaries: ExerciseSummaries;
    bodyParts: BodyPartOption[];
    equipment: string[];
    levels: string[];
};

type ProgramForm = {
    name: string;
    name_am: string;
    description: string;
    description_am: string;
    image: File | null;
    highlights: string;
    highlights_am: string;
    price_etb: string;
    is_published: boolean;
    workouts: AdminProgramWorkout[];
};

type Step = 'details' | 'workouts' | 'review';

const STEPS: { id: Step; label: string }[] = [
    { id: 'details', label: 'Details' },
    { id: 'workouts', label: 'Workouts' },
    { id: 'review', label: 'Review' },
];

function move<T>(list: T[], index: number, dir: -1 | 1): T[] {
    const next = [...list];
    const target = index + dir;
    if (target < 0 || target >= next.length) {
        return list;
    }
    [next[index], next[target]] = [next[target], next[index]];
    return next;
}

function WorkoutEditor({
    workout,
    index,
    workoutCount,
    exerciseSummaries,
    pickerProps,
    onChange,
    onMove,
    onDuplicate,
    onRemove,
}: {
    workout: AdminProgramWorkout;
    index: number;
    workoutCount: number;
    exerciseSummaries: ExerciseSummaries;
    pickerProps: {
        bodyParts: BodyPartOption[];
        equipment: string[];
        levels: string[];
    };
    onChange: (index: number, workout: AdminProgramWorkout) => void;
    onMove: (index: number, dir: -1 | 1) => void;
    onDuplicate: (index: number) => void;
    onRemove: (index: number) => void;
}) {
    const [summaries, setSummaries] = useState<ExerciseSummaries>({});
    const [pickerOpen, setPickerOpen] = useState(false);

    const mergedSummaries = { ...exerciseSummaries, ...summaries };

    const slugs = workout.exercises.map((item) => item.slug);

    function setExercises(exercises: AdminProgramExercise[]) {
        onChange(index, { ...workout, exercises });
    }

    function addExercises(added: string[], summaries: ExerciseSummaries) {
        setSummaries((map) => ({ ...map, ...summaries }));
        setExercises([
            ...workout.exercises,
            ...added
                .filter((slug) => !slugs.includes(slug))
                .map((slug) => ({ slug, sets: null, reps: null })),
        ]);
    }

    function setTarget(slug: string, field: 'sets' | 'reps', value: string) {
        const parsed = value.trim() === '' ? null : Number.parseInt(value, 10);
        setExercises(
            workout.exercises.map((item) =>
                item.slug === slug
                    ? {
                          ...item,
                          [field]: Number.isNaN(parsed) ? null : parsed,
                      }
                    : item,
            ),
        );
    }

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between gap-2">
                <Input
                    value={workout.title}
                    onChange={(e) =>
                        onChange(index, { ...workout, title: e.target.value })
                    }
                    placeholder={`Workout ${index + 1}`}
                    className="max-w-xs font-medium"
                />
                <div className="flex shrink-0 items-center">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={index === 0}
                        onClick={() => onMove(index, -1)}
                        aria-label="Move workout up"
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        disabled={index === workoutCount - 1}
                        onClick={() => onMove(index, 1)}
                        aria-label="Move workout down"
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => onDuplicate(index)}
                        aria-label={`Duplicate workout ${index + 1}`}
                    >
                        <Copy className="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        onClick={() => onRemove(index)}
                        aria-label={`Remove workout ${index + 1}`}
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-3">
                {workout.exercises.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No exercises yet — add some below.
                    </p>
                ) : (
                    <div className="divide-y">
                        {workout.exercises.map((item, exerciseIndex) => {
                            const slug = item.slug;
                            const ex = mergedSummaries[slug];
                            return (
                                <div
                                    key={slug}
                                    className="flex items-center gap-2 py-2"
                                >
                                    <div className="flex shrink-0 flex-col">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-6"
                                            disabled={exerciseIndex === 0}
                                            onClick={() =>
                                                setExercises(
                                                    move(
                                                        workout.exercises,
                                                        exerciseIndex,
                                                        -1,
                                                    ),
                                                )
                                            }
                                            aria-label={`Move ${ex?.name ?? slug} up`}
                                        >
                                            <ArrowUp className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-6"
                                            disabled={
                                                exerciseIndex ===
                                                workout.exercises.length - 1
                                            }
                                            onClick={() =>
                                                setExercises(
                                                    move(
                                                        workout.exercises,
                                                        exerciseIndex,
                                                        1,
                                                    ),
                                                )
                                            }
                                            aria-label={`Move ${ex?.name ?? slug} down`}
                                        >
                                            <ArrowDown className="size-3.5" />
                                        </Button>
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium capitalize">
                                            {ex?.name ?? slug}
                                        </p>
                                        {ex !== undefined && (
                                            <p className="text-xs text-muted-foreground">
                                                {ex.detail}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-1">
                                        <Input
                                            type="number"
                                            min={1}
                                            max={20}
                                            inputMode="numeric"
                                            placeholder="3"
                                            value={item.sets ?? ''}
                                            onChange={(e) =>
                                                setTarget(
                                                    slug,
                                                    'sets',
                                                    e.target.value,
                                                )
                                            }
                                            aria-label={`Sets for ${ex?.name ?? slug}`}
                                            className="h-8 w-14 px-2 text-center text-sm"
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            ×
                                        </span>
                                        <Input
                                            type="number"
                                            min={1}
                                            max={200}
                                            inputMode="numeric"
                                            placeholder="10"
                                            value={item.reps ?? ''}
                                            onChange={(e) =>
                                                setTarget(
                                                    slug,
                                                    'reps',
                                                    e.target.value,
                                                )
                                            }
                                            aria-label={`Reps for ${ex?.name ?? slug}`}
                                            className="h-8 w-14 px-2 text-center text-sm"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="size-7 shrink-0"
                                        onClick={() =>
                                            setExercises(
                                                workout.exercises.filter(
                                                    (entry) =>
                                                        entry.slug !== slug,
                                                ),
                                            )
                                        }
                                        aria-label={`Remove ${ex?.name ?? slug}`}
                                    >
                                        <X className="size-4" />
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                )}

                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setPickerOpen(true)}
                >
                    <Plus className="size-4" />
                    Add exercises
                </Button>

                <ProgramExercisePicker
                    open={pickerOpen}
                    onOpenChange={setPickerOpen}
                    exclude={slugs}
                    onAdd={addExercises}
                    {...pickerProps}
                />
            </CardContent>
        </Card>
    );
}

export default function AdminProgramEdit({
    program,
    exerciseSummaries,
    bodyParts,
    equipment,
    levels,
}: Props) {
    const form = useForm<ProgramForm>({
        name: program?.name ?? '',
        name_am: program?.name_am ?? '',
        description: program?.description ?? '',
        description_am: program?.description_am ?? '',
        image: null,
        highlights: program?.highlights.join('\n') ?? '',
        highlights_am: program?.highlights_am.join('\n') ?? '',
        price_etb: String(program?.price_etb ?? 0),
        is_published: program?.published ?? false,
        workouts: program?.workouts ?? [],
    });
    const [step, setStep] = useState<Step>('details');
    const [imagePreview, setImagePreview] = useState<string | null>(
        program?.image_url ?? null,
    );
    const [shareUrl, setShareUrl] = useState(program?.share_url ?? null);
    const [sharing, setSharing] = useState(false);
    const [, copy] = useClipboard();

    const stepIndex = STEPS.findIndex((s) => s.id === step);

    const exerciseCount = form.data.workouts.reduce(
        (total, workout) => total + workout.exercises.length,
        0,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const lines = (text: string) =>
            text
                .split('\n')
                .map((line) => line.trim())
                .filter((line) => line !== '');
        form.transform((data) => ({
            ...data,
            price_etb: Number(data.price_etb) || 0,
            highlights: lines(data.highlights),
            highlights_am: lines(data.highlights_am),
        }));
        if (program === null) {
            form.post(store().url, { preserveScroll: true });
        } else {
            form.put(update(program.id).url, { preserveScroll: true });
        }
    }

    function pickImage(event: FormEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0] ?? null;
        form.setData('image', file);
        setImagePreview((previous) => {
            if (previous?.startsWith('blob:')) {
                URL.revokeObjectURL(previous);
            }
            return file ? URL.createObjectURL(file) : null;
        });
    }

    function setWorkout(index: number, workout: AdminProgramWorkout) {
        form.setData(
            'workouts',
            form.data.workouts.map((w, i) => (i === index ? workout : w)),
        );
    }

    function moveWorkout(index: number, dir: -1 | 1) {
        form.setData('workouts', move(form.data.workouts, index, dir));
    }

    function duplicateWorkout(index: number) {
        const source = form.data.workouts[index];
        form.setData('workouts', [
            ...form.data.workouts.slice(0, index + 1),
            {
                title: `${source.title} (copy)`,
                exercises: [...source.exercises],
            },
            ...form.data.workouts.slice(index + 1),
        ]);
    }

    function removeWorkout(index: number) {
        form.setData(
            'workouts',
            form.data.workouts.filter((_, i) => i !== index),
        );
    }

    function addWorkout() {
        form.setData('workouts', [
            ...form.data.workouts,
            { title: `Day ${form.data.workouts.length + 1}`, exercises: [] },
        ]);
    }

    function next() {
        if (step === 'details') {
            if (form.data.name.trim() === '') {
                toast.error('Give the program a name first');
                return;
            }
            setStep('workouts');
        } else if (step === 'workouts') {
            if (form.data.workouts.length === 0) {
                toast.error('Add at least one workout');
                return;
            }
            if (
                form.data.workouts.some(
                    (w) => w.title.trim() === '' || w.exercises.length === 0,
                )
            ) {
                toast.error('Every workout needs a title and exercises');
                return;
            }
            setStep('review');
        }
    }

    async function enableShare() {
        if (program === null || sharing) {
            return;
        }
        setSharing(true);
        try {
            const { share_url } = await memberApi<{ share_url: string | null }>(
                share(program.id).url,
                'POST',
            );
            setShareUrl(share_url);
        } catch {
            toast.error('Could not create share link');
        } finally {
            setSharing(false);
        }
    }

    async function copyLink() {
        if (shareUrl && (await copy(shareUrl))) {
            toast.success('Link copied');
        }
    }

    return (
        <>
            <Head
                title={
                    program === null
                        ? 'Admin — new program'
                        : `Admin — edit ${program.name}`
                }
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <AdminPageHeader
                    title={
                        program === null
                            ? 'New program'
                            : `Edit program — ${program.name}`
                    }
                    description="Workouts in a program become playlists in the member's account"
                />

                <nav className="flex items-center gap-2">
                    {STEPS.map((s, i) => (
                        <button
                            key={s.id}
                            type="button"
                            onClick={() => {
                                if (i < stepIndex) {
                                    setStep(s.id);
                                }
                            }}
                            className={cn(
                                'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm',
                                step === s.id
                                    ? 'border-lime-500 bg-lime-300/40 font-medium'
                                    : i < stepIndex
                                      ? 'border-lime-500/40 text-muted-foreground'
                                      : 'border-input text-muted-foreground',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-5 items-center justify-center rounded-full text-xs',
                                    i <= stepIndex
                                        ? 'bg-lime-400 text-lime-950'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {i < stepIndex ? (
                                    <Check className="size-3" />
                                ) : (
                                    i + 1
                                )}
                            </span>
                            {s.label}
                        </button>
                    ))}
                </nav>

                <form onSubmit={submit} className="space-y-4">
                    {step === 'details' && (
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Program details
                                </CardTitle>
                                <CardDescription>
                                    Price 0 makes the shared link free to claim
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name_am">Name (አማርኛ)</Label>
                                    <Input
                                        id="name_am"
                                        value={form.data.name_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'name_am',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="የፕሮግራም ስም"
                                    />
                                    <InputError message={form.errors.name_am} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Input
                                        id="description"
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="What this program is for"
                                    />
                                    <InputError
                                        message={form.errors.description}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description_am">
                                        Description (አማርኛ)
                                    </Label>
                                    <Input
                                        id="description_am"
                                        value={form.data.description_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'description_am',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="ይህ ፕሮግራም ለምንድን ነው"
                                    />
                                    <InputError
                                        message={form.errors.description_am}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="image">Cover image</Label>
                                    <Input
                                        id="image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={pickImage}
                                    />
                                    <InputError message={form.errors.image} />
                                    {imagePreview !== null ? (
                                        <img
                                            src={imagePreview}
                                            alt="Cover preview"
                                            className="aspect-video w-full max-w-sm rounded-lg border object-cover"
                                        />
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Shown as the hero on the shared
                                            sales page. Without one, a collage
                                            of exercise images is used.
                                        </p>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="highlights">
                                        Highlights — one per line
                                    </Label>
                                    <textarea
                                        id="highlights"
                                        value={form.data.highlights}
                                        onChange={(e) =>
                                            form.setData(
                                                'highlights',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                        placeholder={
                                            'Lose 5kg in 6 weeks\n6 guided gym days'
                                        }
                                        className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                    />
                                    <InputError
                                        message={form.errors.highlights}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Bullet points shown in the "What's
                                        included" section of the sales page.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="highlights_am">
                                        Highlights (አማርኛ) — one per line
                                    </Label>
                                    <textarea
                                        id="highlights_am"
                                        value={form.data.highlights_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'highlights_am',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                        placeholder={
                                            'በ6 ሳምንት 5 ኪሎ ይቀንሱ\n6 የተመሩ የአካል ብቃት ቀናት'
                                        }
                                        className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                    />
                                    <InputError
                                        message={form.errors.highlights_am}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="price_etb">
                                        Price (ETB)
                                    </Label>
                                    <Input
                                        id="price_etb"
                                        type="number"
                                        min="0"
                                        step="1"
                                        value={form.data.price_etb}
                                        onChange={(e) =>
                                            form.setData(
                                                'price_etb',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.price_etb}
                                    />
                                    {Number(form.data.price_etb) > 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            Included in Pro — members claim it
                                            into their playlists.
                                        </p>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            className="w-fit"
                                        >
                                            Free program
                                        </Badge>
                                    )}
                                </div>

                                <div className="flex items-start gap-3">
                                    <Checkbox
                                        id="is_published"
                                        checked={form.data.is_published}
                                        onCheckedChange={(checked) =>
                                            form.setData(
                                                'is_published',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <div className="grid gap-1">
                                        <Label htmlFor="is_published">
                                            Publish to catalog
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Listed publicly at /explore so
                                            members can browse and save it. A
                                            share link is created if needed.
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {step === 'workouts' && (
                        <>
                            <div className="space-y-4">
                                {form.data.workouts.map((workout, index) => (
                                    <WorkoutEditor
                                        key={workout.id ?? `new-${index}`}
                                        workout={workout}
                                        index={index}
                                        workoutCount={form.data.workouts.length}
                                        exerciseSummaries={exerciseSummaries}
                                        pickerProps={{
                                            bodyParts,
                                            equipment,
                                            levels,
                                        }}
                                        onChange={setWorkout}
                                        onMove={moveWorkout}
                                        onDuplicate={duplicateWorkout}
                                        onRemove={removeWorkout}
                                    />
                                ))}
                            </div>

                            <InputError message={form.errors.workouts} />

                            <Button
                                type="button"
                                variant="outline"
                                onClick={addWorkout}
                            >
                                <Plus className="size-4" />
                                Add workout
                            </Button>
                        </>
                    )}

                    {step === 'review' && (
                        <Card className="max-w-2xl">
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {form.data.name || 'Untitled program'}
                                </CardTitle>
                                <CardDescription>
                                    {form.data.workouts.length}{' '}
                                    {form.data.workouts.length === 1
                                        ? 'workout'
                                        : 'workouts'}{' '}
                                    · {exerciseCount} exercises ·{' '}
                                    {Number(form.data.price_etb) > 0
                                        ? `${form.data.price_etb} ETB`
                                        : 'Free'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {imagePreview !== null && (
                                    <img
                                        src={imagePreview}
                                        alt="Cover preview"
                                        className="aspect-video w-full rounded-lg border object-cover"
                                    />
                                )}
                                {form.data.description !== '' && (
                                    <p className="text-sm text-muted-foreground">
                                        {form.data.description}
                                    </p>
                                )}
                                <div className="space-y-3">
                                    {form.data.workouts.map(
                                        (workout, index) => (
                                            <div
                                                key={
                                                    workout.id ??
                                                    `review-${index}`
                                                }
                                                className="rounded-lg border p-3"
                                            >
                                                <p className="text-sm font-medium">
                                                    {workout.title}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground capitalize">
                                                    {workout.exercises
                                                        .map(
                                                            ({ slug }) =>
                                                                exerciseSummaries[
                                                                    slug
                                                                ]?.name ?? slug,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                            </div>
                                        ),
                                    )}
                                </div>

                                {program !== null && (
                                    <div className="flex items-center gap-2 border-t pt-4">
                                        {shareUrl ? (
                                            <>
                                                <Input
                                                    readOnly
                                                    value={shareUrl}
                                                    onFocus={(e) =>
                                                        e.target.select()
                                                    }
                                                    className="max-w-sm text-xs"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={copyLink}
                                                >
                                                    <Copy className="size-4" />
                                                    Copy share link
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={sharing}
                                                onClick={enableShare}
                                            >
                                                {sharing
                                                    ? 'Creating…'
                                                    : 'Create share link'}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex items-center gap-2">
                        {stepIndex > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setStep(STEPS[stepIndex - 1].id)}
                            >
                                <ChevronLeft className="size-4" />
                                Back
                            </Button>
                        )}
                        {step !== 'review' ? (
                            <Button
                                type="button"
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                                onClick={next}
                            >
                                Next
                                <ChevronRight className="size-4" />
                            </Button>
                        ) : (
                            <Button
                                type="submit"
                                disabled={form.processing}
                                className="bg-lime-400 text-lime-950 hover:bg-lime-500"
                            >
                                {program === null
                                    ? 'Create program'
                                    : 'Save changes'}
                            </Button>
                        )}
                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AdminProgramEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Programs', href: index() },
    ],
};
