import { cn } from '@/lib/cn'

export function Avatar({ initials, className }: { initials: string; className?: string }) {
  return (
    <span className={cn('grid size-8 shrink-0 place-items-center rounded-full bg-brand-50 text-2xs font-bold text-brand-700 ring-1 ring-brand-100', className)} aria-hidden>
      {initials}
    </span>
  )
}
