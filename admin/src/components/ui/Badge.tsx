import type { ReactNode } from 'react'
import { cn } from '@/lib/cn'
import type { CarStatus, RiskStatus } from '@/lib/types'

export type Tone = 'green' | 'blue' | 'yellow' | 'red' | 'gray' | 'teal'

const tones: Record<Tone, string> = {
  green: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  teal: 'bg-brand-50 text-brand-700 ring-brand-200',
  blue: 'bg-sky-50 text-sky-800 ring-sky-200',
  yellow: 'bg-amber-50 text-amber-800 ring-amber-200',
  red: 'bg-red-50 text-red-700 ring-red-200',
  gray: 'bg-slate-100 text-slate-700 ring-slate-200',
}

const dots: Record<Tone, string> = {
  green: 'bg-emerald-500', teal: 'bg-brand-500', blue: 'bg-sky-500', yellow: 'bg-amber-500', red: 'bg-red-500', gray: 'bg-slate-400',
}

export function Badge({ tone = 'gray', children, className, dot = true }: { tone?: Tone; children: ReactNode; className?: string; dot?: boolean }) {
  return (
    <span className={cn('inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-2xs font-semibold ring-1 ring-inset', tones[tone], className)}>
      {dot && <span className={cn('size-1.5 rounded-full', dots[tone])} aria-hidden />}
      {children}
    </span>
  )
}

const carStatusTone: Record<CarStatus, Tone> = { tersedia: 'green', disewa: 'blue', servis: 'yellow', nonaktif: 'gray' }
const riskTone: Record<RiskStatus, Tone> = { lancar: 'green', perhatian: 'yellow', blacklist: 'red' }

export function CarStatusBadge({ status, label }: { status: CarStatus; label: string }) {
  return <Badge tone={carStatusTone[status]}>{label}</Badge>
}

export function RiskBadge({ status, label }: { status: RiskStatus; label: string }) {
  return <Badge tone={riskTone[status]}>{label}</Badge>
}
