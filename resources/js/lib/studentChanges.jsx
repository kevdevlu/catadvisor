import { createContext, useCallback, useContext, useMemo, useState } from 'react';

// Tells the list behind the side panel that a student changed in the panel
// (a note saved, a follow-up set), so the list reloads its rows and counts,
// keeping its filters and sort. version goes up by one on each
// change.
const StudentChangesContext = createContext({ version: 0, changed: () => {} });

export function StudentChangesProvider({ children }) {
    const [version, setVersion] = useState(0);
    const changed = useCallback(() => setVersion((count) => count + 1), []);
    const value = useMemo(() => ({ version, changed }), [version, changed]);

    return <StudentChangesContext.Provider value={value}>{children}</StudentChangesContext.Provider>;
}

export function useStudentChanges() {
    return useContext(StudentChangesContext);
}
