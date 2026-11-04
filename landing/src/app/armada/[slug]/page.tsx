import type { Metadata } from 'next'
import Link from 'next/link'
import { notFound } from 'next/navigation'
import { Suspense } from 'react'
import { getCar, getCars, getSiteSettings } from '@/lib/api'
import { rupiah } from '@/lib/format'
import { carSchema } from '@/lib/schema'
import { JsonLd } from '@/components/JsonLd'
import { IconCheck, IconFuel, IconGear, IconSeat } from '@/components/Icons'
import { AvailabilityCalendar } from './Calendar'
import { BookingPanel } from './BookingPanel'
import { Gallery } from './Gallery'

type Params = { params: Promise<{ slug: string }> }

// Halaman mobil dibuat saat build (bila API tersedia) & mobil baru dirender saat pertama diminta.
export const dynamicParams = true
export async function generateStaticParams() {
  return (await getCars()).map((c) => ({ slug: c.slug }))
}

export async function generateMetadata({ params }: Params): Promise<Metadata> {
  const { slug } = await params
  const [car, s] = await Promise.all([getCar(slug), getSiteSettings()])
  if (!car) return { title: 'Mobil tidak ditemukan', robots: { index: false } }
  const city = s.business.city ? ` di ${s.business.city}` : ''
  const description = `Sewa ${car.name}${city} mulai ${rupiah(car.rate_self_drive)}/hari. ${car.transmission}, ${car.seats} kursi. ${car.description ?? ''}`.trim()
  return {
    title: `Sewa ${car.name}${city}`,
    description: description.slice(0, 300),
    alternates: { canonical: `/armada/${car.slug}` },
    openGraph: {
      title: `Sewa ${car.name} · ${s.business.name}`,
      description: description.slice(0, 300),
      url: `/armada/${car.slug}`,
      images: car.cover_photo_url ? [{ url: car.cover_photo_url, alt: car.name }] : undefined,
    },
  }
}

export default async function CarPage({ params }: Params) {
  const { slug } = await params
  const [car, s] = await Promise.all([getCar(slug), getSiteSettings()])
  if (!car) notFound()

  const specs = [
    { icon: IconGear, label: 'Transmisi', value: car.transmission },
    { icon: IconSeat, label: 'Kapasitas', value: `${car.seats} kursi` },
    ...(car.fuel_type ? [{ icon: IconFuel, label: 'Bahan bakar', value: car.fuel_type }] : []),
  ]
  const rates = [
    { label: 'Lepas kunci', weekday: car.rate_self_drive, weekend: car.weekend_rate_self_drive },
    ...(car.rate_with_driver ? [{ label: 'Dengan driver', weekday: car.rate_with_driver, weekend: car.weekend_rate_with_driver }] : []),
  ]
  const hasWeekend = rates.some((r) => r.weekend && r.weekend !== r.weekday)

  return (
    <>
      <JsonLd data={carSchema(car, s)} />
      <div className="mx-auto max-w-6xl px-4 pt-6 pb-20 sm:px-6">
        <nav aria-label="Breadcrumb" className="text-sm text-muted">
          <ol className="flex flex-wrap items-center gap-1.5">
            <li><Link href="/" className="hover:text-ink">Beranda</Link></li><li aria-hidden>/</li>
            <li><Link href="/armada" className="hover:text-ink">Armada</Link></li><li aria-hidden>/</li>
            <li aria-current="page" className="font-medium text-ink">{car.name}</li>
          </ol>
        </nav>

        <div className="mt-5 grid gap-8 lg:grid-cols-[1fr_400px] lg:items-start">
          <div className="min-w-0 space-y-8">
            <Gallery photos={car.photos} name={car.name} />

            <header>
              <p className="text-sm font-medium text-muted">{car.brand} · {car.year}{car.color ? ` · ${car.color}` : ''}</p>
              <h1 className="mt-1 text-[32px] leading-tight font-extrabold tracking-tight sm:text-4xl">{car.name}</h1>
              <p className="mt-2 text-lg">
                <span className="font-extrabold">{rupiah(car.rate_self_drive)}</span><span className="text-muted">/hari lepas kunci</span>
              </p>
              {car.availability.status !== 'tersedia' && (
                <p className="mt-3 inline-flex rounded-full bg-amber-50 px-3 py-1 text-sm font-semibold text-amber">Saat ini: {car.availability.label.toLowerCase()} — cek tanggal lain di kalender</p>
              )}
            </header>

            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {specs.map(({ icon: Icon, label, value }) => (
                <li key={label} className="card flex items-center gap-3 p-4">
                  <span className="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand"><Icon className="size-5" /></span>
                  <div><p className="text-2xs text-muted">{label}</p><p className="font-semibold">{value}</p></div>
                </li>
              ))}
            </ul>

            {car.description && <p className="text-base leading-relaxed whitespace-pre-line text-ink/85">{car.description}</p>}

            {car.features.length > 0 && (
              <section aria-labelledby="fitur">
                <h2 id="fitur" className="text-xl font-extrabold">Fitur unggulan</h2>
                <ul className="mt-3 grid gap-2 sm:grid-cols-2">
                  {car.features.map((f) => (
                    <li key={f} className="flex items-center gap-2.5"><span className="grid size-6 place-items-center rounded-full bg-brand-50 text-brand"><IconCheck /></span>{f}</li>
                  ))}
                </ul>
              </section>
            )}

            <section aria-labelledby="tarif">
              <h2 id="tarif" className="text-xl font-extrabold">Tarif per hari</h2>
              <div className="card mt-3 overflow-x-auto">
                <table className="w-full min-w-[320px] text-left text-sm">
                  <thead className="bg-canvas text-2xs font-semibold text-muted uppercase">
                    <tr><th scope="col" className="px-4 py-3">Layanan</th><th scope="col" className="px-4 py-3 text-right">Hari biasa</th>{hasWeekend && <th scope="col" className="px-4 py-3 text-right">Akhir pekan / libur</th>}</tr>
                  </thead>
                  <tbody className="divide-y divide-line">
                    {rates.map((r) => (
                      <tr key={r.label}>
                        <th scope="row" className="px-4 py-3 font-semibold">{r.label}</th>
                        <td className="px-4 py-3 text-right tabular-nums">{rupiah(r.weekday)}</td>
                        {hasWeekend && <td className="px-4 py-3 text-right tabular-nums">{rupiah(r.weekend ?? r.weekday)}</td>}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <ul className="mt-3 space-y-1 text-sm text-muted">
                <li>Deposit {rupiah(car.deposit)} — dana titipan, dikembalikan saat mobil kembali.</li>
                <li>Toleransi keterlambatan {s.rental_rules.grace_hours.toLocaleString('id-ID')} jam; setelahnya {rupiah(s.rental_rules.late_fee_per_hour)}/jam.</li>
              </ul>
            </section>

            <section aria-labelledby="kalender">
              <h2 id="kalender" className="text-xl font-extrabold">Kalender ketersediaan</h2>
              <p className="mt-1 text-sm text-muted">Tanggal terisi sudah termasuk waktu persiapan unit.</p>
              <div className="card mt-4 p-5"><AvailabilityCalendar {...car.calendar} /></div>
            </section>
          </div>

          <aside className="lg:sticky lg:top-24" id="pesan">
            <Suspense fallback={<div className="card h-96 animate-pulse" />}>
              <BookingPanel slug={car.slug} carName={car.name} hasDriver={!!car.rate_with_driver} whatsappUrl={s.business.whatsapp_url} />
            </Suspense>
          </aside>
        </div>
      </div>
    </>
  )
}
