import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
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
import type { BroadcastPoll } from '@/types';

const TEXTAREA_CLS =
    'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring';

/**
 * sendPoll composer — question, 2–10 options, regular or quiz mode with
 * a correct answer, anonymity, multiple answers, explanation and the
 * auto-close open period.
 */
export function PollBuilder({
    poll,
    onChange,
}: {
    poll: BroadcastPoll;
    onChange: (poll: BroadcastPoll) => void;
}) {
    function patch(value: Partial<BroadcastPoll>) {
        onChange({ ...poll, ...value });
    }

    return (
        <div className="space-y-3">
            <div className="space-y-1.5">
                <Label>Question</Label>
                <textarea
                    className={TEXTAREA_CLS}
                    maxLength={300}
                    value={poll.question}
                    placeholder="What should we launch next?"
                    onChange={(e) => patch({ question: e.target.value })}
                />
            </div>

            <div className="space-y-2">
                <Label>Options ({poll.options.length}/10)</Label>
                {poll.options.map((option, i) => (
                    <div key={i} className="flex items-center gap-2">
                        <Input
                            value={option}
                            maxLength={100}
                            placeholder={`Option ${i + 1}`}
                            onChange={(e) =>
                                patch({
                                    options: poll.options.map((o, j) =>
                                        j === i ? e.target.value : o,
                                    ),
                                })
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={poll.options.length <= 2}
                            onClick={() => {
                                const options = poll.options.filter(
                                    (_, j) => j !== i,
                                );
                                patch({
                                    options,
                                    correct_option_id:
                                        poll.correct_option_id === i
                                            ? null
                                            : poll.correct_option_id != null &&
                                                poll.correct_option_id > i
                                              ? poll.correct_option_id - 1
                                              : poll.correct_option_id,
                                });
                            }}
                        >
                            <Trash2 className="size-3" />
                        </Button>
                    </div>
                ))}
                {poll.options.length < 10 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            patch({ options: [...poll.options, ''] })
                        }
                    >
                        <Plus className="size-4" /> Add option
                    </Button>
                )}
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1.5">
                    <Label>Poll type</Label>
                    <Select
                        value={poll.type}
                        onValueChange={(v) =>
                            patch({ type: v as BroadcastPoll['type'] })
                        }
                    >
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="regular">Regular</SelectItem>
                            <SelectItem value="quiz">Quiz</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                {poll.type === 'quiz' && (
                    <div className="space-y-1.5">
                        <Label>Correct answer</Label>
                        <Select
                            value={poll.correct_option_id?.toString() ?? ''}
                            onValueChange={(v) =>
                                patch({ correct_option_id: Number(v) })
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pick one" />
                            </SelectTrigger>
                            <SelectContent>
                                {poll.options.map((option, i) => (
                                    <SelectItem key={i} value={i.toString()}>
                                        {option !== ''
                                            ? option
                                            : `Option ${i + 1}`}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                )}
                <div className="space-y-1.5">
                    <Label>Auto-close after (seconds)</Label>
                    <Input
                        type="number"
                        min={5}
                        max={600}
                        placeholder="5–600, optional"
                        value={poll.open_period?.toString() ?? ''}
                        onChange={(e) =>
                            patch({
                                open_period:
                                    e.target.value === ''
                                        ? null
                                        : Number(e.target.value),
                            })
                        }
                    />
                </div>
            </div>

            <div className="flex flex-wrap gap-4">
                <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                        checked={poll.is_anonymous}
                        onCheckedChange={(v) =>
                            patch({ is_anonymous: v === true })
                        }
                    />
                    Anonymous
                </label>
                {poll.type === 'regular' && (
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={poll.allows_multiple_answers}
                            onCheckedChange={(v) =>
                                patch({ allows_multiple_answers: v === true })
                            }
                        />
                        Allow multiple answers
                    </label>
                )}
            </div>

            <div className="space-y-1.5">
                <Label>Explanation (shown after answering)</Label>
                <Input
                    value={poll.explanation ?? ''}
                    maxLength={200}
                    placeholder="Optional"
                    onChange={(e) => patch({ explanation: e.target.value })}
                />
            </div>
        </div>
    );
}
