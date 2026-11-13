import { Head, useForm } from '@inertiajs/react';
import { GripVertical, Plus, Trash2 } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/trainer';
import { update } from '@/routes/trainer/lead-form';

type Field = {
    id?: number;
    label: string;
    type: 'text' | 'textarea' | 'select';
    options: string[];
    required: boolean;
};

type Props = {
    mode: 'required' | 'optional' | 'off';
    usingDefaults: boolean;
    fields: Field[];
    defaultFields: Omit<Field, 'id'>[];
};

const MODE_OPTIONS = [
    {
        value: 'required',
        title: 'Required',
        body: 'Guests must answer before they can take a free program.',
    },
    {
        value: 'optional',
        title: 'Optional',
        body: 'The form is shown, but guests can skip it and claim anyway.',
    },
    {
        value: 'off',
        title: 'Off',
        body: 'No form — free programs can be claimed directly.',
    },
] as const;

const emptyField = (): Field => ({
    label: '',
    type: 'text',
    options: [],
    required: true,
});

export default function TrainerLeadForm({
    mode,
    usingDefaults,
    fields,
    defaultFields,
}: Props) {
    const { data, setData, patch, processing, errors } = useForm<{
        mode: string;
        fields: Field[];
    }>({
        mode,
        fields: fields.map((field) => ({ ...field })),
    });

    function updateField(index: number, patch: Partial<Field>) {
        setData(
            'fields',
            data.fields.map((field, i) =>
                i === index ? { ...field, ...patch } : field,
            ),
        );
    }

    function removeField(index: number) {
        setData(
            'fields',
            data.fields.filter((_, i) => i !== index),
        );
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();
        patch(update().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Claim form" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Claim form"
                    description="What guests answer before taking your free programs."
                />

                <form onSubmit={submit} className="grid max-w-3xl gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Form requirement
                            </CardTitle>
                            <CardDescription>
                                Applies to every free program you share.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2 sm:grid-cols-3">
                            {MODE_OPTIONS.map((option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() =>
                                        setData('mode', option.value)
                                    }
                                    className={`rounded-lg border p-3 text-left transition-colors ${
                                        data.mode === option.value
                                            ? 'border-lime-500 bg-lime-500/10'
                                            : 'hover:bg-muted'
                                    }`}
                                >
                                    <p className="text-sm font-semibold">
                                        {option.title}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {option.body}
                                    </p>
                                </button>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between space-y-0">
                            <div>
                                <CardTitle className="text-base">
                                    Questions
                                </CardTitle>
                                <CardDescription>
                                    {usingDefaults
                                        ? 'Using the built-in questionnaire — customize it below to make it yours.'
                                        : 'Ask for name and phone is always included.'}
                                </CardDescription>
                            </div>
                            {usingDefaults && (
                                <Badge variant="outline">Default</Badge>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {usingDefaults && data.fields.length === 0 && (
                                <div className="grid gap-2">
                                    <p className="text-sm text-muted-foreground">
                                        Guests currently see the default
                                        questionnaire: goal, experience, days
                                        per week, and health concerns. Add
                                        your own questions to replace it — or
                                        start from the defaults and edit.
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="w-fit"
                                        onClick={() =>
                                            setData(
                                                'fields',
                                                defaultFields.map((f) => ({
                                                    ...f,
                                                    options: [...f.options],
                                                })),
                                            )
                                        }
                                    >
                                        Start from defaults
                                    </Button>
                                </div>
                            )}

                            {data.fields.map((field, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border p-3"
                                >
                                    <div className="flex items-start gap-2">
                                        <GripVertical className="mt-2.5 size-4 shrink-0 text-muted-foreground" />
                                        <div className="grid flex-1 gap-1.5">
                                            <Input
                                                value={field.label}
                                                onChange={(event) =>
                                                    updateField(index, {
                                                        label: event.target
                                                            .value,
                                                    })
                                                }
                                                placeholder="Question, e.g. Have you trained before?"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `fields.${index}.label` as keyof typeof errors
                                                    ]
                                                }
                                            />
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-muted-foreground hover:text-destructive"
                                            onClick={() => removeField(index)}
                                            aria-label="Remove question"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-3 pl-6">
                                        <Select
                                            value={field.type}
                                            onValueChange={(value) =>
                                                updateField(index, {
                                                    type: value as Field['type'],
                                                    options:
                                                        value === 'select' &&
                                                        field.options.length ===
                                                            0
                                                            ? ['Yes', 'No']
                                                            : field.options,
                                                })
                                            }
                                        >
                                            <SelectTrigger className="h-8 w-36 text-xs">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="text">
                                                    Short answer
                                                </SelectItem>
                                                <SelectItem value="textarea">
                                                    Long answer
                                                </SelectItem>
                                                <SelectItem value="select">
                                                    Multiple choice
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>

                                        <label className="flex cursor-pointer items-center gap-2 text-xs">
                                            <Checkbox
                                                checked={field.required}
                                                onCheckedChange={(checked) =>
                                                    updateField(index, {
                                                        required:
                                                            checked === true,
                                                    })
                                                }
                                            />
                                            Required
                                        </label>
                                    </div>

                                    {field.type === 'select' && (
                                        <div className="grid gap-1.5 pl-6">
                                            <Label className="text-xs text-muted-foreground">
                                                Options (comma separated)
                                            </Label>
                                            <Input
                                                value={field.options.join(', ')}
                                                onChange={(event) =>
                                                    updateField(index, {
                                                        options: event.target.value
                                                            .split(',')
                                                            .map((o) =>
                                                                o.trim(),
                                                            )
                                                            .filter(Boolean),
                                                    })
                                                }
                                                placeholder="Yes, No, Maybe"
                                            />
                                            <InputError
                                                message={
                                                    errors[
                                                        `fields.${index}.options` as keyof typeof errors
                                                    ]
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                            ))}

                            <Button
                                type="button"
                                variant="outline"
                                className="w-fit"
                                disabled={data.fields.length >= 10}
                                onClick={() =>
                                    setData('fields', [
                                        ...data.fields,
                                        emptyField(),
                                    ])
                                }
                            >
                                <Plus className="size-4" />
                                Add question
                            </Button>
                        </CardContent>
                    </Card>

                    <div>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Save form
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

TrainerLeadForm.layout = {
    breadcrumbs: [{ title: 'Coach', href: dashboard() }],
};
