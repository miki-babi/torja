import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Utensils } from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
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
import { create, destroy, edit, index } from '@/routes/trainer/foods';
import type { Paginated } from '@/types';

type FoodRow = {
    id: number;
    name_en: string;
    name_am: string | null;
    category: string;
    kcal_per_100g: number | null;
    protein_g: number | null;
    image_url: string | null;
};

type Props = {
    foods: Paginated<FoodRow>;
    filters: { search: string };
};

export default function TrainerFoodsIndex({ foods, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const { confirm, confirmDialog } = useConfirm();

    return (
        <>
            <Head title="My foods" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="My foods"
                    description="Your private food library — these are the foods you can pick in the meal program builder."
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
                                    placeholder="Search your foods…"
                                    className="w-56 pl-8"
                                />
                            </form>
                            <Button asChild>
                                <Link href={create().url}>
                                    <Plus className="size-4" />
                                    New food
                                </Link>
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            {foods.total} foods
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {foods.data.length === 0 ? (
                            <EmptyState
                                icon={Utensils}
                                title="No foods yet"
                                description="Build your own food library from scratch — only the foods you create appear in meal programs."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead />
                                        <TableHead>Food</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>kcal / 100g</TableHead>
                                        <TableHead>Protein / 100g</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {foods.data.map((food) => (
                                        <TableRow key={food.id}>
                                            <TableCell className="w-14">
                                                {food.image_url ? (
                                                    <img
                                                        src={food.image_url}
                                                        alt=""
                                                        className="size-10 rounded object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="flex size-10 items-center justify-center rounded bg-muted">
                                                        <Utensils className="size-4 text-muted-foreground" />
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <p className="text-sm font-medium">
                                                    {food.name_en}
                                                </p>
                                                {food.name_am && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {food.name_am}
                                                    </p>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-sm capitalize">
                                                {food.category}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {food.kcal_per_100g ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {food.protein_g !== null
                                                    ? `${food.protein_g}g`
                                                    : '—'}
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
                                                                edit(food.id)
                                                                    .url
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
                                                                title: 'Delete this food?',
                                                                description:
                                                                    'Meal programs that use it must remove it first — deletion is blocked while in use.',
                                                                confirmLabel:
                                                                    'Delete',
                                                                onConfirm:
                                                                    () =>
                                                                        router.delete(
                                                                            destroy(
                                                                                food.id,
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

                <AdminPagination paginated={foods} />
            </div>
            {confirmDialog}
        </>
    );
}

TrainerFoodsIndex.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
