import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { FormEvent } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes/admin';
import { index, update } from '@/routes/admin/translations';

type Props = {
    line: {
        id: number;
        group: string;
        key: string;
        text: Record<string, string>;
    };
    languages: { code: string; name: string; native_name: string }[];
};

export default function AdminTranslationEdit({ line, languages }: Props) {
    function save(e: FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const data = new FormData(e.currentTarget);
        const text: Record<string, string> = {};
        for (const lang of languages) {
            const value = data.get(`text.${lang.code}`);
            text[lang.code] = typeof value === 'string' ? value : '';
        }
        router.patch(update(line.id).url, { text }, { preserveScroll: true });
    }

    return (
        <>
            <Head title={`Admin — ${line.group}.${line.key}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={line.key}
                    description={`Edit translations in group "${line.group}"`}
                    actions={
                        <Button variant="outline" size="sm" asChild>
                            <Link href={index().url} prefetch>
                                <ArrowLeft className="size-4" />
                                All strings
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            <Badge variant="outline" className="mr-2">
                                {line.group}
                            </Badge>
                            {line.group}.{line.key}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={save} className="space-y-4">
                            {languages.map((lang) => (
                                <div key={lang.code} className="grid gap-1.5">
                                    <Label htmlFor={`text.${lang.code}`}>
                                        {lang.name} ({lang.code})
                                    </Label>
                                    <textarea
                                        id={`text.${lang.code}`}
                                        name={`text.${lang.code}`}
                                        defaultValue={
                                            line.text[lang.code] ?? ''
                                        }
                                        rows={2}
                                        className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                </div>
                            ))}
                            <Button type="submit">Save</Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminTranslationEdit.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Translations', href: index() },
    ],
};
