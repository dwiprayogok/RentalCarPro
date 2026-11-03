import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/cn'

export function Spinner({ className }: { className?: string }) {
  return <Loader2 className={cn('size-5 animate-spin text-brand-500', className)} aria-label="Memuat" />
}

export function FullPageLoader() {
  return (
    <div className="grid min-h-dvh place-items-center bg-canvas">
      <Spinner className="size-8" />
    </div>
  )
}

export function Skeleton({ className }: { className?: string }) {
  return <div className={cn('animate-pulse rounded-md bg-line/70', className)} aria-hidden />
}

export function Alert({ tone = 'red', children }: { tone?: 'red' | 'green' | 'yellow'; children: React.ReactNode }) {
  const styles = {
    red: 'bg-red-50 text-red-800 ring-red-200',
    green: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    yellow: 'bg-amber-50 text-amber-900 ring-amber-200',
  }
  return <div role={tone === 'red' ? 'alert' : 'status'} className={cn('rounded-xl px-4 py-3 text-sm ring-1 ring-inset', styles[tone])}>{children}</div>
}
