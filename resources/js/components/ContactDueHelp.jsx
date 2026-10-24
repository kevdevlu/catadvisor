import { useEffect, useRef, useState } from 'react';
import { InfoIcon } from './icons';

// The info icon on the "Contact due" heading, as on board D. The
// help text reads on from the heading: "Contact due ... when any of these is
// true". It opens on hover or keyboard focus, and Escape closes it. The
// tooltip is placed against the heading cell, which must be position-relative.
//
// Moving the pointer from the icon onto the tooltip keeps it open, so the
// text can be read under a screen magnifier (WCAG 1.4.13). The short delay
// covers the gap between the two.
const CLOSE_DELAY_MS = 150;

export default function ContactDueHelp() {
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
                aria-label="How Contact due works"
                aria-describedby={open ? 'contact-due-help' : undefined}
                onMouseEnter={show}
                onMouseLeave={hideSoon}
                onFocus={() => setOpen(true)}
                onBlur={() => setOpen(false)}
                onKeyDown={(event) => event.key === 'Escape' && setOpen(false)}
            >
                <InfoIcon />
            </button>

            {open && (
                <div
                    id="contact-due-help"
                    role="tooltip"
                    className="tooltip bs-tooltip-bottom show position-absolute top-100 end-0"
                    style={{ '--bs-tooltip-max-width': '390px', whiteSpace: 'normal' }}
                    onMouseEnter={show}
                    onMouseLeave={hideSoon}
                >
                    <div className="tooltip-inner text-start fw-normal" style={{ width: 'max-content', maxWidth: 370, padding: '12px 16px', lineHeight: 1.45 }}>
                        <div className="fw-bold" style={{ marginBottom: 6 }}>
                            When any of these is true:
                        </div>
                        <ol className="m-0" style={{ paddingLeft: '1.25rem' }}>
                            <li style={{ marginBottom: 6 }}>
                                Not contacted within the target:
                                <ul style={{ margin: '2px 0 0', paddingLeft: '1rem' }}>
                                    <li>
                                        High risk: <strong>30 days</strong>
                                    </li>
                                    <li>
                                        Medium risk: <strong>60 days</strong>
                                    </li>
                                    <li>
                                        Low risk: <strong>90 days</strong>
                                    </li>
                                </ul>
                            </li>
                            <li style={{ marginBottom: 6 }}>The last outreach got no reply.</li>
                            <li>A follow-up date is 14 days away or less.</li>
                        </ol>
                    </div>
                </div>
            )}
        </>
    );
}
