import { ChevronLeftIcon, ChevronRightIcon } from '../icons';

// Previous / Next through the student list, with the position ("3 of 17"),
// as on boards E and F. order is the list of student ids to walk
// through; onGo(id) opens that student. Nothing shows while the order loads,
// or if the student isn't in it.
export default function StudentNav({ order, currentId, onGo }) {
    const index = order ? order.indexOf(currentId) : -1;

    if (index === -1) {
        return null;
    }

    const previous = order[index - 1];
    const next = order[index + 1];

    return (
        <nav className="d-flex align-items-center" style={{ gap: 8 }} aria-label="Move through the student list">
            <button
                type="button"
                className="btn btn-sm btn-outline-blue d-inline-flex align-items-center"
                style={{ gap: 4 }}
                disabled={!previous}
                onClick={() => onGo(previous)}
            >
                <ChevronLeftIcon size={16} />
                Previous
            </button>
            <span className="small text-body-secondary text-center" style={{ minWidth: 56 }}>
                {index + 1} of {order.length}
            </span>
            <button
                type="button"
                className="btn btn-sm btn-outline-blue d-inline-flex align-items-center"
                style={{ gap: 4 }}
                disabled={!next}
                onClick={() => onGo(next)}
            >
                Next
                <ChevronRightIcon />
            </button>
        </nav>
    );
}
