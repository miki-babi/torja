import { X } from 'lucide-react';
import {
    bodyPartIcon,
    bodyPartImage,
    type BodyPart,
} from '@/components/member/exercise-data';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Translated label for a body-part tile id ('' is the "Full body" tile),
 * falling back to the raw catalog value when no line exists.
 */
export function useBodyPartLabel(): (id: string) => string {
    const { t } = useTranslation();

    return (id: string) => {
        const key = `exercise_values.body_part.${id === '' ? 'full body' : id}`;
        const text = t(key);
        return text === key ? id : text;
    };
}

/**
 * Body-part filter for the exercise pickers: a tile grid where the '' id
 * ("Full body") clears the selection, plus removable chips for the current
 * selection. Multiple selections are allowed.
 */
export function BodyPartPicker({
    bodyParts,
    value,
    onChange,
    large = false,
}: {
    bodyParts: BodyPart[];
    value: string[];
    onChange: (value: string[]) => void;
    large?: boolean;
}) {
    const partLabel = useBodyPartLabel();

    return (
        <>
            <div
                className={cn(
                    'grid gap-2',
                    large ? 'grid-cols-4' : 'grid-cols-5',
                )}
            >
                {bodyParts.map(({ id }) => {
                    const Icon = bodyPartIcon(id);
                    const image = bodyPartImage(id);
                    const active =
                        id === '' ? value.length === 0 : value.includes(id);
                    return (
                        <button
                            key={id}
                            type="button"
                            onClick={() =>
                                onChange(
                                    id === ''
                                        ? []
                                        : value.includes(id)
                                          ? value.filter((p) => p !== id)
                                          : [...value, id],
                                )
                            }
                            title={partLabel(id)}
                            className={cn(
                                'flex aspect-square flex-col items-center justify-center gap-1 rounded-2xl border px-1 transition-all',
                                active
                                    ? 'border-[#AFFA01] bg-[#AFFA01]/10 text-[#AFFA01]'
                                    : 'border-[#2E2E2E] bg-[#1F1F1F] text-[#8E8E93] hover:border-[#AFFA01]/40 hover:text-[#F5F5F5]',
                            )}
                        >
                            {image !== null ? (
                                <img
                                    src={image}
                                    alt=""
                                    className={cn(
                                        'shrink-0 object-contain',
                                        large ? 'size-14' : 'size-10',
                                    )}
                                />
                            ) : (
                                <Icon
                                    className={cn(
                                        'shrink-0',
                                        large ? 'size-7' : 'size-5',
                                    )}
                                />
                            )}
                            <span
                                className={cn(
                                    'leading-none font-semibold',
                                    large ? 'text-xs' : 'text-[10px]',
                                )}
                            >
                                {partLabel(id)}
                            </span>
                        </button>
                    );
                })}
            </div>
            {value.length > 0 && (
                <div className="mt-2.5 flex flex-wrap gap-1.5">
                    {value.map((id) => {
                        const chipLabel = partLabel(id);
                        return (
                            <button
                                key={id}
                                type="button"
                                onClick={() =>
                                    onChange(value.filter((p) => p !== id))
                                }
                                aria-label={`Remove ${chipLabel} filter`}
                                className="flex items-center gap-1 rounded-full border border-[#AFFA01]/40 bg-[#AFFA01]/10 px-2.5 py-1 text-xs font-semibold text-[#AFFA01] capitalize transition-colors hover:bg-[#AFFA01]/20"
                            >
                                {chipLabel}
                                <X className="size-3" />
                            </button>
                        );
                    })}
                </div>
            )}
        </>
    );
}
