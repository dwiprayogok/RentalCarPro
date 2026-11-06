type P = { className?: string }
const base = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const, 'aria-hidden': true }

export const IconWhatsApp = ({ className = 'size-5' }: P) => (
  <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden>
    <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91A9.85 9.85 0 0 0 12.04 2Zm0 18.15h-.01a8.23 8.23 0 0 1-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.23 8.23Zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.16.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23a7.46 7.46 0 0 1-1.38-1.72c-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.44.13-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.13-.56-1.35-.76-1.85-.2-.48-.41-.42-.56-.42h-.48a.92.92 0 0 0-.66.31c-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.06-.1-.23-.16-.48-.29Z" />
  </svg>
)
export const IconSeat = ({ className = 'size-4' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><path d="M7 4v8a2 2 0 0 0 2 2h7" /><path d="M5 20h14" /><path d="M8 14l-1 6M16 14l1 6" /></svg>
)
export const IconGear = ({ className = 'size-4' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><circle cx="6" cy="6" r="2" /><circle cx="12" cy="6" r="2" /><circle cx="18" cy="6" r="2" /><circle cx="6" cy="18" r="2" /><circle cx="12" cy="18" r="2" /><path d="M6 8v8M12 8v8M18 8v4H12" /></svg>
)
export const IconFuel = ({ className = 'size-4' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><path d="M4 20V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v15" /><path d="M3 20h12M4 10h10" /><path d="M14 8l3 3v6a1.5 1.5 0 0 0 3 0V9l-3-3" /></svg>
)
export const IconCalendar = ({ className = 'size-4' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><rect x="3.5" y="5" width="17" height="15" rx="2" /><path d="M3.5 10h17M8 3v4M16 3v4" /></svg>
)
export const IconCheck = ({ className = 'size-4' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base} strokeWidth={2.2}><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
)
export const IconShield = ({ className = 'size-5' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><path d="M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6l7-3Z" /><path d="M9 12l2 2 4-4" /></svg>
)
export const IconClock = ({ className = 'size-5' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></svg>
)
export const IconTag = ({ className = 'size-5' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><path d="M3.5 12.5V4.5a1 1 0 0 1 1-1h8l8 8-9 9-8-8Z" /><circle cx="8" cy="8" r="1.4" /></svg>
)
export const IconPin = ({ className = 'size-5' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base}><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" /><circle cx="12" cy="9.5" r="2.5" /></svg>
)
export const IconArrow = ({ className = 'size-4' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base} strokeWidth={2}><path d="M5 12h14M13 6l6 6-6 6" /></svg>
)
export const IconStar = ({ className = 'size-4', filled = true }: P & { filled?: boolean }) => (
  <svg viewBox="0 0 24 24" className={className} fill={filled ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth={1.5} aria-hidden>
    <path d="M12 3.5l2.6 5.3 5.9.9-4.25 4.1 1 5.8L12 16.9l-5.25 2.7 1-5.8L3.5 9.7l5.9-.9L12 3.5Z" />
  </svg>
)
export const IconMenu = ({ className = 'size-6' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base} strokeWidth={2}><path d="M4 7h16M4 12h16M4 17h16" /></svg>
)
export const IconClose = ({ className = 'size-6' }: P) => (
  <svg viewBox="0 0 24 24" className={className} {...base} strokeWidth={2}><path d="M6 6l12 12M18 6L6 18" /></svg>
)
