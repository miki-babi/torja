import { Head, Link, router } from '@inertiajs/react';
import {
    Copy,
    Eye,
    EyeOff,
    Link2,
    Link2Off,
    MoreHorizontal,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useConfirm } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { AdminPageHeader } from '@/components/admin/page-header';
import { AdminPagination } from '@/components/admin/pagination';
import { memberApi } from '@/components/member/member-api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { dashboard } from '@/routes/admin';
import {
    create,
    destroy,
    edit,
    index,
    publish,
    share,
    unpublish,
    unshare,
} from '@/routes/admin/meal-programs';
import type { AdminMealProgram, Paginated } from '@/types';

type Props = {
    programs: Paginated<AdminMealProgram>;
};

export default function AdminMealProgramsIndex({ programs }: Props) {
    const [, copy] = useClipboard();
    const { confirm, confirmDialog } = useConfirm();
    const [busyId, setBusyId] = useState<number | null>(null);
    const [shareUrls, setShareUrls] = useState<Record<number, string | null>>(
        () => Object.fromEntries(programs.data.map((p) => [p.id, p.share_url])),
    );
    const [publishedIds, setPublishedIds] = useState<Record<number, boolean>>(
        () => Object.fromEntries(programs.data.map((p) => [p.id, p.published])),
    );

    async function copyLink(program: AdminMealProgram) {
        const url = shareUrls[program.id];
        if (url && (await copy(url))) {
            toast.success('Link copied');
        }
    }

    async function toggleShare(program: AdminMealProgram) {
        setBusyId(program.id);
        try {
            const { share_url } = await memberApi<{ share_url: string | null }>(
                shareUrls[program.id]
                    ? unshare(program.id).url
                    : share(program.id).url,
                shareUrls[program.id] ? 'DELETE' : 'POST',
            );
            setShareUrls((map) => ({ ...map, [program.id]: share_url }));
            toast.success(
                share_url ? 'Share link created' : 'Share link revoked',
            );
        } catch {
            toast.error('Could not update share link');
        } finally {
            setBusyId(null);
        }
    }

    async function togglePublish(program: AdminMealProgram) {
        setBusyId(program.id);
        try {
            const { published } = await memberApi<{ published: boolean }>(
                publishedIds[program.id]
                    ? unpublish(program.id).url
                    : publish(program.id).url,
                publishedIds[program.id] ? 'DELETE' : 'POST',
            );
            setPublishedIds((map) => ({ ...map, [program.id]: published }));
            toast.success(
                published
                    ? 'Program listed in the catalog'
                    : 'Program removed from the catalog',
            );
        } catch {
            toast.error('Could not update publish state');
        } finally {
            setBusyId(null);
        }
    }

    function remove(program: AdminMealProgram) {
        confirm({
            title: `Delete "${program.name}"?`,
            description: 'This removes the meal program and cannot be undone.',
            confirmLabel: 'Delete',
            destructive: true,
            onConfirm: () => router.delete(destroy(program.id).url),
        });
    }

    return (
        <>
            <Head title="Admin — meal programs" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Meal programs"
                    description="Meal plan programs shared with members — Pro members can claim them"
                    actions={
                        <Button size="sm" asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New meal program
                            </Link>
                        </Button>
                    }
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            All meal programs
                        </CardTitle>
                        <CardDescription>
                            {programs.total.toLocaleString()} programs — create,
                            share and track claims
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {programs.data.length === 0 ? (
                            <EmptyState
                                title="No meal programs yet"
                                description="Create your first meal program to share or sell."
                                action={
                                    <Button size="sm" asChild>
                                        <Link href={create()}>
                                            <Plus className="size-4" />
                                            New meal program
                                        </Link>
                                    </Button>
                                }
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Name</TableHead>
                                        <TableHead className="text-right">
                                            Days
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Meals
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Claims
                                        </TableHead>
                                        <TableHead>Price</TableHead>
                                        <TableHead>Share link</TableHead>
                                        <TableHead className="w-10" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {programs.data.map((program) => (
                                        <TableRow key={program.id}>
                                            <TableCell>
                                                <Link
                                                    href={edit(program.id).url}
                                                    className="font-medium hover:underline"
                                                    prefetch
                                                >
                                                    {program.name}
                                                </Link>
                                                {publishedIds[program.id] && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="ml-2"
                                                    >
                                                        Published
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {program.days_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {program.meals_count}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {program.claims_count}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        program.is_free
                                                            ? 'secondary'
                                                            : 'default'
                                                    }
                                                >
                                                    {program.is_free
                                                        ? 'Free'
                                                        : `${program.price_etb.toLocaleString()} ETB`}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {shareUrls[program.id] ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            copyLink(program)
                                                        }
                                                        className="inline-flex items-center gap-1.5 text-xs text-lime-700 hover:underline dark:text-lime-400"
                                                    >
                                                        <Copy className="size-3.5" />
                                                        Copy link
                                                    </button>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            disabled={
                                                                busyId ===
                                                                program.id
                                                            }
                                                            aria-label={`Actions for ${program.name}`}
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={
                                                                    edit(
                                                                        program.id,
                                                                    ).url
                                                                }
                                                            >
                                                                <Pencil className="size-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                toggleShare(
                                                                    program,
                                                                )
                                                            }
                                                        >
                                                            {shareUrls[
                                                                program.id
                                                            ] ? (
                                                                <>
                                                                    <Link2Off className="size-4" />
                                                                    Revoke share
                                                                    link
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Link2 className="size-4" />
                                                                    Create share
                                                                    link
                                                                </>
                                                            )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                togglePublish(
                                                                    program,
                                                                )
                                                            }
                                                        >
                                                            {publishedIds[
                                                                program.id
                                                            ] ? (
                                                                <>
                                                                    <EyeOff className="size-4" />
                                                                    Unpublish
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Eye className="size-4" />
                                                                    Publish to
                                                                    catalog
                                                                </>
                                                            )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onClick={() =>
                                                                remove(program)
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}

                        <div className="mt-4">
                            <AdminPagination page={programs} />
                        </div>
                    </CardContent>
                </Card>
            </div>
            {confirmDialog}
        </>
    );
}

AdminMealProgramsIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: dashboard() },
        { title: 'Meal programs', href: index() },
    ],
};
