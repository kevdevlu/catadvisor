// Bootstrap's spinning circle, sized to the text beside it, so the eye
// catches that the AI is still working. It takes the text's color unless
// className sets one. Screen readers skip it; the text beside it, or an
// aria-busy around it, tells them instead. With "reduce motion" on,
// Bootstrap spins it slower.
export default function Spinner({ className = '' }) {
    return (
        <span
            className={`spinner-border ${className}`}
            style={{ '--bs-spinner-width': '1em', '--bs-spinner-height': '1em', '--bs-spinner-border-width': '0.15em', marginRight: 8 }}
            aria-hidden="true"
        />
    );
}
