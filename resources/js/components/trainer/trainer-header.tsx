import { Breadcrumbs } from '@/components/breadcrumbs';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';
import { Menu } from 'lucide-react';

export function TrainerHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { toggleSidebar } = useSidebar();

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    className="-ml-1 h-9 w-9 border-lime-500/50 bg-lime-500/10 text-lime-600 shadow-sm hover:bg-lime-500/20 hover:text-lime-500 dark:text-lime-400 dark:hover:text-lime-300"
                    onClick={toggleSidebar}
                    aria-label="Open menu"
                >
                    <Menu className="size-5" />
                </Button>
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <Badge
                variant="outline"
                className="ml-auto border-lime-500/40 text-[10px] font-semibold tracking-wide text-lime-600 uppercase dark:text-lime-400"
            >
                Coach
            </Badge>
        </header>
    );
}
