import { AtSign, Mail, Phone, type LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type IdentifierType = 'email' | 'phone' | 'username';

export const IDENTIFIER_META: Record<
    IdentifierType,
    {
        label: string;
        type: string;
        placeholder: string;
        autoComplete: string;
        inputMode: 'email' | 'tel' | 'text';
        icon: LucideIcon;
    }
> = {
    email: {
        label: 'Email',
        type: 'email',
        placeholder: 'email@example.com',
        autoComplete: 'email',
        inputMode: 'email',
        icon: Mail,
    },
    phone: {
        label: 'Phone',
        type: 'tel',
        placeholder: '+251 9XX XXX XXX',
        autoComplete: 'tel',
        inputMode: 'tel',
        icon: Phone,
    },
    username: {
        label: 'Username',
        type: 'text',
        placeholder: '@handle',
        autoComplete: 'username',
        inputMode: 'text',
        icon: AtSign,
    },
};

const ORDER: IdentifierType[] = ['email', 'phone', 'username'];

/**
 * Segmented control that picks which identifier a field collects — icons
 * for every option, the label only on the active one.
 */
export function IdentifierToggle({
    value,
    onChange,
}: {
    value: IdentifierType;
    onChange: (type: IdentifierType) => void;
}) {
    return (
        <div className="grid grid-cols-3 rounded-full bg-muted p-1">
            {ORDER.map((type) => {
                const { icon: Icon, label } = IDENTIFIER_META[type];
                const active = value === type;

                return (
                    <button
                        key={type}
                        type="button"
                        onClick={() => onChange(type)}
                        aria-pressed={active}
                        aria-label={label}
                        title={label}
                        className={cn(
                            'flex items-center justify-center gap-1.5 rounded-full px-2 py-1.5 text-sm font-medium',
                            active
                                ? 'bg-lime-400 text-lime-950'
                                : 'text-muted-foreground',
                        )}
                    >
                        <Icon className="size-4 shrink-0" />
                        {active && label}
                    </button>
                );
            })}
        </div>
    );
}
