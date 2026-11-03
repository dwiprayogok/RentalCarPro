import { Gauge, Globe, Palette, Settings2 } from 'lucide-react'
import { CarStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { number, rupiah } from '@/lib/format'
import type { Car } from '@/lib/types'
import { CarIllustration } from './CarIllustration'

export function CarCard({ car, onDetail }: { car: Car; onDetail: () => void }) {
  return (
    <article className="card flex flex-col overflow-hidden transition-shadow hover:shadow-md">
      <div className="relative aspect-[16/9] bg-canvas">
        {car.cover_photo_url ? (
          <img src={car.cover_photo_url} alt={car.name} className="size-full object-cover" loading="lazy" />
        ) : (
          <div className="flex size-full items-center justify-center px-8"><CarIllustration className="w-full max-w-[240px]" /></div>
        )}
        <div className="absolute top-3 left-3"><CarStatusBadge status={car.status} label={car.status_label} /></div>
        {car.is_published && (
          <span className="absolute top-3 right-3 inline-flex items-center gap-1 rounded-md bg-white/90 px-2 py-0.5 text-2xs font-semibold text-brand-700 ring-1 ring-brand-200" title="Tampil di landing page">
            <Globe className="size-3" /> Landing
          </span>
        )}
      </div>

      <div className="flex flex-1 flex-col p-4">
        <h3 className="truncate text-base font-bold text-ink" title={car.name}>{car.name}</h3>
        <p className="mt-0.5 text-xs font-semibold text-brand-600">{car.plate_number} · {car.year}</p>

        <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-muted">
          <li className="flex items-center gap-1"><Settings2 className="size-3.5" /> {car.transmission_label}</li>
          <li className="flex items-center gap-1"><Gauge className="size-3.5" /> {number(car.odometer)} km</li>
          {car.color && <li className="flex items-center gap-1"><Palette className="size-3.5" /> {car.color}</li>}
        </ul>

        <div className="mt-4 flex items-end justify-between gap-2 border-t border-line pt-3">
          <p className="text-base font-extrabold text-ink tabular-nums">
            {rupiah(car.rate_self_drive)} <span className="text-xs font-medium text-ink-muted">/ hari</span>
          </p>
          <Button size="sm" variant="secondary" onClick={onDetail} aria-label={`Detail ${car.name} ${car.plate_number}`}>Detail</Button>
        </div>
      </div>
    </article>
  )
}

export function CarCardSkeleton() {
  return (
    <div className="card overflow-hidden" aria-hidden>
      <div className="aspect-[16/9] animate-pulse bg-line/60" />
      <div className="space-y-2 p-4">
        <div className="h-4 w-2/3 animate-pulse rounded bg-line/70" />
        <div className="h-3 w-1/3 animate-pulse rounded bg-line/70" />
        <div className="h-3 w-1/2 animate-pulse rounded bg-line/70" />
        <div className="mt-4 h-6 w-full animate-pulse rounded bg-line/70" />
      </div>
    </div>
  )
}
