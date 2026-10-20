import type { SVGProps } from 'react';

/** A classic board game meeple silhouette. */
export default function Meeple(props: SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 100 100" aria-hidden="true" {...props}>
            <path
                fill="currentColor"
                d="M50 6c-9 0-15 7-15 15 0 5 2 9 5 12-12 3-27 8-33 14-4 4-1 10 4 10 8 0 16-2 22-4L19 85c-2 5 1 9 6 9h14c4 0 7-2 8-6l3-12 3 12c1 4 4 6 8 6h14c5 0 8-4 6-9L67 53c6 2 14 4 22 4 5 0 8-6 4-10-6-6-21-11-33-14 3-3 5-7 5-12 0-8-6-15-15-15z"
            />
        </svg>
    );
}
