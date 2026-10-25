// Small line icons (from the Lucide set), drawn inline so they follow the
// text color. All are decorative: aria-hidden, with words nearby or an
// aria-label on the button that holds them.

function Icon({ size = 16, strokeWidth = 2, style, className, children }) {
    return (
        <svg
            className={className}
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            style={style}
        >
            {children}
        </svg>
    );
}

export function SortIcon({ direction }) {
    if (direction === 'asc') {
        return (
            <Icon size={14} strokeWidth={2.5}>
                <path d="M6 15l6-6 6 6" />
            </Icon>
        );
    }

    if (direction === 'desc') {
        return (
            <Icon size={14} strokeWidth={2.5}>
                <path d="M6 9l6 6 6-6" />
            </Icon>
        );
    }

    return (
        <Icon size={14} style={{ opacity: 0.45 }}>
            <path d="M8 9l4-4 4 4" />
            <path d="M16 15l-4 4-4-4" />
        </Icon>
    );
}

export function FilterIcon() {
    return (
        <Icon size={14} style={{ opacity: 0.35 }}>
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </Icon>
    );
}

export function InfoIcon() {
    return (
        <Icon size={24}>
            <circle cx="12" cy="12" r="10" />
            <path d="M12 16v-4" />
            <path d="M12 8h.01" />
        </Icon>
    );
}

export function PhoneIcon({ size = 11, strokeWidth = 2.5, style }) {
    return (
        <Icon size={size} strokeWidth={strokeWidth} style={style}>
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
        </Icon>
    );
}

export function XIcon() {
    return (
        <Icon size={16} strokeWidth={2.5}>
            <path d="M18 6L6 18" />
            <path d="M6 6l12 12" />
        </Icon>
    );
}

export function ChevronLeftIcon({ size = 18 }) {
    return (
        <Icon size={size} strokeWidth={2.5}>
            <path d="M15 18l-6-6 6-6" />
        </Icon>
    );
}

// The small page icon after a source link.
export function NoteIcon() {
    return (
        <Icon size={13} strokeWidth={2.25} style={{ marginLeft: 3, verticalAlign: -1 }}>
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
        </Icon>
    );
}

export function LockIcon() {
    return (
        <Icon size={12} strokeWidth={2.5}>
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </Icon>
    );
}

// The side panel's controls (board E and F): close it, open the full page,
// and, on the full page, go back to the panel.
export function ChevronsRightIcon() {
    return (
        <Icon size={22}>
            <path d="M13 17l5-5-5-5" />
            <path d="M6 17l5-5-5-5" />
        </Icon>
    );
}

export function MaximizeIcon() {
    return (
        <Icon size={20}>
            <polyline points="15 3 21 3 21 9" />
            <polyline points="9 21 3 21 3 15" />
            <line x1="21" y1="3" x2="14" y2="10" />
            <line x1="3" y1="21" x2="10" y2="14" />
        </Icon>
    );
}

export function MinimizeIcon() {
    return (
        <Icon size={20}>
            <polyline points="4 14 10 14 10 20" />
            <polyline points="20 10 14 10 14 4" />
            <line x1="14" y1="10" x2="21" y2="3" />
            <line x1="3" y1="21" x2="10" y2="14" />
        </Icon>
    );
}

export function ChevronRightIcon() {
    return (
        <Icon size={16} strokeWidth={2.5}>
            <path d="M9 18l6-6-6-6" />
        </Icon>
    );
}

// "Opens in a panel on the right": after a student's name on hover.
export function PanelRightOpenIcon({ className }) {
    return (
        <Icon size={16} className={className}>
            <rect width="18" height="18" x="3" y="3" rx="2" />
            <path d="M15 3v18" />
            <path d="m10 15-3-3 3-3" />
        </Icon>
    );
}

export function CheckIcon() {
    return (
        <Icon size={20} strokeWidth={2.5}>
            <path d="M20 6L9 17l-5-5" />
        </Icon>
    );
}
