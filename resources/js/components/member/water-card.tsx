import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useLocalStorage } from '@/hooks/use-local-storage';
import { track } from '@/lib/tracking';

const GOAL_ML = 2000;

export function WaterCard({ dateKey }: { dateKey: string }) {
    const [waterByDate, setWaterByDate] = useLocalStorage<
        Record<string, number>
    >('member:water', {});

    const ml = waterByDate[dateKey] ?? 0;
    const addWater = (amount: number) => {
        setWaterByDate((current) => ({
            ...current,
            [dateKey]: (current[dateKey] ?? 0) + amount,
        }));
        track('water.logged', {
            ml_added: amount,
            total_ml: ml + amount,
            date: dateKey,
        });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between">
                <CardTitle className="text-base">Water</CardTitle>
                <span className="text-xs text-muted-foreground">
                    {ml}ml / {GOAL_ML / 1000}L
                </span>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-2">
                    <Button variant="outline" onClick={() => addWater(250)}>
                        +250 ml
                    </Button>
                    <Button variant="outline" onClick={() => addWater(500)}>
                        +500 ml
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
