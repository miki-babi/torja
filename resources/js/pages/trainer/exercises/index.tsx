import { Head, Link, router } from '@inertiajs/react';
import { Dumbbell, Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/trainer';
import { create, destroy, edit, index } from '@/routes/trainer/exercises';
import type { Paginated } from '@/types';

type ExerciseRow = {
    id: number;
    slug: string;
    name: string;
    name_am: string | null;
    body_part: string;
    equipment: string;
    level: string;
    image_url: string;
    has_video: boolean;
};

type Props = {
    exercises: Paginated<ExerciseRow>;
    filters: { search: string };
};

export default function TrainerExercisesIndex({ exercises, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const { confirm, confirmDialog } = useConfirm();

    return (
        <>
            <Head title="My exercises" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="My exercises"
                    description="Your private exercise library — mixed into the program builder alongside the shared catalog."
                    actions={
                        <div className="flex items-center gap-2">
                            <form
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    router.get(
                                        index().url,
                                        { search },
                                        {
                                            preserveState: true,
                                            replace: true,
                                        },
                                    );
                                }}
                                className="relative"
                            >
                                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    placeholder="Search your exercises…"
                                    className="w-56 pl-8"
                                />
                            </form>
                            <Button asChild>
                                <Link href={create().url}>
                                    <Plus className="size-4" />
                                    New exercise
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {exercises.total} exercises
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {exercises.data.length === 0 ? (
                            <EmptyState
                                icon={Dumbbell}
                                title="No exercises yet"
                                description="Create an exercise your clients can't find in the shared catalog — same names are allowed."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead />
                                        <TableHead>Exercise</TableHead>
                                        <TableHead>Body part</TableHead>
                                        <TableHead>Equipment</TableHead>
                                        <TableHead>Level</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {exercises.data.map((exercise) => (
                                        <TableRow key={exercise.id}>
                                            <TableCell className="w-14">
                                                <img
                                                    src={exercise.image_url}
                                                    alt=""
                                                    className="size-10 rounded object-cover"
                                                    loading="lazy"
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm font-medium">
                                                    {exercise.name}
                                                    {exercise.has_video && (
                                                        <Badge
                                                            variant="outline"
                                                            className="ml-2"
                                                        >
                                                            video
                                                        </Badge>
                                                    )}
                                                </p>
                                                {exercise.name_am && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {exercise.name_am}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm capitalize">
                                                {exercise.body_part}
                                            </TableCell>
                                            <TableCell className="text-sm capitalize">
                                                {exercise.equipment}
                                            </TableCell>
                                            <TableCell className="text-sm capitalize">
                                                {exercise.level}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="ghost"
                                                    >
                                                        <Link
                                                            href={
                                                                edit(
                                                                    exercise.slug,
                                                                ).url
                                                            }
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-muted-foreground hover:text-destructive"
                                                        onClick={() =>
                                                            confirm({
                                                                title: 'Delete this exercise?',
                                                                description:
                                                                    'Programs that use it must remove it first — deletion is blocked while in use.',
                                                                confirmLabel:
                                                                    'Delete',
                                                                onConfirm:
                                                                    () =>
                                                                        router.delete(
                                                                            destroy(
                                                                                exercise.slug,
                                                                            )
                                                                                .url,
                                                                        ),
                                                            })
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <AdminPagination paginated={exercises} />
            </div>
            {confirmDialog}
        </>
    );
}

TrainerExercisesIndex.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
