import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { RichTextEditor } from '@/components/rich-text-editor';
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
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index, store, update } from '@/routes/admin/shop-products';

const TEXTAREA_CLS =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

type Product = {
    id: number;
    name: string;
    name_am: string | null;
    description: string | null;
    description_am: string | null;
    category: string;
    price_etb: number;
    content: string | null;
    image_url: string | null;
    published: boolean;
} | null;

type ProductForm = {
    name: string;
    name_am: string;
    description: string;
    description_am: string;
    category: string;
    price_etb: string;
    content: string;
    published: boolean;
    image: File | null;
};

export default function ShopProductEdit({ product }: { product: Product }) {
    const title = product === null ? 'New product' : `Edit ${product.name}`;

    const form = useForm<ProductForm>({
        name: product?.name ?? '',
        name_am: product?.name_am ?? '',
        description: product?.description ?? '',
        description_am: product?.description_am ?? '',
        category: product?.category ?? 'equipment',
        price_etb: product?.price_etb?.toString() ?? '',
        content: product?.content ?? '',
        published: product?.published ?? false,
        image: null,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (product === null) {
            form.post(store().url, { preserveScroll: true });
        } else {
            form.put(update.url(product.id), { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title={`Admin — ${title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title={title}
                    description="Shop products appear in Explore while published."
                />

                <form
                    onSubmit={submit}
                    className="max-w-2xl space-y-4"
                    encType="multipart/form-data"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Details</CardTitle>
                            <CardDescription>
                                English and Amharic names are both shown
                                depending on member language.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        value={form.data.name}
                                        onChange={(e) =>
                                            form.setData('name', e.target.value)
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="name_am">
                                        Name (Amharic)
                                    </Label>
                                    <Input
                                        id="name_am"
                                        value={form.data.name_am}
                                        onChange={(e) =>
                                            form.setData(
                                                'name_am',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.name_am}
                                    />
                                </div>
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="description">Description</Label>
                                <textarea
                                    id="description"
                                    rows={2}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    className={TEXTAREA_CLS}
                                />
                                <InputError
                                    message={form.errors.description}
                                />
                            </div>
                            <div className="space-y-1.5">
                                <Label htmlFor="description_am">
                                    Description (Amharic)
                                </Label>
                                <textarea
                                    id="description_am"
                                    rows={2}
                                    value={form.data.description_am}
                                    onChange={(e) =>
                                        form.setData(
                                            'description_am',
                                            e.target.value,
                                        )
                                    }
                                    className={TEXTAREA_CLS}
                                />
                                <InputError
                                    message={form.errors.description_am}
                                />
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-1.5">
                                    <Label htmlFor="category">Category</Label>
                                    <Select
                                        value={form.data.category}
                                        onValueChange={(v) =>
                                            form.setData('category', v)
                                        }
                                    >
                                        <SelectTrigger id="category">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="equipment">
                                                Equipment
                                            </SelectItem>
                                            <SelectItem value="supplement">
                                                Supplement
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={form.errors.category}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="price_etb">
                                        Price (ETB)
                                    </Label>
                                    <Input
                                        id="price_etb"
                                        type="number"
                                        min={1}
                                        value={form.data.price_etb}
                                        onChange={(e) =>
                                            form.setData(
                                                'price_etb',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.price_etb}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Rich content
                            </CardTitle>
                            <CardDescription>
                                Full product details shown on the product
                                page — supports headings, lists, links and
                                images.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <RichTextEditor
                                value={form.data.content}
                                onChange={(html) =>
                                    form.setData('content', html)
                                }
                            />
                            <InputError message={form.errors.content} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Image &amp; listing
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="image">Image</Label>
                                {product?.image_url && (
                                    <img
                                        src={product.image_url}
                                        alt=""
                                        className="mb-2 size-20 rounded-xl object-cover"
                                    />
                                )}
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) =>
                                        form.setData(
                                            'image',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={form.errors.image} />
                            </div>
                            <label
                                htmlFor="published"
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    id="published"
                                    checked={form.data.published}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'published',
                                            checked === true,
                                        )
                                    }
                                />
                                Publish to Explore catalog
                            </label>
                        </CardContent>
                    </Card>

                    <Button disabled={form.processing}>
                        {product === null
                            ? 'Create product'
                            : 'Save changes'}
                    </Button>
                </form>
            </div>
        </>
    );
}
