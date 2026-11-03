import { cn } from '@/lib/cn'

interface Chip<T extends string> {
  value: T
  label: string
  count?: number
}

export function FilterChips<T extends string>({ options, value, onChange, label }: {
  options: Chip<T>[]
  value: T
  onChange: (value: T) => void
  label: string
}) {
  return (
    <div role="radiogroup" aria-label={label} className="flex flex-wrap gap-2">
      {options.map((o) => {
        const active = o.value === value
        return (
          <button
            key={o.value}
            type="button"
            role="radio"
            aria-checked={active}
            onClick={() => onChange(o.value)}
            className={cn(
              'inline-flex h-8 items-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition-colors',
              active
                ? 'border-brand-300 bg-brand-50 text-brand-700'
                : 'border-line-strong bg-white text-ink-soft hover:border-brand-300 hover:text-ink',
            )}
          >
            {o.label}
            {o.count !== undefined && (
              <span className={cn('rounded px-1.5 text-2xs tabular-nums', active ? 'bg-brand-100' : 'bg-canvas text-ink-muted')}>{o.count}</span>
            )}
          </button>
        )
      })}
    </div>
  )
}
