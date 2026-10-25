// The filter bar above the table. The search label says exactly what it
// searches.
export default function StudentFilters({ searchRef, query, onQuery, risk, onRisk, year, onYear, showNotes, onShowNotes }) {
    return (
        <div className="d-flex flex-wrap align-items-end bg-warm-gray rounded-top border-bottom p-3" style={{ gap: 12 }}>
            <div style={{ flex: '2 1 280px' }}>
                <label htmlFor="student-search" className="form-label small fw-semibold mb-1">
                    Search
                </label>
                <input
                    id="student-search"
                    ref={searchRef}
                    type="search"
                    className="form-control"
                    placeholder="Name, student ID, email, or major"
                    value={query}
                    onChange={(event) => onQuery(event.target.value)}
                />
            </div>
            <div style={{ flex: '1 1 150px' }}>
                <label htmlFor="risk-filter" className="form-label small fw-semibold mb-1">
                    Risk
                </label>
                <select id="risk-filter" className="form-select" value={risk} onChange={(event) => onRisk(event.target.value)}>
                    <option value="">All</option>
                    <option value="high">High</option>
                    <option value="medium">Medium</option>
                    <option value="low">Low</option>
                </select>
            </div>
            <div style={{ flex: '1 1 150px' }}>
                <label htmlFor="year-filter" className="form-label small fw-semibold mb-1">
                    Class year
                </label>
                <select id="year-filter" className="form-select" value={year} onChange={(event) => onYear(event.target.value)}>
                    <option value="">All</option>
                    <option value="Freshman">Freshman</option>
                    <option value="Sophomore">Sophomore</option>
                    <option value="Junior">Junior</option>
                    <option value="Senior">Senior</option>
                </select>
            </div>
            <div className="form-check form-switch mb-2" style={{ flex: '0 0 auto' }}>
                <input
                    id="show-notes"
                    className="form-check-input"
                    type="checkbox"
                    role="switch"
                    checked={showNotes}
                    onChange={(event) => onShowNotes(event.target.checked)}
                />
                <label className="form-check-label fw-semibold" htmlFor="show-notes">
                    Show latest note
                </label>
            </div>
        </div>
    );
}
