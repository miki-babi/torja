import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/**
 * Free-text chip input: type a name, press Enter or Add, get a removable
 * chip. Used by the wizard for custom food/ingredient lists.
 */
export function TextChips({
    values,
    onChange,
    placeholder,
}: {
    values: string[];
    onChange: (values: string[]) => void;
    placeholder?: string;
}) {
    const [query, setQuery] = useState('');

    function add() {
        const trimmed = query.trim();
        if (trimmed === '' || values.includes(trimmed)) {
            return;
        }
        onChange([...values, trimmed]);
        setQuery('');
    }

    return (
        <div className="grid gap-2">
            <div className="flex gap-2">
                <Input
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            add();
                        }
                    }}
                    placeholder={placeholder}
                    autoComplete="off"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={add}
                    disabled={query.trim() === ''}
                    aria-label="Add"
                >
                    <Plus className="size-4" />
                </Button>
            </div>
            {values.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {values.map((value) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() =>
                                onChange(values.filter((v) => v !== value))
                            }
                            className="inline-flex items-center gap-1 rounded-full border border-foreground bg-foreground px-2.5 py-1 text-xs text-background"
                        >
                            {value}
                            <X className="size-3" />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
