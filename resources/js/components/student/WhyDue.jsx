// The "Why due" list on the student page, for example "95 days overdue.
// Target for High risk: every 30 days." The API writes the words; the
// emphasis part shows in bold red. A student who isn't due gets one line
// saying when they will be, under "Contact due" instead.
export default function WhyDue({ due }) {
    return (
        <>
            <h3 className="h6 text-uppercase text-body-secondary" style={{ letterSpacing: '0.04em', marginTop: 24 }}>
                {due.is_due ? 'Why due' : 'Contact due'}
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
