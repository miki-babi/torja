import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes/admin';
import { edit, index } from '@/routes/admin/translations';
import type { Paginated } from '@/types';

type LanguageLine = {
    id: number;
    group: string;
    key: string;
    text: Record<string, string>;
};

type Props = {
    lines: Paginated<LanguageLine>;
    groups: string[];
    languages: { code: string; name: string; native_name: string }[];
    filters: { search: string; group: string };
};

export default function AdminTranslationsIndex({
    lines,
    groups,
    languages,
    filters,
}: Props) {
    function applyFilters(search: string, group: string) {
        router.get(
            index().url,
            { search: search || undefined, group: group || undefined },
            { preserveState: true },
        );
    }

    function onSearch(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const data = new FormData(e.currentTarget);
        const search = data.get('search');
        applyFilters(typeof search === 'string' ? search : '', filters.group);
    }

    return (
        <>
            <Head title="Admin — translations" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Translations"
                    description="UI strings, per language"
                />

                <div className="flex flex-wrap gap-2">
                    <form onSubmit={onSearch} className="flex flex-1 gap-2">
                        <div className="relative max-w-md flex-1">
                            <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                name="search"
                                defaultValue={filters.search}
                                placeholder="Search key or text…"
                                className="pl-8"
                            />
                        </div>
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                    <Select
                        value={filters.group || 'all'}
                        onValueChange={(v) =>
                            applyFilters(filters.search, v === 'all' ? '' : v)
                        }
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All groups" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All groups</SelectItem>
                            {groups.map((g) => (
                                <SelectItem key={g} value={g}>
                                    {g}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardContent className="p-0">
                        {lines.data.length === 0 ? (
                            <EmptyState
                                title="No strings found"
                                description="Try a different search or group filter."
                            />
                        ) : (
                            <div className="divide-y">
                                {lines.data.map((line) => (
                                    <Link
                                        key={line.id}
                                        href={edit(line.id).url}
                                        className="block px-4 py-3 transition-colors hover:bg-muted/50"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {line.group}
                                            </Badge>
                                            <span className="truncate text-sm font-medium">
                                                {line.key}
                                            </span>
                                        </div>
                                        <div className="mt-1.5 grid gap-x-6 gap-y-0.5 sm:grid-cols-2">
                                            {languages.map((lang) => (
                                                <p
                                                    key={lang.code}
                                                    className="truncate text-xs text-muted-foreground"
                                                >
                                                    <span className="font-mono font-medium text-foreground/70">
                                                        {lang.code}:
                                                    </span>{' '}
                                                    {line.text[lang.code] ??
                                                        '—'}
                                                </p>
                                            ))}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <AdminPagination page={lines} />
            </div>
        </>
    );
}

AdminTranslationsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Translations', href: index() },
    ],
};
