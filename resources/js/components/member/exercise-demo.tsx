import { cn } from '@/lib/utils';

/**
 * Exercise demo media: a looping muted <video> for trainer-uploaded
 * clips, an <img> for the dataset's GIFs — falling back to the still
 * thumbnail when no demo exists.
 */
export function ExerciseDemo({
    gifUrl,
    imageUrl,
    isVideo,
    alt = '',
    className,
}: {
    gifUrl: string | null;
    imageUrl: string;
    isVideo?: boolean;
    alt?: string;
    className?: string;
}) {
    if (isVideo && gifUrl !== null && gifUrl !== '') {
        return (
            <video
                src={gifUrl}
                autoPlay
                muted
                loop
                playsInline
                className={cn('bg-white', className)}
            />
        );
    }

    return (
        <img
            src={gifUrl !== null && gifUrl !== '' ? gifUrl : imageUrl}
            alt={alt}
            loading="lazy"
            className={className}
        />
    );
}
