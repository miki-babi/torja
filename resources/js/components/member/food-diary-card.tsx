import { Plus, X } from 'lucide-react';
import type { DiaryEntry } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export function FoodDiaryCard({
    entries,
    onLogFood,
    onRemove,
}: {
    entries: DiaryEntry[];
    onLogFood: () => void;
    onRemove: (id: string) => void;
}) {
    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">Food diary</CardTitle>
                <Button variant="ghost" size="sm" onClick={onLogFood}>
                    <Plus className="size-4" />
                    Log food
                </Button>
            </CardHeader>
            <CardContent>
                {entries.length === 0 ? (
                    <div className="py-8 text-center">
                        <p className="text-sm font-medium">Nothing logged</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Log your first meal of the day
                        </p>
                    </div>
                ) : (
                    <div className="divide-y">
                        {entries.map((entry) => (
                            <div
                                key={entry.id}
                                className="flex items-center gap-3 py-2.5"
                            >
                                {(() => {
                                    const photos =
                                        entry.photos ??
                                        (entry.photo !== undefined
                                            ? [entry.photo]
                                            : []);
                                    return (
                                        photos.length > 0 && (
                                            <div className="relative shrink-0">
                                                <img
                                                    src={photos[0]}
                                                    alt=""
                                                    className="size-10 rounded-md object-cover"
                                                />
                                                {photos.length > 1 && (
                                                    <span className="absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-full bg-foreground text-[9px] text-background">
                                                        {photos.length}
                                                    </span>
                                                )}
                                            </div>
                                        )
                                    );
                                })()}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">
                                        {entry.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {entry.detail}
                                        {entry.detail !== '' && ' · '}
                                        {Math.round(entry.kcal)} kcal ·{' '}
                                        {Math.round(entry.protein)}P{' '}
                                        {Math.round(entry.carbs)}C{' '}
                                        {Math.round(entry.fat)}F
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 shrink-0"
                                    onClick={() => onRemove(entry.id)}
                                    aria-label={`Remove ${entry.name}`}
                                >
                                    <X className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
