/** Ilustrasi mobil generik (dipakai bila belum ada foto). */
export function CarIllustration({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 320 130" className={className} role="img" aria-label="Ilustrasi mobil">
      <ellipse cx="160" cy="116" rx="132" ry="7" fill="#c9d6d2" opacity=".55" />
      <path d="M28 92c0-10 6-18 16-21l40-10 34-27c6-5 13-7 21-7h62c9 0 17 3 23 9l26 26 34 6c11 2 18 11 18 22v10c0 5-4 9-9 9H37c-5 0-9-4-9-9V92Z" fill="#dfe7e4" />
      <path d="M28 92c0-10 6-18 16-21l40-10 34-27c6-5 13-7 21-7h62c9 0 17 3 23 9l26 26 34 6c11 2 18 11 18 22v10c0 5-4 9-9 9H37c-5 0-9-4-9-9V92Z" fill="none" stroke="#9fb4b2" strokeWidth="2" />
      <path d="M96 60 123 38c5-4 10-5 16-5h20v27H96Z" fill="#5b6f74" opacity=".85" />
      <path d="M167 33h26c7 0 13 3 18 8l19 19h-63V33Z" fill="#5b6f74" opacity=".85" />
      <path d="M163 33v62M100 66h168" stroke="#9fb4b2" strokeWidth="2" />
      <rect x="282" y="78" width="14" height="6" rx="3" fill="#f2c94c" />
      <rect x="30" y="80" width="12" height="6" rx="3" fill="#e57373" />
      {[86, 240].map((cx) => (
        <g key={cx}>
          <circle cx={cx} cy="104" r="20" fill="#14262d" />
          <circle cx={cx} cy="104" r="10" fill="#c9d6d2" />
          <circle cx={cx} cy="104" r="3.5" fill="#14262d" />
        </g>
      ))}
    </svg>
  )
}
