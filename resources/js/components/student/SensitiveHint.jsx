import { findSensitive, quoteList } from '../../lib/sensitiveKeywords';

// The yellow hint under a note form while the advisor types:
// "This note may be sensitive. It mentions "counselor". Consider marking it
// private." The advisor decides; the app never marks a note private on its
// own. Shows nothing once the note is private or mentions nothing on the
// list. Used by the add and edit forms.
export default function SensitiveHint({ text, isPrivate, onMarkPrivate }) {
    const words = isPrivate ? [] : findSensitive(text);

    if (words.length === 0) {
        return null;
    }

    return (
        <div
            className="alert alert-warning d-flex flex-wrap align-items-center justify-content-between"
            style={{ gap: 8, marginTop: 10, marginBottom: 0, padding: '10px 14px' }}
        >
            <div className="small" style={{ flex: '1 1 220px' }}>
                {/* Only the first sentence is announced, so a screen reader
                    doesn't repeat the hint as each new word appears. */}
                <span className="fw-semibold" role="status">
                    This note may be sensitive.
                </span>{' '}
                It mentions {quoteList(words)}. Consider marking it private.
            </div>
            <button type="button" className="btn btn-sm btn-blue" onClick={onMarkPrivate}>
                Mark as private
            </button>
        </div>
    );
}
