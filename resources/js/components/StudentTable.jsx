import { useState } from 'react';
import { Link } from 'react-router';
import { isPastTarget, statusText } from '../lib/students';
import BadgePopover from './BadgePopover';
import { FilterIcon, NoteIcon, PanelRightOpenIcon, PhoneIcon, SortIcon } from './icons';
import StatusHelp from './StatusHelp';

// The student table on board D. Every column sorts; a column the
// filter bar has narrowed to one value shows a grayed filter icon instead.
// The admin gets an Advisor column while viewing all advisors.
const COLUMNS = [
    { key: 'risk', label: 'Risk' },
    { key: 'name', label: 'Student' },
    { key: 'advisor', label: 'Advisor', adminOnly: true },
    { key: 'major', label: 'Major' },
    { key: 'year', label: 'Year' },
    { key: 'gpa', label: 'GPA', className: 'text-end' },
    { key: 'last', label: 'Last contact' },
    { key: 'due', label: 'Status' },
];

const RISK_BADGE = { high: 'text-bg-chili', medium: 'text-bg-warning', low: 'text-bg-light' };

export default function StudentTable({ rows, sort, onSort, lockedKeys, showNotes, showAdvisor }) {
    const columns = COLUMNS.filter((column) => !column.adminOnly || showAdvisor);

    // Which badge popover is open, as "studentId:kind". One at a time.
    const [openBadge, setOpenBadge] = useState(null);

    return (
        <div className="table-responsive-lg">
            <table className={`table align-middle mb-0 caseload-table${showNotes ? ' show-notes' : ''}`}>
                <caption className="small" style={{ padding: '12px 16px' }}>
                    Click a column heading to sort.
                </caption>
                <thead className="table-light">
                    <tr>
                        {columns.map((column) => (
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
                    <StudentRow
                        key={row.id}
                        row={row}
                        columnCount={columns.length}
                        showNotes={showNotes}
                        showAdvisor={showAdvisor}
                        openBadge={openBadge}
                        onOpenBadge={setOpenBadge}
                    />
                ))}

                {rows.length === 0 && (
                    <tbody>
                        <tr>
                            <td colSpan={columns.length} className="text-center text-body-secondary" style={{ padding: 32 }}>
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
            {column.key === 'due' && <StatusHelp />}
        </th>
    );
}

// One student: the main row, plus the gray last-note line when the switch is
// on. Only the name opens the student, so the rest of the row is plain text
// an advisor can select and copy.
function StudentRow({ row, columnCount, showNotes, showAdvisor, openBadge, onOpenBadge }) {
    // Props for one badge's popover: open when it's the table's open badge.
    function badge(kind) {
        const key = `${row.id}:${kind}`;
        return { id: `badge-${key}`, open: openBadge === key, onToggle: (open) => onOpenBadge(open ? key : null) };
    }

    return (
        <tbody>
            <tr>
                <td>
                    <span className={`badge ${RISK_BADGE[row.risk.value]}`}>{row.risk.label}</span>
                </td>
                <td>
                    <Link to={`/students/${row.id}`} className="fw-semibold text-blue student-link">
                        {row.last_name}, {row.first_name}
                        <PanelRightOpenIcon className="student-link-icon" />
                    </Link>
                    <div className="small text-body-secondary d-flex align-items-center flex-wrap" style={{ gap: 6 }}>
                        <span>{row.sis_id}</span>
                        {row.contact_preference && (
                            <BadgePopover {...badge('preference')} label="Contact preference" icon={<PhoneIcon />} title="Contact preference">
                                <p className="mb-1">{row.contact_preference.text}</p>
                                <PreferenceSource studentId={row.id} source={row.contact_preference.source} />
                            </BadgePopover>
                        )}
                    </div>
                </td>
                {showAdvisor && <td>{row.advisor}</td>}
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
                    {/* The target chip on its own line, the other reasons below. */}
                    <BadgePopover
                        {...badge('status')}
                        label={statusText(row)}
                        title="Contact target"
                        tone={isPastTarget(row) ? 'text-chili is-strong' : 'text-body-secondary'}
                        align="end"
                    >
                        <p className="mb-1">{contactText(row)}</p>
                        <div className="small text-body-secondary">
                            Target is every {row.risk.target_days} days for a {row.risk.label} risk student.
                        </div>
                    </BadgePopover>
                    {(row.waiting || row.follow_up_due) && (
                        <div className="d-flex flex-wrap mt-1" style={{ gap: 4 }}>
                            {row.waiting && (
                                <BadgePopover {...badge('waiting')} label="No reply" title="Waiting on a reply" align="end">
                                    <p className="mb-1">Since {row.waiting.since_label}</p>
                                    {row.waiting.note_text && <div className="note-excerpt small mb-2">{row.waiting.note_text}</div>}
                                    <div className="small text-body-secondary">
                                        Set by AI from <NoteLink studentId={row.id} source={row.waiting.source} />
                                    </div>
                                </BadgePopover>
                            )}
                            {row.follow_up_due && (
                                <BadgePopover {...badge('follow-up')} label="Follow-up needed" title="Next follow-up" align="end">
                                    <p className="mb-1">{followUpText(row.follow_up)}</p>
                                    <FollowUpSource studentId={row.id} source={row.follow_up.source} />
                                </BadgePopover>
                            )}
                        </div>
                    )}
                </td>
            </tr>
            {showNotes && (
                <tr className="last-note-row">
                    <td colSpan={columnCount} className="small text-body-secondary">
                        {lastNoteText(row.last_note)}
                    </td>
                </tr>
            )}
        </tbody>
    );
}

// "By Oct 9, 2026: Call to talk about scholarship", or just the reason when
// the follow-up has no date.
function followUpText(followUp) {
    const reason = followUp.reason_private ? '(private)' : followUp.reason;
    return followUp.date_label ? `By ${followUp.date_label}: ${reason}` : reason;
}

// The Status chip's popover, for example "236 days since last contact
// (Feb 11, 2026)." The API sends the dates, so nothing here does date math.
function contactText(row) {
    if (row.never_contacted) return 'No contact on record.';

    const since = `${row.last_contact.days_ago} ${row.last_contact.days_ago === 1 ? 'day' : 'days'} since last contact (${row.last_contact.label}).`;
    return row.days_until_due < 0 ? since : `${since} Contact by ${row.contact_by_label}.`;
}

// "the Jun 2 email note", linked to that note on the student's page, where
// it scrolls into view and flashes. A deleted note can't be
// shown, so its name is plain text.
function NoteLink({ studentId, source }) {
    if (!source.note_id) {
        return <>the {source.note_name}</>;
    }

    return (
        <>
            the{' '}
            <Link to={{ pathname: `/students/${studentId}`, hash: `#note-${source.note_id}` }} className="source-link">
                {source.note_name}
                <NoteIcon />
            </Link>
        </>
    );
}

// "From the Jan 22, 2026 phone note", or "Set by Reyes on Oct 5, 2026" when
// typed in by hand.
function PreferenceSource({ studentId, source }) {
    if (source.note_name) {
        return (
            <div className="small text-body-secondary">
                From <NoteLink studentId={studentId} source={source} />
            </div>
        );
    }

    return source.set_by ? <div className="small text-body-secondary">{`Set by ${source.set_by} on ${source.set_on}`}</div> : null;
}

// "Set by AI from the Sep 23 in-person note", or "Set by Reyes" when typed
// in by hand.
function FollowUpSource({ studentId, source }) {
    const who = source.set_by ? `Set by ${source.set_by}` : 'Set by AI';

    return (
        <div className="small text-body-secondary">
            {who}
            {source.note_name && (
                <>
                    {' '}
                    from <NoteLink studentId={studentId} source={source} />
                </>
            )}
        </div>
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
