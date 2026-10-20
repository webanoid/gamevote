import { useCallback, useLayoutEffect, useRef } from 'react';

type Key = string | number;

/**
 * Slide list items from their old position to their new one when the list is reordered
 * (the FLIP technique), so a re-sort is visible instead of rows jumping under the cursor.
 *
 * Attach the returned ref callback to each item; it is the same function for a key on every
 * render, so it doesn't make memoized items re-render. Positions are read with offsetTop, so
 * the list should be the items' offset parent (give it `position: relative`).
 */
export function useFlipAnimation<T extends HTMLElement>(order: readonly Key[]) {
    const elements = useRef(new Map<Key, T>());
    const positions = useRef(new Map<Key, number>());
    const refCallbacks = useRef(new Map<Key, (element: T | null) => void>());
    const orderKey = order.join(',');

    useLayoutEffect(() => {
        const reduceMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        const next = new Map<Key, number>();

        elements.current.forEach((element, key) => {
            const top = element.offsetTop;
            const previous = positions.current.get(key);
            next.set(key, top);

            if (reduceMotion || previous === undefined || previous === top) {
                return;
            }

            element.animate(
                [
                    { transform: `translateY(${previous - top}px)` },
                    { transform: 'translateY(0)' },
                ],
                { duration: 300, easing: 'cubic-bezier(0.2, 0, 0, 1)' },
            );
        });

        positions.current = next;
    }, [orderKey]);

    return useCallback((key: Key) => {
        let callback = refCallbacks.current.get(key);

        if (!callback) {
            callback = (element: T | null) => {
                if (element) {
                    elements.current.set(key, element);
                } else {
                    elements.current.delete(key);
                    refCallbacks.current.delete(key);
                }
            };
            refCallbacks.current.set(key, callback);
        }

        return callback;
    }, []);
}
