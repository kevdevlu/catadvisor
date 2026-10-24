// Sorting and wording for the first screen's table. The API sends
// the numbers (days since contact, days until due), so nothing here does
// date math.

const YEAR_ORDER = { Freshman: 0, Sophomore: 1, Junior: 2, Senior: 3 };

// Never-contacted students sort as the most overdue, and the oldest contact.
const NEVER = -100000;

// Each column's sort key. Ascending means: High risk first, A to Z, oldest
// contact first, most overdue first.
const SORT_KEYS = {
    risk: (row) => row.risk.rank,
    name: (row) => `${row.last_name} ${row.first_name}`.toLowerCase(),
    major: (row) => row.major.toLowerCase(),
    year: (row) => YEAR_ORDER[row.class_year],
    gpa: (row) => Number(row.gpa),
    last: (row) => (row.last_contact ? -row.last_contact.days_ago : NEVER),
    due: (row) => row.days_until_due ?? NEVER,
};

export function sortStudents(rows, { key, dir }) {
    const sortKey = SORT_KEYS[key];
    const direction = dir === 'desc' ? -1 : 1;

    return [...rows].sort((a, b) => {
        const x = sortKey(a);
        const y = sortKey(b);

        if (x < y) return -direction;
        if (x > y) return direction;

        // Ties go to whoever is most overdue.
        return SORT_KEYS.due(a) - SORT_KEYS.due(b);
    });
}

function days(n) {
    return `${n} ${n === 1 ? 'day' : 'days'}`;
}

// The "Contact due" column's words, for example "206 days overdue".
export function contactDueText(row) {
    if (row.never_contacted) return 'Never contacted';
    if (row.days_until_due < 0) return `${days(-row.days_until_due)} overdue`;
    if (row.days_until_due === 0) return 'Due today';
    return `Due in ${days(row.days_until_due)}`;
}

export function isOverdue(row) {
    return row.never_contacted || row.days_until_due < 0;
}
