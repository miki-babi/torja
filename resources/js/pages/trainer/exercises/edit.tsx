import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/trainer';
import { index, store, update } from '@/routes/trainer/exercises';

type Exercise = {
    id: number;
    slug: string;
    name: string;
    name_am: string | null;
    body_part: string;
    equipment: string;
    target: string;
    level: string;
    instructions: string;
    image_url: string;
    gif_url: string | null;
    is_video: boolean;
};

type Props = {
    exercise: Exercise | null;
    bodyParts: string[];
    levels: string[];
};

export default function TrainerExerciseEdit({
    exercise,
    bodyParts,
    levels,
}: Props) {
    const form = useForm<{
        name: string;
        name_am: string;
        body_part: string;
        equipment: string;
        target: string;
        level: string;
        instructions: string;
        image: File | null;
        video: File | null;
    }>({
        name: exercise?.name ?? '',
        name_am: exercise?.name_am ?? '',
        body_part: exercise?.body_part ?? '',
        equipment: exercise?.equipment ?? '',
        target: exercise?.target ?? '',
        level: exercise?.level ?? 'beginner',
        instructions: exercise?.instructions ?? '',
        image: null,
        video: null,
    });

    const [imagePreview, setImagePreview] = useState<string | null>(
        exercise?.image_url ?? null,
    );
    const [videoName, setVideoName] = useState<string | null>(null);

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true };
        if (exercise === null) {
            form.post(store().url, options);
        } else {
            form.put(update(exercise.slug).url, options);
        }
    }

    function pickImage(event: FormEvent<HTMLInputElement>) {
        const file = event.currentTarget.files?.[0] ?? null;
        form.setData('image', file);
        setImagePreview((previous) => {
            if (previous?.startsWith('blob:')) {
                URL.revokeObjectURL(previous);
            }
            return file ? URL.createObjectURL(file) : exercise?.image_url ?? null;
        });
    }

    return (
        <>
            <Head
                title={exercise === null ? 'New exercise' : exercise.name}
            />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={
                        exercise === null ? 'New exercise' : 'Edit exercise'
                    }
                    description="Your own exercise — pickable alongside the shared catalog when you build programs. Names may repeat."
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Exercise details
                        </CardTitle>
                        <CardDescription>
                            One instruction step per line.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="e.g. Bench press"
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_am">
                                    Name (Amharic, optional)
                                </Label>
                                <Input
                                    id="name_am"
                                    value={form.data.name_am}
                                    onChange={(e) =>
                                        form.setData('name_am', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.name_am} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>Body part</Label>
                                    <Select
                                        value={form.data.body_part}
                                        onValueChange={(value) =>
                                            form.setData('body_part', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pick one" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {bodyParts.map((part) => (
                                                <SelectItem
                                                    key={part}
                                                    value={part}
                                                >
                                                    <span className="capitalize">
                                                        {part}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.body_part}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>Level</Label>
                                    <Select
                                        value={form.data.level}
                                        onValueChange={(value) =>
                                            form.setData('level', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {levels.map((level) => (
                                                <SelectItem
                                                    key={level}
                                                    value={level}
                                                >
                                                    <span className="capitalize">
                                                        {level}
                                                    </span>
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={form.errors.level} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="equipment">Equipment</Label>
                                    <Input
                                        id="equipment"
                                        value={form.data.equipment}
                                        onChange={(e) =>
                                            form.setData(
                                                'equipment',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. dumbbell, band, body weight"
                                    />
                                    <InputError
                                        message={form.errors.equipment}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="target">Target</Label>
                                    <Input
                                        id="target"
                                        value={form.data.target}
                                        onChange={(e) =>
                                            form.setData(
                                                'target',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. pectorals, quads"
                                    />
                                    <InputError message={form.errors.target} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="instructions">
                                    Instructions (optional)
                                </Label>
                                <textarea
                                    id="instructions"
                                    value={form.data.instructions}
                                    onChange={(e) =>
                                        form.setData(
                                            'instructions',
                                            e.target.value,
                                        )
                                    }
                                    rows={4}
                                    className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                <InputError
                                    message={form.errors.instructions}
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="image">
                                        Thumbnail image (optional)
                                    </Label>
                                    <Input
                                        id="image"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={pickImage}
                                    />
                                    <InputError message={form.errors.image} />
                                    {imagePreview !== null && (
                                        <img
                                            src={imagePreview}
                                            alt=""
                                            className="size-24 rounded-lg object-cover"
                                        />
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="video">
                                        Demo video (optional)
                                    </Label>
                                    <Input
                                        id="video"
                                        type="file"
                                        accept="video/mp4,video/webm,video/quicktime,image/gif"
                                        onChange={(e) => {
                                            const file =
                                                e.currentTarget.files?.[0] ??
                                                null;
                                            form.setData('video', file);
                                            setVideoName(file?.name ?? null);
                                        }}
                                    />
                                    <InputError message={form.errors.video} />
                                    <p className="text-xs text-muted-foreground">
                                        mp4, webm, mov or gif — up to 20MB.
                                        {videoName !== null &&
                                            ` Selected: ${videoName}`}
                                    </p>
                                    {exercise?.gif_url != null &&
                                        videoName === null && (
                                            <p className="text-xs text-muted-foreground">
                                                Current demo:{' '}
                                                {exercise.is_video
                                                    ? 'video'
                                                    : 'image'}{' '}
                                                attached — upload to replace.
                                            </p>
                                        )}
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing && <Spinner />}
                                    {exercise === null
                                        ? 'Create exercise'
                                        : 'Save changes'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={index().url}>Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

TrainerExerciseEdit.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
