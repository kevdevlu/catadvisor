import { useEffect, useRef } from 'react';

// A badge in the student table that opens a small white popover with
// details, so an advisor can check a preference or a follow-up without
// opening the student. It uses Bootstrap's popover look, which
// sets "click for details" apart from the dark hover help on "Contact due".
//
// The table decides which one is open, so only one is open at a time.
// Clicking away, clicking the badge again, the × button, or Escape closes
// it; the × button and Escape also put keyboard focus back on the badge. align="end" lines the popover up
// with the badge's right edge, for badges near the right side of the table.
// tone sets the badge's text color: blue by default, or for example
// "text-chili" for the red Status chip.
export default function BadgePopover({ id, label, icon, title, tone = 'text-azurite', align = 'start', open, onToggle, children }) {
    const buttonRef = useRef(null);
    const popoverRef = useRef(null);

    // A popover that opens past the bottom of the screen (a badge on the
    // last rows) scrolls the page just enough to show it all.
    useEffect(() => {
        if (!open) return;

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        popoverRef.current?.scrollIntoView({ block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' });
    }, [open]);

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
            if (!inside) onToggle(false);
        }

        document.addEventListener('keydown', handleKey);
        document.addEventListener('pointerdown', handlePointer);

        return () => {
            document.removeEventListener('keydown', handleKey);
            document.removeEventListener('pointerdown', handlePointer);
        };
    }, [open, onToggle]);

    function close() {
        onToggle(false);
        buttonRef.current?.focus();
    }

    return (
        <span className="position-relative d-inline-block">
            <button
                ref={buttonRef}
                type="button"
                className={`badge bg-white border d-inline-flex align-items-center gap-1 badge-button ${tone}`}
                aria-expanded={open}
                aria-controls={id}
                onClick={() => onToggle(!open)}
            >
                {icon}
                {label}
            </button>
            {open && (
                <div ref={popoverRef} id={id} className={`popover bs-popover-bottom show shadow-lg badge-popover${align === 'end' ? ' align-end' : ''}`}>
                    <div className="popover-header d-flex align-items-center justify-content-between" style={{ gap: 12 }}>
                        {title}
                        <button type="button" className="btn-close" style={{ fontSize: '0.75rem' }} aria-label={`Close ${title}`} onClick={close} />
                    </div>
                    <div className="popover-body">{children}</div>
                </div>
            )}
        </span>
    );
}
