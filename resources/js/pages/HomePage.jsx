import { useEffect, useMemo, useState } from 'react';
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

// The first screen, board D: a greeting, four count cards that
// filter the table, the filter bar, and the sortable student table. The
// server does the filtering and the card counts; the browser only sorts.
export default function HomePage() {
    const { user } = useAuth();

    const [card, setCard] = useState(null);
    const [query, setQuery] = useState('');
    const [risk, setRisk] = useState('');
    const [year, setYear] = useState('');
    const [showNotes, setShowNotes] = useState(true);
    const [sort, setSort] = useState(DEFAULT_SORT);
    const [list, setList] = useState(null);
    const [error, setError] = useState('');

    const debouncedQuery = useDebouncedValue(query).trim();

    useEffect(() => {
        const params = new URLSearchParams();
        if (card) params.set('card', card);
        if (debouncedQuery) params.set('q', debouncedQuery);
        if (risk) params.set('risk', risk);
        if (year) params.set('year', year);

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
    }, [card, debouncedQuery, risk, year]);

    // A column the filter bar narrows to one value has nothing left to sort,
    // so it is locked and the sort falls back to "Contact due".
    // Clearing the filter brings the chosen sort back.
    const lockedKeys = [risk && 'risk', year && 'year'].filter(Boolean);
    const effectiveSort = lockedKeys.includes(sort.key) ? { key: 'due', dir: 'asc' } : sort;

    // Depends on the sort's key and direction, not the object, which is new
    // on every render.
    const rows = useMemo(
        () => (list ? sortStudents(list.rows, effectiveSort) : []),
        [list, effectiveSort.key, effectiveSort.dir],
    );

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

    return (
        <main className="container-xl" style={{ paddingTop: 32, paddingBottom: 48 }}>
            <h1 className="text-blue mt-0 mb-1">{user.is_admin ? 'All advisors' : `Welcome back, ${user.display_name}`}</h1>
            <p className="text-body-secondary mb-0">
                {meta.today_label} · {meta.caseload} students{user.is_admin ? '' : ' in your caseload'}
            </p>

            <CountCards counts={meta.counts} fourthCard={meta.fourth_card} caseload={meta.caseload} selected={card} onSelect={setCard} />

            <div className="d-flex justify-content-end align-items-center" style={{ minHeight: 32, marginTop: 10 }}>
                {card ? (
                    <button
                        type="button"
                        className="btn btn-link p-0 d-inline-flex align-items-center fw-semibold gap-1"
                        style={{ fontSize: '0.875rem' }}
                        aria-label="Clear the card filter and show all students"
                        onClick={() => setCard(null)}
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
                    onShowNotes={setShowNotes}
                />
                <StudentTable rows={rows} sort={effectiveSort} onSort={handleSort} lockedKeys={lockedKeys} showNotes={showNotes} />
            </section>
        </main>
    );
}
