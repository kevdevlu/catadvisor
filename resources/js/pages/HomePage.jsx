import { useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../auth/AuthContext';
import AdvisorPicker from '../components/AdvisorPicker';
import CountCards from '../components/CountCards';
import { XIcon } from '../components/icons';
import StudentFilters from '../components/StudentFilters';
import StudentTable from '../components/StudentTable';
import { api } from '../lib/api';
import { useStudentChanges } from '../lib/studentChanges';
import { DEFAULT_SORT, sortStudents } from '../lib/students';
import { useDebouncedValue } from '../lib/useDebouncedValue';

// "Show latest note" starts off, and each account's choice is remembered in
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

// The first screen: a greeting, four count cards that filter the table, the
// filter bar, and the sortable student table. The server does the filtering
// and the card counts; the browser only sorts.
export default function HomePage() {
    const { user } = useAuth();
    // Goes up when a student changes in the side panel, to reload the rows.
    const { version: changes } = useStudentChanges();

    const [card, setCard] = useState(null);
    const [query, setQuery] = useState('');
    const [risk, setRisk] = useState('');
    const [year, setYear] = useState('');
    const [showNotes, setShowNotes] = useState(() => loadShowNotes(user));
    // The admin's advisor picker: the usernames picked, or null
    // for all advisors.
    const [advisors, setAdvisors] = useState(null);
    const advisorsKey = advisors?.join(',') ?? '';
    const [sort, setSort] = useState(DEFAULT_SORT);
    const [list, setList] = useState(null);
    const [error, setError] = useState('');
    const searchRef = useRef(null);

    const debouncedQuery = useDebouncedValue(query).trim();

    useEffect(() => {
        const params = new URLSearchParams();
        if (card) params.set('card', card);
        if (debouncedQuery) params.set('q', debouncedQuery);
        if (risk) params.set('risk', risk);
        if (year) params.set('year', year);
        advisors?.forEach((username) => params.append('advisors[]', username));

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
    }, [card, debouncedQuery, risk, year, advisorsKey, changes]);

    // A column the filter bar narrows to one value has nothing left to sort,
    // so it is locked and the sort falls back to "Status". Clearing the
    // filter brings the chosen sort back. The Advisor column shows only for
    // the admin, whoever is picked, and always sorts.
    const showAdvisor = user.is_admin;
    const lockedKeys = [risk && 'risk', year && 'year'].filter(Boolean);
    const sortable = !lockedKeys.includes(sort.key) && (sort.key !== 'advisor' || showAdvisor);
    const effectiveSort = sortable ? sort : { key: 'due', dir: 'asc' };

    // Depends on the sort's key and direction, not the object, which is new
    // on every render.
    const rows = useMemo(
        () => (list ? sortStudents(list.rows, effectiveSort) : []),
        [list, effectiveSort.key, effectiveSort.dir],
    );

    // Any filter on: a card, the search box, Risk, or Class year. The
    // admin's Advisor menu isn't one: it picks whose students to look at.
    const filtered = Boolean(card || query.trim() || risk || year);

    // "Clear filters" turns them all off. It disappears once used, so
    // keyboard focus moves to the search box, a natural place to start
    // again, instead of being lost to the top of the page.
    function clearFilters() {
        setCard(null);
        setQuery('');
        setRisk('');
        setYear('');
        searchRef.current?.focus();
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

    // "Welcome back, Reyes" and "… · 17 students in your caseload" for an
    // advisor. For the admin, "Advising office" and whose students show:
    // "All advisors", "Advisor: Okafor", or "Advisors: Okafor, Reyes", every
    // name listed.
    let heading = `Welcome back, ${user.display_name}`;
    let whose = 'in your caseload';
    const picked = user.is_admin ? (advisors ?? meta.advisors.map((advisor) => advisor.username)) : [];

    if (user.is_admin) {
        const names = meta.advisors.filter((advisor) => picked.includes(advisor.username)).map((advisor) => advisor.display_name);
        heading = 'Advising office';
        whose = advisors === null ? '· All advisors' : `· ${names.length === 1 ? 'Advisor' : 'Advisors'}: ${names.join(', ')}`;
    }

    return (
        <main className="container-xl" style={{ paddingTop: 32, paddingBottom: 48 }}>
            <div className="d-flex flex-wrap align-items-end justify-content-between" style={{ gap: 16 }}>
                <div>
                    <h1 className="text-blue mb-1">{heading}</h1>
                    <p className="text-body-secondary mb-0">
                        {meta.today_label} · {meta.caseload} students {whose}
                    </p>
                </div>
                {user.is_admin && (
                    <div style={{ flex: '0 0 240px' }}>
                        <AdvisorPicker
                            advisors={meta.advisors}
                            selected={picked}
                            onChange={(usernames) => setAdvisors(usernames.length === meta.advisors.length ? null : usernames)}
                        />
                    </div>
                )}
            </div>

            <CountCards
                counts={meta.counts}
                fourthCard={meta.fourth_card}
                caseload={meta.caseload}
                rules={meta.rules}
                selected={card}
                onSelect={setCard}
            />

            {/* "Showing 3 of 50 students · Clear filters", or "Showing all 50
                students" when no filter is on. */}
            <div className="d-flex align-items-center flex-wrap" style={{ minHeight: 32, marginTop: 10, gap: 8 }}>
                <span className="small text-body-secondary">
                    {filtered ? `Showing ${rows.length} of ${meta.caseload} students` : `Showing all ${meta.caseload} students`}
                </span>
                {filtered && (
                    <>
                        <span className="small text-body-secondary" aria-hidden="true">
                            ·
                        </span>
                        <button
                            type="button"
                            className="btn btn-link p-0 d-inline-flex align-items-center fw-semibold gap-1"
                            style={{ fontSize: '0.875rem' }}
                            aria-label="Clear all filters and show all students"
                            onClick={clearFilters}
                        >
                            <XIcon />
                            Clear filters
                        </button>
                    </>
                )}
            </div>

            {error && <div className="alert alert-warning mt-2">{error}</div>}

            <section className="card border-0 shadow-sm" style={{ marginTop: 6 }}>
                <StudentFilters
                    searchRef={searchRef}
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
