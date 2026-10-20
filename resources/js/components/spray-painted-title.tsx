import { cn } from '@/lib/utils';

/** Paint runs under the title, as [left position %, length in rem]. */
const drips: Array<[number, number]> = [
    [9, 1.4],
    [27, 2.4],
    [46, 1.1],
    [63, 2],
    [84, 1.6],
];

export default function SprayPaintedTitle({
    title,
    description,
    className,
}: {
    title: string;
    description?: string;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'graffiti-wall overflow-hidden rounded-xl border px-5 pt-6 pb-8 sm:px-8',
                className,
            )}
        >
            <h1 className="spray-paint relative inline-block -rotate-2 text-4xl leading-tight sm:text-6xl">
                {title}
                <span aria-hidden="true">
                    {drips.map(([left, length]) => (
                        <span
                            key={left}
                            className="paint-drip"
                            style={
                                {
                                    left: `${left}%`,
                                    '--drip': `${length}rem`,
                                } as React.CSSProperties
                            }
                        />
                    ))}
                </span>
            </h1>
            {description && (
                <p className="mt-4 max-w-md rounded-md bg-background/80 px-3 py-2 text-sm text-muted-foreground backdrop-blur-sm">
                    {description}
                </p>
            )}
        </div>
    );
}
