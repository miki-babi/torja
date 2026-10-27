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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import { create, edit, index } from '@/routes/admin/ingredients';
import type { AdminIngredient, Paginated } from '@/types';

type Props = {
    ingredients: Paginated<AdminIngredient>;
    filters: { search: string };
};

export default function AdminIngredientsIndex({ ingredients, filters }: Props) {
    function submitSearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        router.get(
            index().url,
            { search: data.get('search') || undefined },
            { preserveState: true },
        );
    }

    return (
        <>
            <Head title="Admin — ingredients" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Ingredients"
                    description="Edit ingredient names and allergy labels"
                    actions={
                        <Button size="sm" asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New ingredient
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            All ingredients
                        </CardTitle>
                        <CardDescription>
                            {ingredients.total.toLocaleString()} ingredients —
                            search by English or Amharic name
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <form onSubmit={submitSearch} className="flex gap-2">
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

                        {ingredients.data.length === 0 ? (
                            <EmptyState
                                title="No ingredients found"
                                description="Try a different search term."
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name (EN)</TableHead>
                                        <TableHead>Name (AM)</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Allergy label</TableHead>
                                        <TableHead>Flags</TableHead>
                                        <TableHead className="w-16" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ingredients.data.map((ingredient) => (
                                        <TableRow key={ingredient.id}>
                                            <TableCell className="font-medium">
                                                {ingredient.name_en}
                                            </TableCell>
                                            <TableCell>
                                                {ingredient.name_am}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">
                                                    {ingredient.category}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {ingredient.common_allergy_label ??
                                                    '—'}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    {ingredient.show_in_onboarding && (
                                                        <Badge variant="secondary">
                                                            onboarding
                                                        </Badge>
                                                    )}
                                                    {ingredient.is_vegan && (
                                                        <Badge variant="outline">
                                                            vegan
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={edit(
                                                            ingredient.id,
                                                        )}
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

                        <AdminPagination page={ingredients} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminIngredientsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Ingredients', href: index() },
    ],
};
