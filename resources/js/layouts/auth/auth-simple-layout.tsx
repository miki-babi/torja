import { Card, CardContent } from '@/components/ui/card';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({ children }: AuthLayoutProps) {
    return (
        <div className="min-h-screen bg-background">
            <div className="mx-auto flex w-full max-w-md flex-col px-4 pt-6 pb-8">
                <Card>
                    <CardContent className="pt-6">{children}</CardContent>
                </Card>
            </div>
        </div>
    );
}
