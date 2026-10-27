import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

/**
 * Prev/next pagination controls for Laravel paginators.
 */
export function AdminPagination<T>({ page }: { page: Paginated<T> }) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span className="tabular-nums">
                Page {page.current_page} of {page.last_page} · {page.total}{' '}
                total
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    disabled={!page.prev_page_url}
                >
                    {page.prev_page_url ? (
                        <Link href={page.prev_page_url}>
                            <ChevronLeft className="size-4" />
                            Previous
                        </Link>
                    ) : (
                        <span>
                            <ChevronLeft className="size-4" />
                            Previous
                        </span>
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    disabled={!page.next_page_url}
                >
                    {page.next_page_url ? (
                        <Link href={page.next_page_url}>
                            Next
                            <ChevronRight className="size-4" />
                        </Link>
                    ) : (
                        <span>
                            Next
                            <ChevronRight className="size-4" />
                        </span>
                    )}
                </Button>
            </div>
        </div>
    );
}
