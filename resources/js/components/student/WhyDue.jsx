// The "Reasons to reach out" list on the student page, for example "95 days
// past target. Target for High risk: every 30 days." The API writes the
// words; the emphasis part shows in bold red. A student who isn't due gets
// one line saying when they will be, under "Next contact" instead.
//
// The heading can take keyboard focus (tabIndex -1), so the page can move
// focus here after Clear removes the line above it.
export default function WhyDue({ due, headingRef }) {
    return (
        <>
            <h3 ref={headingRef} tabIndex={-1} className="h6 text-uppercase text-body-secondary" style={{ letterSpacing: '0.04em', marginTop: 24 }}>
                {due.is_due ? 'Reasons to reach out' : 'Next contact'}
            </h3>
            <ul className="mb-0">
                {due.reasons.map((reason) => (
                    <li key={`${reason.emphasis}${reason.text}`}>
                        {reason.emphasis && <span className="fw-semibold text-chili">{reason.emphasis}</span>}
                        {reason.emphasis && reason.text && ' '}
                        {reason.text}
                    </li>
                ))}
            </ul>
        </>
    );
}
