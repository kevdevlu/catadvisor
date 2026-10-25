import { CheckIcon } from './icons';

// The four count cards above the table. Each card is a button that filters
// the table; clicking the selected card again clears the filter.
// Hover and selected styles live in app.css (.count-card).
//
// The day counts in the words come from the API's rules, so they
// always match the numbers the server counts with.
const CARDS = {
    due: {
        label: () => 'Due for outreach',
        note: () => 'past target, no reply, or follow-up needed',
        numberClass: 'text-red',
    },
    high_overdue: {
        label: () => 'High risk, past target',
        note: ({ rules }) => `target: every ${rules.targets.find((target) => target.value === 'high').days} days`,
        numberClass: 'text-chili',
    },
    waiting: {
        label: () => 'Waiting on a reply',
        note: () => 'last outreach went unanswered',
        numberClass: 'text-blue',
    },
    never: {
        label: () => 'Never contacted',
        note: () => 'no contact on record',
        numberClass: 'text-chili',
    },
    recent: {
        label: ({ rules }) => `Contacted, last ${rules.recent_days} days`,
        note: ({ caseload }) => `of ${caseload} students`,
        numberClass: 'text-blue',
    },
};

export default function CountCards({ counts, fourthCard, caseload, rules, selected, onSelect }) {
    const keys = ['due', 'high_overdue', 'waiting', fourthCard];

    return (
        <div className="count-cards">
            {keys.map((key) => {
                const card = CARDS[key];
                const isSelected = selected === key;

                return (
                    <button
                        key={key}
                        data-card={key}
                        type="button"
                        className={`card count-card text-start w-100${isSelected ? ' is-selected' : ''}`}
                        aria-pressed={isSelected}
                        onClick={() => onSelect(isSelected ? null : key)}
                    >
                        <span className="d-flex align-items-start justify-content-between w-100 gap-2">
                            <span className="count-card-label small fw-semibold text-uppercase">{card.label({ rules })}</span>
                            {isSelected && <CheckIcon />}
                        </span>
                        <span className={`count-card-number d-block fw-bold ${isSelected ? '' : card.numberClass}`}>{counts[key]}</span>
                        <span className="count-card-note small d-block">{card.note({ caseload, rules })}</span>
                    </button>
                );
            })}
        </div>
    );
}
