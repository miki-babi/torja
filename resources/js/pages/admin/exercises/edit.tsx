import { Head, Link } from '@inertiajs/react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, update } from '@/routes/admin/exercises';
import { useForm } from '@inertiajs/react';

type Exercise = {
    id: number;
    name: string;
    name_am: string | null;
    category: string;
    equipment: string;
    body_part: string;
    target: string;
    level: string;
    instructions: string | null;
    instructions_am: string | null;
    gif_url: string | null;
};

export default function AdminExerciseEdit({ exercise }: { exercise: Exercise }) {
    const form = useForm({
        name: exercise.name,
        name_am: exercise.name_am ?? '',
        category: exercise.category,
        equipment: exercise.equipment,
        body_part: exercise.body_part,
        target: exercise.target,
        level: exercise.level,
        instructions: exercise.instructions ?? '',
        instructions_am: exercise.instructions_am ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.put(update(exercise.id).url);
    }

    return (
        <>
            <Head title={`Edit ${exercise.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={exercise.name}
                    description="Fix naming, translation, or categorisation. Media stays unchanged."
                />

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle className="text-base">
                            Exercise details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            {exercise.gif_url && (
                                <img
                                    src={exercise.gif_url}
                                    alt={exercise.name}
                                    className="size-40 rounded-lg object-cover"
                                />
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="name_am">Name (Amharic)</Label>
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
                                    <Label htmlFor="category">Category</Label>
                                    <Input
                                        id="category"
                                        value={form.data.category}
                                        onChange={(e) =>
                                            form.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.category}
                                    />
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
                                    />
                                    <InputError
                                        message={form.errors.equipment}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="body_part">Body part</Label>
                                    <Input
                                        id="body_part"
                                        value={form.data.body_part}
                                        onChange={(e) =>
                                            form.setData(
                                                'body_part',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.body_part}
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
                                    />
                                    <InputError message={form.errors.target} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="level">Level</Label>
                                    <Input
                                        id="level"
                                        value={form.data.level}
                                        onChange={(e) =>
                                            form.setData(
                                                'level',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={form.errors.level} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="instructions">
                                    Instructions
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

                            <div className="grid gap-2">
                                <Label htmlFor="instructions_am">
                                    Instructions (Amharic)
                                </Label>
                                <textarea
                                    id="instructions_am"
                                    value={form.data.instructions_am}
                                    onChange={(e) =>
                                        form.setData(
                                            'instructions_am',
                                            e.target.value,
                                        )
                                    }
                                    rows={4}
                                    className="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                <InputError
                                    message={form.errors.instructions_am}
                                />
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Save changes
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
