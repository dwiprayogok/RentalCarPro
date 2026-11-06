export function Logo({ name, dark = false }: { name: string; dark?: boolean }) {
  return (
    <span className="flex items-center gap-2.5">
      <span className="grid size-9 place-items-center rounded-xl bg-brand text-white shadow-sm shadow-brand/30" aria-hidden>
        <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
          <path d="M5 16V11.5l1.8-4.2A2 2 0 0 1 8.6 6h6.8a2 2 0 0 1 1.8 1.3l1.8 4.2V16" /><path d="M4 16h16v2.5H4z" /><path d="M5 11.5h14" />
        </svg>
      </span>
      <span className={`text-lg font-extrabold tracking-tight ${dark ? 'text-white' : 'text-ink'}`}>{name}</span>
    </span>
  )
}
