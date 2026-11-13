import { Head, Link, router } from '@inertiajs/react';
import { Dumbbell, Pencil, Search, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
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
import { edit, index } from '@/routes/admin/exercises';
import type { Paginated } from '@/types';

type ExerciseRow = {
    id: number;
    name: string;
    name_am: string | null;
    category: string;
    equipment: string;
    target: string;
    level: string;
    gif_url: string | null;
    review_needed: boolean;
};

type Props = {
    exercises: Paginated<ExerciseRow>;
    categories: string[];
    filters: { search: string; category: string };
};

export default function AdminExercisesIndex({
    exercises,
    categories,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    return (
        <>
            <Head title="Exercise library" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Exercise library"
                    description="The shared catalog every program builder draws from."
                    actions={
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(
                                    index().url,
                                    {
                                        search,
                                        category: filters.category,
                                    },
                                    { preserveState: true, replace: true },
                                );
                            }}
                            className="relative"
                        >
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search exercises…"
                                className="w-64 pl-8"
                            />
                        </form>
                    }
                />

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle className="text-base">
                            {exercises.total} exercises
                        </CardTitle>
                        <div className="flex flex-wrap gap-1">
                            <Link
                                href={index({ query: { search } }).url}
                                className={`rounded-full px-3 py-1 text-xs font-medium ${
                                    filters.category === ''
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                All
                            </Link>
                            {categories.map((category) => (
                                <Link
                                    key={category}
                                    href={
                                        index({
                                            query: { search, category },
                                        }).url
                                    }
                                    className={`rounded-full px-3 py-1 text-xs font-medium ${
                                        filters.category === category
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground'
                                    }`}
                                >
                                    {category}
                                </Link>
                            ))}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {exercises.data.length === 0 ? (
                            <EmptyState
                                icon={Dumbbell}
                                title="No exercises"
                                description="Try a different search or category."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead />
                                        <TableHead>Exercise</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Target</TableHead>
                                        <TableHead>Equipment</TableHead>
                                        <TableHead>Level</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {exercises.data.map((exercise) => (
                                        <TableRow key={exercise.id}>
                                            <TableCell className="w-14">
                                                {exercise.gif_url && (
                                                    <img
                                                        src={exercise.gif_url}
                                                        alt=""
                                                        className="size-10 rounded object-cover"
                                                        loading="lazy"
                                                    />
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm font-medium">
                                                    {exercise.name}
                                                </p>
                                                {exercise.name_am && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {exercise.name_am}
                                                    </p>
                                                )}
                                                {exercise.review_needed && (
                                                    <Badge
                                                        variant="outline"
                                                        className="mt-1"
                                                    >
                                                        <TriangleAlert className="mr-1 size-3" />
                                                        review
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {exercise.category}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {exercise.target}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {exercise.equipment}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {exercise.level}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="ghost"
                                                >
                                                    <Link
                                                        href={
                                                            edit(exercise.id)
                                                                .url
                                                        }
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                </Button>
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
        </>
    );
}
