import { Camera, ChevronLeft, PenLine } from 'lucide-react';
import { useRef, useState } from 'react';
import type { DiaryEntry } from '@/components/member/types';
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

function parseNumber(value: string): number {
    const parsed = Number.parseFloat(value);
    return Number.isNaN(parsed) ? 0 : parsed;
}

type NewEntry = Omit<DiaryEntry, 'id'>;
type Mode = 'choose' | 'photo' | 'manual';

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
    const [name, setName] = useState('');
    const [detail, setDetail] = useState('');
    const [kcal, setKcal] = useState('');
    const [grams, setGrams] = useState('100');
    const [photos, setPhotos] = useState<string[]>([]);
    const [pending, setPending] = useState<NewEntry[]>([]);
    const fileInput = useRef<HTMLInputElement>(null);

    function handleOpenChange(next: boolean) {
        if (!next) {
            setMode('choose');
            setName('');
            setDetail('');
            setKcal('');
            setGrams('100');
            setPhotos([]);
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

    function handleAddManual() {
        const trimmed = name.trim();
        if (trimmed === '') {
            return;
        }
        queueEntry({
            name: trimmed,
            detail: detail.trim(),
            kcal: parseNumber(kcal),
            protein: 0,
            carbs: 0,
            fat: 0,
        });
        setName('');
        setDetail('');
        setKcal('');
    }

    function handleAddPhoto() {
        const g = parseNumber(grams) || 100;
        queueEntry({
            name: name.trim() || 'Meal photo',
            detail: `${g} g · photo`,
            kcal: parseNumber(kcal),
            protein: 0,
            carbs: 0,
            fat: 0,
            photos: photos.length > 0 ? photos : undefined,
        });
        setPhotos([]);
        setName('');
        setKcal('');
        setMode('choose');
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Log food</DialogTitle>
                    <DialogDescription>
                        Snap a photo or type what you ate.
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
                            onClick={() => setMode('manual')}
                            className="flex flex-col items-center gap-2 rounded-xl border p-6 hover:bg-accent"
                        >
                            <PenLine className="size-6 text-lime-600" />
                            <span className="text-sm font-medium">
                                Type a name
                            </span>
                        </button>
                    </div>
                )}

                {mode === 'manual' && (
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="manual-name">Name</Label>
                            <Input
                                id="manual-name"
                                autoFocus
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        handleAddManual();
                                    }
                                }}
                                placeholder="e.g. Enjera with shiro"
                                autoComplete="off"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="grid gap-2">
                                <Label htmlFor="manual-detail">
                                    Portion (optional)
                                </Label>
                                <Input
                                    id="manual-detail"
                                    value={detail}
                                    onChange={(e) => setDetail(e.target.value)}
                                    placeholder="e.g. 1 roll, 2 cups"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="manual-kcal">
                                    Calories (optional, kcal)
                                </Label>
                                <Input
                                    id="manual-kcal"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    value={kcal}
                                    onChange={(e) => setKcal(e.target.value)}
                                    placeholder="Estimate if you know it"
                                />
                            </div>
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
                                onClick={handleAddManual}
                                disabled={name.trim() === ''}
                            >
                                Add to list
                            </Button>
                        </div>
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
                            <div className="grid gap-2">
                                <Label htmlFor="photo-name">Name</Label>
                                <Input
                                    id="photo-name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="e.g. Enjera"
                                    autoComplete="off"
                                />
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
                                value={kcal}
                                onChange={(e) => setKcal(e.target.value)}
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
            </DialogContent>
        </Dialog>
    );
}
