import { Head, Link } from '@inertiajs/react';
import { Package, Pencil, Plus } from 'lucide-react';
import { AdminPageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { create, edit } from '@/routes/admin/shop-products';

type Product = {
    id: number;
    name: string;
    category: string;
    price_etb: number;
    image_url: string | null;
    published: boolean;
    orders_count: number;
};

export default function ShopProductsIndex({
    products,
}: {
    products: Product[];
}) {
    return (
        <>
            <Head title="Admin — shop products" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <AdminPageHeader
                    title="Shop products"
                    description="Equipment and supplements listed in Explore."
                    actions={
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" /> New product
                            </Link>
                        </Button>
                    }
                />

                <Card>
                <CardHeader>
                    <CardTitle className="text-base">Catalog</CardTitle>
                </CardHeader>
                <CardContent className="divide-y p-0">
                    {products.length === 0 && (
                        <p className="px-6 py-8 text-sm text-muted-foreground">
                            No products yet.
                        </p>
                    )}
                    {products.map((product) => (
                        <div
                            key={product.id}
                            className="flex items-center gap-3 px-6 py-3"
                        >
                            <div className="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-muted">
                                {product.image_url ? (
                                    <img
                                        src={product.image_url}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <Package className="size-5 text-muted-foreground" />
                                )}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold">
                                    {product.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {product.category} ·{' '}
                                    {product.price_etb.toLocaleString()} ETB ·{' '}
                                    {product.orders_count} orders
                                </p>
                            </div>
                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase',
                                    product.published
                                        ? 'bg-lime-400/15 text-lime-500'
                                        : 'bg-muted text-muted-foreground',
                                )}
                            >
                                {product.published ? 'Live' : 'Draft'}
                            </span>
                            <Button asChild size="sm" variant="outline">
                                <Link href={edit(product.id)}>
                                    <Pencil className="size-3.5" /> Edit
                                </Link>
                            </Button>
                        </div>
                    ))}
                </CardContent>
                </Card>
            </div>
        </>
    );
}
