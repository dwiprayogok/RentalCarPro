import { Check, ChevronsUpDown, Loader2, Search, X } from 'lucide-react'
import { useEffect, useId, useRef, useState, type ReactNode } from 'react'
import { cn } from '@/lib/cn'

export interface ComboboxProps<T> {
  id?: string
  value: T | null
  onChange: (item: T | null) => void
  items: T[]
  loading?: boolean
  search: string
  onSearch: (value: string) => void
  getKey: (item: T) => number | string
  getLabel: (item: T) => string
  renderItem?: (item: T) => ReactNode
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  invalid?: boolean
  disabled?: boolean
  isItemDisabled?: (item: T) => boolean
  'aria-describedby'?: string
}

/**
 * Dropdown yang bisa dicari (pencarian dilakukan parent, mis. ke API).
 * Keyboard: ↑/↓ pilih, Enter konfirmasi, Esc tutup.
 */
export function Combobox<T>({
  id, value, onChange, items, loading, search, onSearch, getKey, getLabel, renderItem,
  placeholder = 'Pilih…', searchPlaceholder = 'Ketik untuk mencari…', emptyText = 'Tidak ada hasil.',
  invalid, disabled, isItemDisabled, ...aria
}: ComboboxProps<T>) {
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(0)
  const root = useRef<HTMLDivElement>(null)
  const input = useRef<HTMLInputElement>(null)
  const listId = useId()

  useEffect(() => {
    if (!open) return
    const onDoc = (e: MouseEvent) => { if (!root.current?.contains(e.target as Node)) setOpen(false) }
    document.addEventListener('mousedown', onDoc)
    requestAnimationFrame(() => input.current?.focus())
    return () => document.removeEventListener('mousedown', onDoc)
  }, [open])

  useEffect(() => setActive(0), [items])

  const choose = (item: T) => {
    if (isItemDisabled?.(item)) return
    onChange(item)
    setOpen(false)
  }

  return (
    <div ref={root} className="relative">
      <button
        id={id}
        type="button"
        disabled={disabled}
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-invalid={invalid || undefined}
        {...aria}
        className={cn(
          'flex h-10 w-full items-center gap-2 rounded-[10px] border bg-white px-3 text-left text-sm transition-colors',
          'hover:border-brand-300 focus:border-brand-500 focus:ring-3 focus:ring-brand-100 focus:outline-none disabled:cursor-not-allowed disabled:bg-canvas',
          invalid ? 'border-red-400' : 'border-line-strong',
        )}
      >
        <span className={cn('min-w-0 flex-1 truncate', !value && 'text-ink-muted/80')}>{value ? getLabel(value) : placeholder}</span>
        {value && !disabled ? (
          <span
            role="button"
            tabIndex={-1}
            aria-label="Kosongkan pilihan"
            onClick={(e) => { e.stopPropagation(); onChange(null) }}
            className="rounded p-0.5 text-ink-muted hover:text-ink"
          >
            <X className="size-4" />
          </span>
        ) : (
          <ChevronsUpDown className="size-4 shrink-0 text-ink-muted" />
        )}
      </button>

      {open && (
        <div className="absolute z-20 mt-1.5 w-full overflow-hidden rounded-xl border border-line bg-white shadow-xl">
          <div className="relative border-b border-line">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
            <input
              ref={input}
              value={search}
              onChange={(e) => onSearch(e.target.value)}
              placeholder={searchPlaceholder}
              role="combobox"
              aria-controls={listId}
              aria-expanded
              aria-activedescendant={items[active] ? `${listId}-${active}` : undefined}
              onKeyDown={(e) => {
                if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(a + 1, items.length - 1)) }
                if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)) }
                if (e.key === 'Enter') { e.preventDefault(); if (items[active]) choose(items[active]) }
                if (e.key === 'Escape') { e.stopPropagation(); setOpen(false) }
              }}
              className="h-10 w-full pr-3 pl-9 text-sm focus:outline-none"
            />
          </div>
          <ul id={listId} role="listbox" className="max-h-64 overflow-y-auto py-1">
            {loading && <li className="flex items-center gap-2 px-3 py-2.5 text-xs text-ink-muted"><Loader2 className="size-4 animate-spin" /> Mencari…</li>}
            {!loading && items.length === 0 && <li className="px-3 py-2.5 text-xs text-ink-muted">{emptyText}</li>}
            {!loading && items.map((item, i) => {
              const selected = value !== null && getKey(value) === getKey(item)
              const itemDisabled = isItemDisabled?.(item)
              return (
                <li
                  key={getKey(item)}
                  id={`${listId}-${i}`}
                  role="option"
                  aria-selected={selected}
                  aria-disabled={itemDisabled || undefined}
                  onMouseEnter={() => setActive(i)}
                  onClick={() => choose(item)}
                  className={cn(
                    'flex cursor-pointer items-center gap-2 px-3 py-2 text-sm',
                    i === active && 'bg-canvas',
                    itemDisabled && 'cursor-not-allowed opacity-60',
                  )}
                >
                  <div className="min-w-0 flex-1">{renderItem ? renderItem(item) : getLabel(item)}</div>
                  {selected && <Check className="size-4 shrink-0 text-brand-500" />}
                </li>
              )
            })}
          </ul>
        </div>
      )}
    </div>
  )
}
