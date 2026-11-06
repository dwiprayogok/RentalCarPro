import type { Metadata } from 'next'
import { Suspense } from 'react'
import { getCars, getSiteSettings } from '@/lib/api'
import { Fleet } from './Fleet'

export async function generateMetadata(): Promise<Metadata> {
  const s = await getSiteSettings()
  const city = s.business.city ? ` di ${s.business.city}` : ''
  return {
    title: `Armada Sewa Mobil${city}`,
    description: `Daftar mobil sewa ${s.business.name}${city}: tarif per hari, kapasitas, transmisi, dan cek ketersediaan berdasarkan tanggal.`,
    alternates: { canonical: '/armada' },
    openGraph: { title: `Armada · ${s.business.name}`, url: '/armada' },
  }
}

export default async function ArmadaPage() {
  const [cars, s] = await Promise.all([getCars(), getSiteSettings()])
  return (
    <>
      <section className="bg-ink text-white">
        <div className="mx-auto max-w-6xl px-4 pt-12 pb-24 sm:px-6">
          <p className="eyebrow text-mint">Armada</p>
          <h1 className="mt-2 text-[32px] leading-tight font-extrabold tracking-tight sm:text-5xl">Temukan mobil yang pas</h1>
          <p className="mt-3 max-w-xl text-white/70">Isi tanggal sewa untuk melihat unit yang kosong beserta estimasi totalnya.</p>
        </div>
      </section>
      <div className="mx-auto -mt-16 max-w-6xl px-4 pb-20 sm:px-6">
        <Suspense fallback={<div className="card h-40 animate-pulse" />}>
          <Fleet cars={cars} whatsappUrl={s.business.whatsapp_url} />
        </Suspense>
      </div>
    </>
  )
}
