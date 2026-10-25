import { useEffect, useRef, useState } from 'react';
import { api } from '../lib/api';

// "Reset demo data" in the admin's header: puts every student and note back
// to the original dataset, for everyone. A small popover asks first, since
// it throws away every change made since. It closes like the other
// popovers: × button, Cancel, Escape, or a click outside it.
//
// After a reset the page loads again from the top. Rationale: the reloaded
// students get new IDs, so any open student, link, or list on screen would
// point at records that are gone.
export default function DemoReset() {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const buttonRef = useRef(null);
    const popoverRef = useRef(null);

    useEffect(() => {
        if (!open) return;

        function handleKey(event) {
            if (event.key === 'Escape') {
                // Handled: the side panel, if open, stays open.
                event.preventDefault();
                close();
            }
        }

        function handlePointer(event) {
            const inside = popoverRef.current?.contains(event.target) || buttonRef.current?.contains(event.target);
            if (!inside) setOpen(false);
        }

        document.addEventListener('keydown', handleKey);
        document.addEventListener('pointerdown', handlePointer);

        return () => {
            document.removeEventListener('keydown', handleKey);
            document.removeEventListener('pointerdown', handlePointer);
        };
    }, [open]);

    function close() {
        if (busy) return;
        setOpen(false);
        setError('');
        buttonRef.current?.focus();
    }

    async function reset() {
        if (busy) return;
        setBusy(true);
        setError('');

        try {
            await api('/api/demo/reset', { method: 'POST' });
            window.location.assign('/');
        } catch {
            setBusy(false);
            setError('The reset didn’t work. Try again in a minute.');
        }
    }

    return (
        <div className="position-relative">
            <button
                ref={buttonRef}
                type="button"
                className="btn btn-sm btn-outline-white"
                aria-expanded={open}
                aria-controls="demo-reset"
                onClick={() => (open ? close() : setOpen(true))}
            >
                Reset demo data
            </button>

            {open && (
                <div ref={popoverRef} id="demo-reset" className="popover bs-popover-bottom show shadow-lg badge-popover align-end text-body" style={{ width: 300 }}>
                    <div className="popover-header d-flex align-items-center justify-content-between" style={{ gap: 12 }}>
                        Reset demo data
                        <button type="button" className="btn-close" style={{ fontSize: '0.75rem' }} aria-label="Close" onClick={close} />
                    </div>
                    <div className="popover-body">
                        <p className="mb-2">
                            Put every student and note back to the original dataset? Notes, follow-ups, and other changes made since are removed for
                            everyone.
                        </p>
                        {error && (
                            <p className="text-danger small mb-2" role="alert">
                                {error}
                            </p>
                        )}
                        <div className="d-flex justify-content-end" style={{ gap: 8 }}>
                            <button type="button" className="btn btn-sm btn-link" onClick={close} disabled={busy}>
                                Cancel
                            </button>
                            <button type="button" className="btn btn-sm btn-red" onClick={reset} aria-disabled={busy}>
                                {busy ? 'Resetting…' : 'Reset'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
