import { useState } from 'react';
import SourceLink from './SourceLink';

// "Waiting on a reply since Jun 2, 2026" on the student page,
// with the note it came from. Only the AI sets it; the advisor can Clear it.
// Clear removes the line, so onCleared tells the page where to put keyboard
// focus next.
export default function WaitingLine({ waiting, canWrite, onUpdate, onJump, onAnnounce, onCleared }) {
    const [error, setError] = useState('');

    if (!waiting) {
        return null;
    }

    async function handleClear() {
        setError('');
        try {
            await onUpdate('waiting', { method: 'DELETE' });
            onAnnounce('"Waiting on a reply" cleared.');
            onCleared();
        } catch {
            setError('"Waiting on a reply" did not clear. Try again.');
        }
    }

    return (
        <div className="border rounded" style={{ padding: '8px 12px' }}>
            <div className="d-flex flex-wrap align-items-center justify-content-between" style={{ gap: 8 }}>
                <div style={{ flex: '1 1 260px', minWidth: 0 }}>
                    <span className="fw-semibold">Waiting on a reply</span> since {waiting.since_label}{' '}
                    <span className="small text-body-secondary">
                        · Set by AI from <SourceLink source={waiting.source} onJump={onJump} />
                    </span>
                </div>
                {canWrite && (
                    <button type="button" className="btn btn-link btn-sm p-0 text-chili flex-shrink-0" style={{ minHeight: 32 }} onClick={handleClear}>
                        Clear
                    </button>
                )}
            </div>
            {error && (
                <div className="small text-danger" role="alert">
                    {error}
                </div>
            )}
        </div>
    );
}
