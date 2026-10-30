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

    const percentage = Math.min(100, Math.round((ml / GOAL_ML) * 100));

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between pb-2">
                <CardTitle className="text-base font-bold text-[#F5F5F5]">Water</CardTitle>
                <span className="text-xs font-semibold text-[#AFFA01]">
                    {ml}ml / {GOAL_ML / 1000}L
                </span>
            </CardHeader>
            <CardContent className="space-y-3">
                {/* Progress Bar */}
                <div className="h-2 w-full overflow-hidden rounded-full bg-[#2E2E2E]">
                    <div
                        className="h-full rounded-full bg-gradient-to-r from-[#D7F457] to-[#AFFA01] transition-all duration-300"
                        style={{ width: `${percentage}%` }}
                    />
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <Button
                        variant="secondary"
                        onClick={() => addWater(250)}
                        className="h-10 text-xs font-bold"
                    >
                        +250 ml
                    </Button>
                    <Button
                        variant="secondary"
                        onClick={() => addWater(500)}
                        className="h-10 text-xs font-bold"
                    >
                        +500 ml
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
