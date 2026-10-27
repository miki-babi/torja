import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import { create, edit, index } from '@/routes/admin/foods';
import type { AdminFood, Paginated } from '@/types';

type Props = {
    foods: Paginated<AdminFood>;
    categories: string[];
    filters: { search: string; category: string };
};

export default function AdminFoodsIndex({ foods, categories, filters }: Props) {
    function applyFilters(search: string, category: string) {
        router.get(
            index().url,
            { search: search || undefined, category: category || undefined },
            { preserveState: true },
        );
    }

    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const search = data.get('search');
        applyFilters(
            typeof search === 'string' ? search : '',
            filters.category,
        );
    }

    return (
        <>
            <Head title="Admin — foods" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Foods"
                    description="Edit food names, macros and review flags"
                    actions={
                        <Button size="sm" asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New food
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">All foods</CardTitle>
                        <CardDescription>
                            {foods.total.toLocaleString()} foods — search by
                            English or Amharic name
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap gap-2">
                            <form
                                onSubmit={submitSearch}
                                className="flex gap-2"
                            >
                                <div className="relative max-w-xs flex-1">
                                    <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="search"
                                        defaultValue={filters.search}
                                        placeholder="Search name…"
                                        className="pl-8"
                                    />
                                </div>
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                            </form>
                            <Select
                                value={
                                    filters.category === ''
                                        ? 'all'
                                        : filters.category
                                }
                                onValueChange={(value) =>
                                    applyFilters(
                                        filters.search,
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger className="w-52">
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All categories
                                    </SelectItem>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category}
                                            value={category}
                                        >
                                            {category}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {foods.data.length === 0 ? (
                            <EmptyState
                                title="No foods found"
                                description="Try a different search or category filter."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name (EN)</TableHead>
                                        <TableHead>Name (AM)</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead className="text-right">
                                            kcal/100g
                                        </TableHead>
                                        <TableHead className="text-right">
                                            P/C/F
                                        </TableHead>
                                        <TableHead>Flags</TableHead>
                                        <TableHead className="w-16" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {foods.data.map((food) => (
                                        <TableRow key={food.id}>
                                            <TableCell className="font-medium">
                                                {food.name_en}
                                            </TableCell>
                                            <TableCell>
                                                {food.name_am ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {food.category}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {food.kcal_per_100g ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {food.protein_g ?? '—'}/
                                                {food.carbs_g ?? '—'}/
                                                {food.fat_g ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {food.review_needed && (
                                                    <Badge variant="destructive">
                                                        review needed
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(food.id)}
                                                        prefetch
                                                    >
                                                        Edit
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <AdminPagination page={foods} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminFoodsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Foods', href: index() },
    ],
};
