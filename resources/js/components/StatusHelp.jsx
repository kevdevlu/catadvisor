import { useEffect, useRef, useState } from 'react';
import { InfoIcon } from './icons';

// The info icon on the "Status" heading. It explains only how the
// column sorts; each chip in the column explains itself when clicked. It
// opens on hover or keyboard focus, and Escape closes it. The tooltip is
// placed against the heading cell, which must be position-relative.
//
// Moving the pointer from the icon onto the tooltip keeps it open, so the
// text can be read under a screen magnifier (WCAG 1.4.13). The short delay
// covers the gap between the two.
const CLOSE_DELAY_MS = 150;

export default function StatusHelp() {
    const [open, setOpen] = useState(false);
    const closeTimer = useRef(null);

    // Don't leave a pending close running after the table is gone.
    useEffect(() => () => clearTimeout(closeTimer.current), []);

    function show() {
        clearTimeout(closeTimer.current);
        setOpen(true);
    }

    function hideSoon() {
        clearTimeout(closeTimer.current);
        closeTimer.current = setTimeout(() => setOpen(false), CLOSE_DELAY_MS);
    }

    return (
        <>
            <button
                type="button"
                className="btn btn-link p-0 ms-1 text-azurite align-middle"
                style={{ minHeight: 44, minWidth: 40 }}
                aria-label="How Status sorts"
                aria-describedby={open ? 'status-help' : undefined}
                onMouseEnter={show}
                onMouseLeave={hideSoon}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={(event) => {
                    // preventDefault tells the side panel this Escape is taken.
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        setOpen(false);
                    }
                }}
            >
                <InfoIcon />
            </button>

            {open && (
                <div
                    id="status-help"
                    role="tooltip"
                    className="tooltip bs-tooltip-bottom show position-absolute top-100 end-0"
                    style={{ whiteSpace: 'nowrap' }}
                    onMouseEnter={show}
                    onMouseLeave={hideSoon}
                >
                    <div className="tooltip-inner fw-normal" style={{ padding: '8px 12px' }}>
                        Sorts by target date
                    </div>
                </div>
            )}
        </>
    );
}
