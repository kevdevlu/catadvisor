import { createContext, useEffect, useState } from 'react';
import { api } from './api';
import { DEFAULT_SORT, sortStudents } from './students';

// True while a student is open in the side panel. A list link then replaces
// the panel's history entry instead of adding one, so Back still closes the
// panel rather than stepping through every student opened.
export const PanelOpenContext = createContext(false);

// The student list's order as it is on screen (student ids), which the
// list keeps up to date for the side panel's Previous / Next. null while no
// list is showing.
export const ListOrderContext = createContext({ order: null, setOrder: () => {} });

// Where a student link goes, and what it carries. From the list,
// a student opens in the side panel: the list stays behind it
// (backgroundLocation), and Previous / Next follow the list's current order
// (order: student ids, as sorted and filtered on screen).
export function panelLink(studentId, { background, order, hash = '' }) {
    return {
        to: { pathname: `/students/${studentId}`, hash },
        state: { backgroundLocation: background, order, openedFromList: true },
    };
}

// The order Previous / Next follow: the one the link carried, or, for a page
// opened from a typed or pasted address, the list's first order (all
// students, High risk first).
export function useStudentOrder(carried) {
    const [order, setOrder] = useState(carried ?? null);

    useEffect(() => {
        if (carried) {
            setOrder(carried);
            return;
        }

        let current = true;

        api('/api/students')
            .then((response) => current && setOrder(sortStudents(response.data, DEFAULT_SORT).map((row) => row.id)))
            .catch(() => current && setOrder([]));

        return () => {
            current = false;
        };
    }, [carried]);

    return order;
}
