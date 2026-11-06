import Link from 'next/link'
import type { PublicCar } from '@/lib/types'
import { rupiah } from '@/lib/format'
import { CarPhoto } from './CarPhoto'
import { IconFuel, IconGear, IconSeat } from './Icons'

const badge: Record<PublicCar['availability']['status'], string> = {
  tersedia: 'bg-brand-50 text-brand',
  disewa: 'bg-amber-50 text-amber',
  perawatan: 'bg-canvas text-muted',
}

export function CarCard({ car, query = '', priority = false }: { car: PublicCar; query?: string; priority?: boolean }) {
  const p = car.period
  return (
    <article className="card group flex flex-col overflow-hidden transition-shadow hover:shadow-xl hover:shadow-ink/5">
      <Link href={`/armada/${car.slug}${query}`} className="flex flex-1 flex-col focus-visible:outline-offset-[-3px]">
        <div className="relative aspect-[16/10] overflow-hidden bg-canvas">
          <CarPhoto src={car.cover_photo_url} alt={car.name} eager={priority} className="transition-transform duration-500 group-hover:scale-[1.03]" />
          {p ? (
            <span className={`absolute top-3 left-3 rounded-full px-2.5 py-1 text-2xs font-semibold shadow-sm ${p.available ? 'bg-brand text-white' : 'bg-white text-danger'}`}>
              {p.available ? 'Tersedia di tanggal ini' : 'Penuh di tanggal ini'}
            </span>
          ) : (
            <span className={`absolute top-3 left-3 rounded-full px-2.5 py-1 text-2xs font-semibold ${badge[car.availability.status]}`}>
              {car.availability.label}
            </span>
          )}
        </div>
        <div className="flex flex-1 flex-col p-5">
          <p className="text-2xs font-medium text-muted">{car.brand} · {car.year}</p>
          <h3 className="mt-0.5 text-lg font-bold tracking-tight">{car.name}</h3>
          <ul className="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-sm text-muted">
            <li className="flex items-center gap-1.5"><IconGear />{car.transmission}</li>
            <li className="flex items-center gap-1.5"><IconSeat />{car.seats} kursi</li>
            {car.fuel_type && <li className="flex items-center gap-1.5"><IconFuel />{car.fuel_type}</li>}
          </ul>
          <div className="mt-auto flex items-end justify-between gap-3 pt-5">
            {p ? (
              <div>
                <p className="text-2xs text-muted">Estimasi {p.days} hari</p>
                <p className="text-xl font-extrabold tracking-tight">{rupiah(p.estimated_total)}</p>
              </div>
            ) : (
              <div>
                <p className="text-2xs text-muted">Mulai</p>
                <p className="text-xl font-extrabold tracking-tight">{rupiah(car.rate_self_drive)}<span className="text-sm font-medium text-muted">/hari</span></p>
              </div>
            )}
            <span className="inline-flex min-h-10 items-center rounded-full bg-ink px-4 text-sm font-semibold text-white transition-colors group-hover:bg-brand">
              Lihat
            </span>
          </div>
        </div>
      </Link>
    </article>
  )
}
