import { Link } from 'react-router';
import { contactDueText, isOverdue } from '../lib/students';
import ContactDueHelp from './ContactDueHelp';
import { FilterIcon, PhoneIcon, SortIcon } from './icons';

// The student table on board D. Every column sorts; a column the
// filter bar has narrowed to one value shows a grayed filter icon instead.
const COLUMNS = [
    { key: 'risk', label: 'Risk' },
    { key: 'name', label: 'Student' },
    { key: 'major', label: 'Major' },
    { key: 'year', label: 'Year' },
    { key: 'gpa', label: 'GPA', className: 'text-end' },
    { key: 'last', label: 'Last contact' },
    { key: 'due', label: 'Contact due' },
];

const RISK_BADGE = { high: 'text-bg-chili', medium: 'text-bg-warning', low: 'text-bg-light' };

export default function StudentTable({ rows, sort, onSort, lockedKeys, showNotes }) {
    return (
        <div className="table-responsive-lg">
            <table className={`table align-middle mb-0 caseload-table${showNotes ? ' show-notes' : ''}`}>
                <caption className="small" style={{ padding: '12px 16px' }}>
                    Click a column heading to sort.
                </caption>
                <thead className="table-light">
                    <tr>
                        {COLUMNS.map((column) => (
                            <HeaderCell
                                key={column.key}
                                column={column}
                                sort={sort}
                                onSort={onSort}
                                locked={lockedKeys.includes(column.key)}
                            />
                        ))}
                    </tr>
                </thead>

                {rows.map((row) => (
                    <StudentRow key={row.id} row={row} showNotes={showNotes} />
                ))}

                {rows.length === 0 && (
                    <tbody>
                        <tr>
                            <td colSpan={COLUMNS.length} className="text-center text-body-secondary" style={{ padding: 32 }}>
                                No students match. Clear the search and filters, or pick another card.
                            </td>
                        </tr>
                    </tbody>
                )}
            </table>
        </div>
    );
}

function HeaderCell({ column, sort, onSort, locked }) {
    const active = sort.key === column.key;
    const ariaSort = active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none';

    return (
        <th scope="col" className={`position-relative text-nowrap ${column.className ?? ''}`} aria-sort={locked ? undefined : ariaSort}>
            {locked ? (
                <span
                    className="fw-bold d-inline-flex align-items-center gap-1"
                    style={{ minHeight: 44, border: '1px solid transparent' }}
                    title="Filtered to one value, so sorting is off"
                >
                    {column.label}
                    <FilterIcon />
                    <span className="visually-hidden">(filtered, not sortable)</span>
                </span>
            ) : (
                <button
                    type="button"
                    className="btn btn-link p-0 fw-bold text-reset text-decoration-none d-inline-flex align-items-center gap-1"
                    style={{ minHeight: 44 }}
                    onClick={() => onSort(column.key)}
                >
                    {column.label}
                    <SortIcon direction={active ? sort.dir : null} />
                </button>
            )}
            {column.key === 'due' && <ContactDueHelp />}
        </th>
    );
}

// One student: the main row, plus the gray last-note line when the switch is
// on. Each student gets its own <tbody> so the name's stretched link (a
// Bootstrap class that makes the whole area clickable) covers both rows.
function StudentRow({ row, showNotes }) {
    return (
        <tbody className="position-relative">
            <tr>
                <td>
                    <span className={`badge ${RISK_BADGE[row.risk.value]}`}>{row.risk.label}</span>
                </td>
                <td>
                    <Link to={`/students/${row.id}`} className="fw-semibold text-blue stretched-link student-link">
                        {row.last_name}, {row.first_name}
                    </Link>
                    <div className="small text-body-secondary d-flex align-items-center flex-wrap" style={{ gap: 6 }}>
                        <span>{row.sis_id}</span>
                        {row.contact_preference && (
                            // Raised above the stretched link, so hovering it shows the full preference.
                            <span
                                className="badge bg-white text-azurite border d-inline-flex align-items-center gap-1 position-relative"
                                style={{ zIndex: 2 }}
                                title={`Contact preference: ${row.contact_preference}`}
                            >
                                <PhoneIcon />
                                Contact preference
                            </span>
                        )}
                    </div>
                </td>
                <td>{row.major}</td>
                <td>{row.class_year}</td>
                <td className="text-end">{row.gpa}</td>
                <td>
                    {row.last_contact ? (
                        <>
                            <div>{row.last_contact.label}</div>
                            <div className="small text-body-secondary">{daysAgoText(row.last_contact.days_ago)}</div>
                        </>
                    ) : (
                        <span className="text-body-secondary">None</span>
                    )}
                </td>
                <td className="text-nowrap">
                    <div className={isOverdue(row) ? 'fw-semibold text-chili' : 'text-body-secondary'}>{contactDueText(row)}</div>
                    {(row.waiting || row.follow_up_due) && (
                        <div className="d-flex flex-wrap mt-1" style={{ gap: 4 }}>
                            {row.waiting && <span className="badge bg-white text-azurite border">No reply</span>}
                            {row.follow_up_due && <span className="badge bg-white text-azurite border">Follow-up due</span>}
                        </div>
                    )}
                </td>
            </tr>
            {showNotes && (
                <tr className="last-note-row">
                    <td colSpan={COLUMNS.length} className="small text-body-secondary">
                        {lastNoteText(row.last_note)}
                    </td>
                </tr>
            )}
        </tbody>
    );
}

function daysAgoText(days) {
    if (days === 0) return 'Today';
    return `${days} ${days === 1 ? 'day' : 'days'} ago`;
}

// "Last note (Jun 2, Email): …", in board A's wording. A private note's words
// only come from the API for its author; others see "Private note".
function lastNoteText(note) {
    if (!note) return 'No notes yet.';
    return `Last note (${note.date_label}, ${note.type}): ${note.text ?? 'Private note'}`;
}
