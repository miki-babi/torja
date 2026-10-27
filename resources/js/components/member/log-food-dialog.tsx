import { Camera, ChevronLeft, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { DiaryEntry, FoodSearchResult } from '@/components/member/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { search } from '@/routes/member/foods';

function parseNumber(value: string): number {
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

type NewEntry = Omit<DiaryEntry, 'id'>;
type Mode = 'choose' | 'photo' | 'search';

function compressImage(file: File): Promise<string> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            const max = 640;
            const scale = Math.min(1, max / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas
                .getContext('2d')
                ?.drawImage(img, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(img.src);
            resolve(canvas.toDataURL('image/jpeg', 0.7));
        };
        img.onerror = reject;
        img.src = URL.createObjectURL(file);
    });
}

export function LogFoodDialog({
    open,
    onOpenChange,
    onAdd,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAdd: (entry: NewEntry) => void;
}) {
    const [mode, setMode] = useState<Mode>('choose');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<FoodSearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<FoodSearchResult | null>(null);
    const [grams, setGrams] = useState('100');
    const [photos, setPhotos] = useState<string[]>([]);
    const [photoName, setPhotoName] = useState('');
    const [photoKcal, setPhotoKcal] = useState('');
    const [photoSuggestions, setPhotoSuggestions] = useState<
        FoodSearchResult[]
    >([]);
    const [matchedFood, setMatchedFood] = useState<FoodSearchResult | null>(
        null,
    );
    const [pending, setPending] = useState<NewEntry[]>([]);
    const fileInput = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const q = query.trim();
        if (mode !== 'search' || q.length < 2 || selected !== null) {
            setResults([]);
            return;
        }
        setLoading(true);
        const timer = setTimeout(() => {
            fetch(`${search().url}?q=${encodeURIComponent(q)}`)
                .then((response) =>
                    response.ok ? response.json() : { foods: [] },
                )
                .then((data) => setResults(data.foods ?? []))
                .catch(() => setResults([]))
                .finally(() => setLoading(false));
        }, 250);
        return () => clearTimeout(timer);
    }, [query, selected, mode]);

    // Autocomplete the photo-entry name against the food database.
    useEffect(() => {
        const q = photoName.trim();
        if (mode !== 'photo' || q.length < 2 || matchedFood !== null) {
            setPhotoSuggestions([]);
            return;
        }
        const timer = setTimeout(() => {
            fetch(`${search().url}?q=${encodeURIComponent(q)}`)
                .then((response) =>
                    response.ok ? response.json() : { foods: [] },
                )
                .then((data) =>
                    setPhotoSuggestions((data.foods ?? []).slice(0, 5)),
                )
                .catch(() => setPhotoSuggestions([]));
        }, 250);
        return () => clearTimeout(timer);
    }, [photoName, mode, matchedFood]);

    function handleOpenChange(next: boolean) {
        if (!next) {
            setMode('choose');
            setQuery('');
            setResults([]);
            setSelected(null);
            setGrams('100');
            setPhotos([]);
            setPhotoName('');
            setPhotoKcal('');
            setPhotoSuggestions([]);
            setMatchedFood(null);
            setPending([]);
        }
        onOpenChange(next);
    }

    function queueEntry(entry: NewEntry) {
        setPending((list) => [...list, entry]);
    }

    function commitAll() {
        pending.forEach(onAdd);
        handleOpenChange(false);
    }

    async function handlePhoto(file: File | undefined) {
        if (!file) {
            return;
        }
        try {
            const compressed = await compressImage(file);
            setPhotos((list) => [...list, compressed]);
        } catch {
            // Unreadable image — ignore.
        }
        // Allow picking the same file again.
        if (fileInput.current) {
            fileInput.current.value = '';
        }
    }

    function pickSuggestion(food: FoodSearchResult) {
        const g = parseNumber(grams) || 100;
        setPhotoName(food.name_en);
        setMatchedFood(food);
        setPhotoSuggestions([]);
        setPhotoKcal(
            String(Math.round((food.kcal_per_100g ?? 0) * (g / 100) * 10) / 10),
        );
    }

    function handleAddDb() {
        if (selected === null) {
            return;
        }
        const g = parseNumber(grams) || 100;
        const scale = g / 100;
        queueEntry({
            name: selected.name_en,
            detail: `${g} g`,
            kcal: (selected.kcal_per_100g ?? 0) * scale,
            protein: (selected.protein_g ?? 0) * scale,
            carbs: (selected.carbs_g ?? 0) * scale,
            fat: (selected.fat_g ?? 0) * scale,
        });
        setSelected(null);
        setQuery('');
    }

    function handleAddPhoto() {
        const g = parseNumber(grams) || 100;
        queueEntry({
            name: photoName.trim() || 'Meal photo',
            detail: `${g} g · photo`,
            kcal: parseNumber(photoKcal),
            protein: 0,
            carbs: 0,
            fat: 0,
            photos: photos.length > 0 ? photos : undefined,
        });
        setPhotos([]);
        setPhotoName('');
        setPhotoKcal('');
        setPhotoSuggestions([]);
        setMatchedFood(null);
        setMode('choose');
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Log food</DialogTitle>
                    <DialogDescription>
                        Snap a photo or search the food database.
                    </DialogDescription>
                </DialogHeader>

                {pending.length > 0 && (
                    <div className="rounded-md border">
                        <div className="divide-y">
                            {pending.map((entry, i) => (
                                <div
                                    key={i}
                                    className="flex items-center gap-2 px-3 py-1.5 text-sm"
                                >
                                    <span className="min-w-0 flex-1 truncate">
                                        {entry.name}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {Math.round(entry.kcal)} kcal
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setPending((list) =>
                                                list.filter((_, j) => j !== i),
                                            )
                                        }
                                        aria-label={`Remove ${entry.name}`}
                                        className="text-muted-foreground"
                                    >
                                        ×
                                    </button>
                                </div>
                            ))}
                        </div>
                        <div className="border-t p-2">
                            <Button
                                onClick={commitAll}
                                className="w-full bg-lime-400 text-lime-950 hover:bg-lime-500"
                            >
                                Log {pending.length} item
                                {pending.length === 1 ? '' : 's'}
                            </Button>
                        </div>
                    </div>
                )}

                {mode === 'choose' && (
                    <div className="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setMode('photo');
                                fileInput.current?.click();
                            }}
                            className="flex flex-col items-center gap-2 rounded-xl border p-6 hover:bg-accent"
                        >
                            <Camera className="size-6 text-lime-600" />
                            <span className="text-sm font-medium">
                                Take a photo
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => setMode('search')}
                            className="flex flex-col items-center gap-2 rounded-xl border p-6 hover:bg-accent"
                        >
                            <Search className="size-6 text-lime-600" />
                            <span className="text-sm font-medium">
                                Search database
                            </span>
                        </button>
                    </div>
                )}

                {mode === 'photo' && (
                    <div className="grid gap-4">
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="hidden"
                            onChange={(e) => handlePhoto(e.target.files?.[0])}
                        />
                        {photos.length === 0 ? (
                            <button
                                type="button"
                                onClick={() => fileInput.current?.click()}
                                className="flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-muted-foreground hover:bg-accent"
                            >
                                <Camera className="size-8" />
                                <span className="text-sm">
                                    Tap to take a photo
                                </span>
                            </button>
                        ) : (
                            <div className="grid grid-cols-3 gap-2">
                                {photos.map((src, i) => (
                                    <div key={i} className="relative">
                                        <img
                                            src={src}
                                            alt={`Meal ${i + 1}`}
                                            className="h-24 w-full rounded-xl object-cover"
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setPhotos((list) =>
                                                    list.filter(
                                                        (_, j) => j !== i,
                                                    ),
                                                )
                                            }
                                            aria-label={`Remove photo ${i + 1}`}
                                            className="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-foreground text-xs text-background"
                                        >
                                            ×
                                        </button>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={() => fileInput.current?.click()}
                                    aria-label="Add another photo"
                                    className="flex h-24 items-center justify-center rounded-xl border border-dashed text-2xl text-muted-foreground hover:bg-accent"
                                >
                                    +
                                </button>
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-2">
                            <div className="relative grid gap-2">
                                <Label htmlFor="photo-name">Name</Label>
                                <Input
                                    id="photo-name"
                                    value={photoName}
                                    onChange={(e) => {
                                        setPhotoName(e.target.value);
                                        setMatchedFood(null);
                                    }}
                                    placeholder="e.g. Enjera"
                                    autoComplete="off"
                                />
                                {photoSuggestions.length > 0 && (
                                    <div className="absolute top-full right-0 left-0 z-10 mt-1 divide-y overflow-hidden rounded-md border bg-popover shadow-md">
                                        {photoSuggestions.map((food) => (
                                            <button
                                                key={food.id}
                                                type="button"
                                                className="flex w-full min-w-0 items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                                                onClick={() =>
                                                    pickSuggestion(food)
                                                }
                                            >
                                                <span className="min-w-0 flex-1 truncate">
                                                    {food.name_en}
                                                </span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {Math.round(
                                                        food.kcal_per_100g ?? 0,
                                                    )}{' '}
                                                    kcal/100g
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {matchedFood !== null && (
                                    <p className="text-xs text-muted-foreground">
                                        Matched — calories prefilled, edit
                                        anything.
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="photo-grams">Portion (g)</Label>
                                <Input
                                    id="photo-grams"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    value={grams}
                                    onChange={(e) => setGrams(e.target.value)}
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="photo-kcal">
                                Calories (optional, kcal)
                            </Label>
                            <Input
                                id="photo-kcal"
                                type="number"
                                inputMode="decimal"
                                min="0"
                                value={photoKcal}
                                onChange={(e) => setPhotoKcal(e.target.value)}
                                placeholder="Estimate if you know it"
                            />
                        </div>
                        <div className="flex justify-between">
                            <Button
                                variant="ghost"
                                onClick={() => setMode('choose')}
                            >
                                <ChevronLeft className="size-4" />
                                Back
                            </Button>
                            <Button
                                onClick={handleAddPhoto}
                                disabled={photos.length === 0}
                            >
                                Add to list
                            </Button>
                        </div>
                    </div>
                )}

                {mode === 'search' &&
                    (selected === null ? (
                        <div className="grid min-w-0 gap-3">
                            <div className="flex items-center gap-2">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-8 shrink-0"
                                    onClick={() => setMode('choose')}
                                    aria-label="Back"
                                >
                                    <ChevronLeft className="size-4" />
                                </Button>
                                <Input
                                    autoFocus
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search foods…"
                                />
                            </div>
                            <div className="min-h-24 min-w-0">
                                {loading && (
                                    <div className="flex justify-center py-6">
                                        <Spinner className="size-5 text-muted-foreground" />
                                    </div>
                                )}
                                {!loading && results.length > 0 && (
                                    <div className="max-h-64 w-full divide-y overflow-x-hidden overflow-y-auto rounded-md border">
                                        {results.map((food) => (
                                            <button
                                                key={food.id}
                                                type="button"
                                                className="flex w-full min-w-0 items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                                                onClick={() =>
                                                    setSelected(food)
                                                }
                                            >
                                                <span className="min-w-0 flex-1 truncate">
                                                    {food.name_en}
                                                </span>
                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                    {Math.round(
                                                        food.kcal_per_100g ?? 0,
                                                    )}{' '}
                                                    kcal/100g
                                                </span>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                {!loading &&
                                    query.trim().length >= 2 &&
                                    results.length === 0 && (
                                        <p className="py-6 text-center text-sm text-muted-foreground">
                                            No foods found.
                                        </p>
                                    )}
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-4">
                            <div>
                                <p className="text-sm font-medium">
                                    {selected.name_en}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {Math.round(selected.kcal_per_100g ?? 0)}{' '}
                                    kcal per 100 g
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="grams">Portion (g)</Label>
                                <Input
                                    id="grams"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    value={grams}
                                    onChange={(e) => setGrams(e.target.value)}
                                />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="ghost"
                                    onClick={() => setSelected(null)}
                                >
                                    Back
                                </Button>
                                <Button onClick={handleAddDb}>
                                    Add to list
                                </Button>
                            </div>
                        </div>
                    ))}
            </DialogContent>
        </Dialog>
    );
}
