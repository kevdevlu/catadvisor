import { useEffect, useId, useRef, useState } from 'react';
import { ChevronDownIcon, FilterIcon } from './icons';

// The admin's advisor picker at the top right of the first screen:
// whose students the cards and the table show. A button names the
// choice ("All advisors", "Okafor", or "2 advisors" with a filter icon)
// and opens a popover with a checkbox per advisor.
//
// At least one advisor stays checked: the last checked box can't be
// unchecked, since "nobody" would be an empty screen that looks broken.
// "All advisors" checks everyone; it can't clear everyone, for the same
// reason.
//
// Each advisor row also has an "Only" button: one click shows just that
// advisor and closes the popover. Rationale: in an office with 6 advisors,
// getting to one by unchecking the others would take 5 clicks.
//
// advisors: [{ username, display_name }]; selected: the usernames picked.
// The popover closes like the badge popovers: × button, Escape, or a click
// outside it.
export default function AdvisorPicker({ advisors, selected, onChange }) {
    const id = useId();
    const [open, setOpen] = useState(false);
    const buttonRef = useRef(null);
    const popoverRef = useRef(null);

    const all = selected.length === advisors.length;
    const names = advisors.filter((advisor) => selected.includes(advisor.username)).map((advisor) => advisor.display_name);
    const summary = all ? 'All advisors' : names.length === 1 ? names[0] : `${names.length} advisors`;

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
        setOpen(false);
        buttonRef.current?.focus();
    }

    function only(username) {
        onChange([username]);
        close();
    }

    function toggle(username) {
        if (selected.includes(username)) {
            onChange(selected.filter((name) => name !== username));
        } else {
            // In the picker's order, so the subtitle lists names A to Z.
            onChange(advisors.map((advisor) => advisor.username).filter((name) => name === username || selected.includes(name)));
        }
    }

    return (
        <div className="position-relative">
            <div id={`${id}-label`} className="form-label small fw-semibold mb-1">
                Advisors
            </div>
            <button
                ref={buttonRef}
                type="button"
                className="btn btn-outline-blue d-inline-flex align-items-center justify-content-between w-100"
                style={{ gap: 8, minHeight: 44 }}
                aria-labelledby={`${id}-label ${id}-summary`}
                aria-expanded={open}
                aria-controls={`${id}-popover`}
                onClick={() => setOpen(!open)}
            >
                <span className="d-inline-flex align-items-center" style={{ gap: 6 }}>
                    {!all && <FilterIcon size={16} muted={false} />}
                    <span id={`${id}-summary`}>{summary}</span>
                </span>
                <ChevronDownIcon />
            </button>

            {open && (
                <div ref={popoverRef} id={`${id}-popover`} className="popover bs-popover-bottom show shadow-lg badge-popover align-end" style={{ width: '100%' }}>
                    <div className="popover-header d-flex align-items-center justify-content-between" style={{ gap: 12 }}>
                        Advisors
                        <button type="button" className="btn-close" style={{ fontSize: '0.75rem' }} aria-label="Close Advisors" onClick={close} />
                    </div>
                    <fieldset className="popover-body">
                        <legend className="visually-hidden">Show students of</legend>
                        <div className="form-check">
                            <input
                                id={`${id}-all`}
                                className="form-check-input"
                                type="checkbox"
                                checked={all}
                                disabled={all}
                                ref={(box) => box && (box.indeterminate = !all)}
                                onChange={() => onChange(advisors.map((advisor) => advisor.username))}
                            />
                            <label className="form-check-label fw-semibold" htmlFor={`${id}-all`}>
                                All advisors
                            </label>
                        </div>
                        <hr style={{ margin: '8px 0' }} />
                        {advisors.map((advisor) => {
                            const checked = selected.includes(advisor.username);
                            const last = checked && selected.length === 1;

                            // The "Only" button is hidden, but keeps its room,
                            // on the one advisor already picked alone, so the
                            // rows don't shift.
                            return (
                                <div key={advisor.username} className="d-flex align-items-center justify-content-between" style={{ gap: 12 }}>
                                    <div className="form-check mb-0">
                                        <input
                                            id={`${id}-${advisor.username}`}
                                            className="form-check-input"
                                            type="checkbox"
                                            checked={checked}
                                            disabled={last}
                                            aria-describedby={last ? `${id}-last` : undefined}
                                            onChange={() => toggle(advisor.username)}
                                        />
                                        <label className="form-check-label" htmlFor={`${id}-${advisor.username}`}>
                                            {advisor.display_name}
                                        </label>
                                    </div>
                                    <button
                                        type="button"
                                        className={`btn btn-link btn-sm p-0 ${last ? 'invisible' : ''}`}
                                        aria-label={`Show only ${advisor.display_name}`}
                                        onClick={() => only(advisor.username)}
                                    >
                                        Only
                                    </button>
                                </div>
                            );
                        })}
                        {selected.length === 1 && (
                            <p id={`${id}-last`} className="small text-body-secondary mb-0" style={{ marginTop: 6 }}>
                                At least one advisor stays picked.
                            </p>
                        )}
                    </fieldset>
                </div>
            )}
        </div>
    );
}
