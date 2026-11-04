import type { Metadata } from 'next'
import { getSiteSettings } from '@/lib/api'
import { rupiah } from '@/lib/format'
import { IconCheck } from '@/components/Icons'

export async function generateMetadata(): Promise<Metadata> {
  const s = await getSiteSettings()
  return {
    title: 'Syarat & Ketentuan Sewa',
    description: `Syarat sewa mobil ${s.business.name}: dokumen, deposit, toleransi keterlambatan, dan denda.`,
    alternates: { canonical: '/syarat-ketentuan' },
  }
}

export default async function TermsPage() {
  const s = await getSiteSettings()
  const r = s.rental_rules
  return (
    <div className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
      <p className="eyebrow">Syarat & ketentuan</p>
      <h1 className="mt-2 text-[32px] leading-tight font-extrabold tracking-tight sm:text-4xl">Aturan sewa yang jelas sejak awal</h1>

      {s.terms.length > 0 && (
        <section aria-labelledby="dokumen" className="card mt-8 p-6">
          <h2 id="dokumen" className="text-lg font-extrabold">Syarat penyewa</h2>
          <ol className="mt-4 space-y-3">
            {s.terms.map((t, i) => (
              <li key={i} className="flex gap-3 leading-relaxed">
                <span className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-brand"><IconCheck /></span>{t}
              </li>
            ))}
          </ol>
        </section>
      )}

      <section aria-labelledby="waktu" className="card mt-5 p-6">
        <h2 id="waktu" className="text-lg font-extrabold">Waktu sewa & keterlambatan</h2>
        <ul className="mt-4 list-disc space-y-2 pl-5 leading-relaxed marker:text-brand">
          <li>Sewa dihitung per 24 jam sejak waktu ambil.</li>
          <li>Ada toleransi {r.grace_hours.toLocaleString('id-ID')} jam setelah waktu kembali yang disepakati, tanpa biaya.</li>
          <li>Lewat toleransi, dikenakan {rupiah(r.late_fee_per_hour)} per jam, maksimal setara tarif satu hari untuk setiap 24 jam.</li>
          <li>Keterlambatan lebih dari 24 jam dihitung sebagai tambahan hari sewa.</li>
        </ul>
      </section>

      <section aria-labelledby="deposit" className="card mt-5 p-6">
        <h2 id="deposit" className="text-lg font-extrabold">Deposit</h2>
        <p className="mt-3 leading-relaxed">
          Deposit adalah dana titipan. Bila mobil kembali tepat waktu dan tanpa kendala, deposit dikembalikan penuh.
          Bila ada keterlambatan atau biaya lain, deposit dapat dipakai untuk menutupnya dan sisanya dikembalikan.
        </p>
      </section>

      <p className="mt-8 text-sm text-muted">Pertanyaan lain? Hubungi kami lewat WhatsApp{s.business.whatsapp ? ` ${s.business.whatsapp}` : ''}.</p>
    </div>
  )
}
