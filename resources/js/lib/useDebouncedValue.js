import { useEffect, useState } from 'react';

// Returns value only after it has stopped changing for delay milliseconds,
// so typing in the search box sends one request instead of one per letter.
export function useDebouncedValue(value, delay = 250) {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}
