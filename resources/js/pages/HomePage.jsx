import { useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import CountCards from '../components/CountCards';
import { XIcon } from '../components/icons';
import StudentFilters from '../components/StudentFilters';
import StudentTable from '../components/StudentTable';
import { api } from '../lib/api';
import { sortStudents } from '../lib/students';
import { useDebouncedValue } from '../lib/useDebouncedValue';

// High risk first, then most overdue.
const DEFAULT_SORT = { key: 'risk', dir: 'asc' };

// "Show last notes" starts off, and each account's choice is remembered in
// this browser. Storage can be blocked (a private window, or
// site data turned off), so a failed read just means off, and a failed
// write is ignored.
function notesKey(user) {
    return `cat-advisor:show-last-notes:${user.username}`;
}

function loadShowNotes(user) {
    try {
        return localStorage.getItem(notesKey(user)) === 'on';
    } catch {
        return false;
    }
}

function saveShowNotes(user, on) {
    try {
        localStorage.setItem(notesKey(user), on ? 'on' : 'off');
    } catch {
        // Not remembered; the switch still works for this visit.
    }
}

// The first screen, board D: a greeting, four count cards that
// filter the table, the filter bar, and the sortable student table. The
// server does the filtering and the card counts; the browser only sorts.
export default function HomePage() {
    const { user } = useAuth();

    const [card, setCard] = useState(null);
    const [query, setQuery] = useState('');
    const [risk, setRisk] = useState('');
    const [year, setYear] = useState('');
    const [showNotes, setShowNotes] = useState(() => loadShowNotes(user));
    // The admin's "Advisor" menu: a username, or '' for all.
    const [advisor, setAdvisor] = useState('');
    const [sort, setSort] = useState(DEFAULT_SORT);
    const [list, setList] = useState(null);
    const [error, setError] = useState('');
    const cardsRef = useRef(null);

    const debouncedQuery = useDebouncedValue(query).trim();

    useEffect(() => {
        const params = new URLSearchParams();
        if (card) params.set('card', card);
        if (debouncedQuery) params.set('q', debouncedQuery);
        if (risk) params.set('risk', risk);
        if (year) params.set('year', year);
        if (advisor) params.set('advisor', advisor);

        // Ignore a slow answer to an older request once a newer one is sent.
        let current = true;

        api(`/api/students?${params}`)
            .then((response) => {
                if (current) {
                    setList({ rows: response.data, meta: response.meta });
                    setError('');
                }
            })
            .catch(() => current && setError('The student list did not load. Reload the page to try again.'));

        return () => {
            current = false;
        };
    }, [card, debouncedQuery, risk, year, advisor]);

    // A column the filter bar narrows to one value has nothing left to sort,
    // so it is locked and the sort falls back to "Contact due".
    // Clearing the filter brings the chosen sort back. The same goes for the
    // Advisor column, which shows only while the admin views all advisors.
    const showAdvisor = user.is_admin && !advisor;
    const lockedKeys = [risk && 'risk', year && 'year'].filter(Boolean);
    const sortable = !lockedKeys.includes(sort.key) && (sort.key !== 'advisor' || showAdvisor);
    const effectiveSort = sortable ? sort : { key: 'due', dir: 'asc' };

    // Depends on the sort's key and direction, not the object, which is new
    // on every render.
    const rows = useMemo(
        () => (list ? sortStudents(list.rows, effectiveSort) : []),
        [list, effectiveSort.key, effectiveSort.dir],
    );

    // "Clear" disappears once it's used, so keyboard focus moves to the card
    // that was selected instead of being lost to the top of the page.
    function clearCard() {
        cardsRef.current?.querySelector(`[data-card="${card}"]`)?.focus();
        setCard(null);
    }

    function handleShowNotes(on) {
        setShowNotes(on);
        saveShowNotes(user, on);
    }

    function handleSort(key) {
        setSort({ key, dir: effectiveSort.key === key && effectiveSort.dir === 'asc' ? 'desc' : 'asc' });
    }

    if (!list) {
        return error ? (
            <main className="container-xl py-4">
                <div className="alert alert-danger">{error}</div>
            </main>
        ) : (
            <p className="container-xl py-4 text-body-secondary">Loading…</p>
        );
    }

    const { meta } = list;

    // "Welcome back, Reyes" for an advisor; "All advisors" or "Okafor's
    // students" for the admin.
    let heading = `Welcome back, ${user.display_name}`;
    if (user.is_admin) heading = meta.advisor ? `${meta.advisor}'s students` : 'All advisors';

    return (
        <main className="container-xl" style={{ paddingTop: 32, paddingBottom: 48 }}>
            <div className="d-flex flex-wrap align-items-end justify-content-between" style={{ gap: 16 }}>
                <div>
                    <h1 className="text-blue mb-1">{heading}</h1>
                    <p className="text-body-secondary mb-0">
                        {meta.today_label} · {meta.caseload} students{user.is_admin ? '' : ' in your caseload'}
                    </p>
                </div>
                {user.is_admin && (
                    <div style={{ flex: '0 0 240px' }}>
                        <label htmlFor="advisor-filter" className="form-label small fw-semibold mb-1">
                            Advisor
                        </label>
                        <select id="advisor-filter" className="form-select" value={advisor} onChange={(event) => setAdvisor(event.target.value)}>
                            <option value="">All advisors</option>
                            {meta.advisors.map((option) => (
                                <option key={option.username} value={option.username}>
                                    {option.display_name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
            </div>

            <CountCards
                ref={cardsRef}
                counts={meta.counts}
                fourthCard={meta.fourth_card}
                caseload={meta.caseload}
                rules={meta.rules}
                selected={card}
                onSelect={setCard}
            />

            <div className="d-flex align-items-center" style={{ minHeight: 32, marginTop: 10 }}>
                {card ? (
                    <button
                        type="button"
                        className="btn btn-link p-0 d-inline-flex align-items-center fw-semibold gap-1"
                        style={{ fontSize: '0.875rem' }}
                        aria-label="Clear the card filter and show all students"
                        onClick={clearCard}
                    >
                        <XIcon />
                        Clear
                    </button>
                ) : (
                    <span className="small text-body-secondary">
                        {rows.length === meta.caseload
                            ? `Showing all ${meta.caseload} students`
                            : `Showing ${rows.length} of ${meta.caseload} students`}
                    </span>
                )}
            </div>

            {error && <div className="alert alert-warning mt-2">{error}</div>}

            <section className="card border-0 shadow-sm" style={{ marginTop: 6 }}>
                <StudentFilters
                    query={query}
                    onQuery={setQuery}
                    risk={risk}
                    onRisk={setRisk}
                    year={year}
                    onYear={setYear}
                    showNotes={showNotes}
                    onShowNotes={handleShowNotes}
                />
                <StudentTable
                    rows={rows}
                    sort={effectiveSort}
                    onSort={handleSort}
                    lockedKeys={lockedKeys}
                    showNotes={showNotes}
                    showAdvisor={showAdvisor}
                />
            </section>
        </main>
    );
}
