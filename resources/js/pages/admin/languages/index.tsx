import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes/admin';
import { index, store, update } from '@/routes/admin/languages';

type Language = {
    id: number;
    code: string;
    name: string;
    native_name: string;
    is_active: boolean;
    is_default: boolean;
    position: number;
};

export default function AdminLanguagesIndex({
    languages,
}: {
    languages: Language[];
}) {
    const form = useForm({ code: '', name: '', native_name: '' });

    function create(e: FormEvent) {
        e.preventDefault();
        form.post(store().url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    function toggle(language: Language, field: 'is_active' | 'is_default') {
        router.patch(
            update(language.id).url,
            {
                name: language.name,
                native_name: language.native_name,
                is_active:
                    field === 'is_active'
                        ? !language.is_active
                        : language.is_active,
                is_default:
                    field === 'is_default'
                        ? !language.is_default
                        : language.is_default,
                position: language.position,
            },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Admin — languages" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Languages"
                    description="Languages available for the member UI"
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Add language
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={create}
                            className="grid items-end gap-2 sm:grid-cols-3"
                        >
                            <div className="grid gap-1.5">
                                <Label htmlFor="code">Code</Label>
                                <Input
                                    id="code"
                                    value={form.data.code}
                                    onChange={(e) =>
                                        form.setData('code', e.target.value)
                                    }
                                    placeholder="am"
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    placeholder="Amharic"
                                    required
                                />
                            </div>
                            <div className="grid gap-1.5">
                                <Label htmlFor="native_name">Native name</Label>
                                <Input
                                    id="native_name"
                                    value={form.data.native_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'native_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="አማርኛ"
                                    required
                                />
                            </div>
                            <Button
                                type="submit"
                                className="w-fit sm:col-span-3"
                                disabled={form.processing}
                            >
                                Add
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Installed languages
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Language</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead className="text-right">
                                        Position
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-center">
                                        Active
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Default
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {languages.map((language) => (
                                    <TableRow key={language.id}>
                                        <TableCell>
                                            <p className="font-medium">
                                                {language.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {language.native_name}
                                            </p>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {language.code}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {language.position}
                                        </TableCell>
                                        <TableCell>
                                            {language.is_default && (
                                                <Badge variant="secondary">
                                                    default
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Checkbox
                                                checked={language.is_active}
                                                onCheckedChange={() =>
                                                    toggle(
                                                        language,
                                                        'is_active',
                                                    )
                                                }
                                                disabled={language.is_default}
                                                aria-label={`Toggle ${language.name} active`}
                                            />
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Checkbox
                                                checked={language.is_default}
                                                onCheckedChange={() =>
                                                    toggle(
                                                        language,
                                                        'is_default',
                                                    )
                                                }
                                                disabled={language.is_default}
                                                aria-label={`Set ${language.name} as default`}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminLanguagesIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Languages', href: index() },
    ],
};
