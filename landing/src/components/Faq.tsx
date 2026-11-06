import type { Faq as FaqItem } from '@/lib/types'

export function FaqList({ items }: { items: FaqItem[] }) {
  return (
    <div className="divide-y divide-line overflow-hidden rounded-[var(--radius-card)] bg-white ring-1 ring-line/70">
      {items.map((f, i) => (
        <details key={i} className="group px-5 sm:px-6" open={i === 0}>
          <summary className="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-4 text-base font-semibold [&::-webkit-details-marker]:hidden">
            {f.q}
            <span className="grid size-8 shrink-0 place-items-center rounded-full bg-canvas text-brand transition-transform group-open:rotate-45" aria-hidden>+</span>
          </summary>
          <p className="-mt-1 pb-5 leading-relaxed whitespace-pre-line text-muted">{f.a}</p>
        </details>
      ))}
    </div>
  )
}
