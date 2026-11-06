import Link from 'next/link'
import { getCars, getSiteSettings } from '@/lib/api'
import { rupiah, waLink } from '@/lib/format'
import { businessSchema } from '@/lib/schema'
import { CarCard } from '@/components/CarCard'
import { FaqList } from '@/components/Faq'
import { JsonLd } from '@/components/JsonLd'
import { QuickSearch } from '@/components/QuickSearch'
import { SectionHeading } from '@/components/SectionHeading'
import { IconArrow, IconCheck, IconClock, IconPin, IconShield, IconStar, IconTag, IconWhatsApp } from '@/components/Icons'

export default async function HomePage() {
  const [s, cars] = await Promise.all([getSiteSettings(), getCars()])
  const featured = cars.slice(0, 3)
  const minRate = cars.length ? Math.min(...cars.map((c) => c.rate_self_drive)) : null
  const wa = waLink(s.business.whatsapp_url, `Halo ${s.business.name}, saya ingin tanya sewa mobil.`)

  const values = [
    { icon: IconTag, title: 'Tarif jelas di depan', text: 'Estimasi total keluar saat Anda memilih tanggal. Tanpa biaya tersembunyi.' },
    { icon: IconClock, title: `Toleransi ${s.rental_rules.grace_hours.toLocaleString('id-ID')} jam`, text: `Telat sedikit tidak langsung didenda. Lewat toleransi, ${rupiah(s.rental_rules.late_fee_per_hour)}/jam.` },
    { icon: IconShield, title: 'Deposit dikembalikan', text: 'Deposit hanya titipan dan dikembalikan saat mobil kembali tanpa kendala.' },
  ]

  return (
    <>
      <JsonLd data={businessSchema(s)} />

      {/* HERO */}
      <section className="relative isolate overflow-hidden bg-ink text-white">
        <div aria-hidden className="absolute inset-0 -z-10 bg-[radial-gradient(60%_80%_at_85%_0%,rgba(15,123,99,0.55)_0%,transparent_60%),radial-gradient(40%_60%_at_0%_100%,rgba(127,216,189,0.18)_0%,transparent_70%)]" />
        <div aria-hidden className="absolute inset-0 -z-10 opacity-[0.07] [background-image:linear-gradient(#fff_1px,transparent_1px),linear-gradient(90deg,#fff_1px,transparent_1px)] [background-size:44px_44px]" />
        <div className="mx-auto max-w-6xl px-4 pt-14 pb-12 sm:px-6 sm:pt-20 lg:pt-24 lg:pb-16">
          <div className="max-w-3xl">
            <p className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-2xs font-semibold text-mint ring-1 ring-white/15">
              <span className="size-1.5 rounded-full bg-mint" aria-hidden /> Rental mobil{s.business.city ? ` ${s.business.city}` : ''} · {s.business.operating_hours ?? 'Setiap hari'}
            </p>
            <h1 className="mt-5 text-[34px] leading-[1.08] font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">
              {s.hero.title}
            </h1>
            {s.hero.subtitle && <p className="mt-5 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">{s.hero.subtitle}</p>}
            <div className="mt-7 flex flex-wrap gap-3">
              {wa && (
                <a href={wa} target="_blank" rel="noopener" className="btn bg-[#1FAF5A] px-6 text-white hover:bg-[#1a9a4f]">
                  <IconWhatsApp /> Pesan via WhatsApp
                </a>
              )}
              <Link href="/armada" className="btn-ghost-dark px-6">Lihat armada <IconArrow /></Link>
            </div>
          </div>
          <div className="mt-10 lg:mt-14"><QuickSearch /></div>
          <dl className="mt-8 grid grid-cols-3 gap-4 border-t border-white/10 pt-6 text-center sm:max-w-xl sm:text-left">
            <div><dt className="text-2xs text-white/60">Armada siap</dt><dd className="text-2xl font-extrabold">{cars.length || '–'}</dd></div>
            <div><dt className="text-2xs text-white/60">Mulai dari</dt><dd className="text-2xl font-extrabold">{minRate ? `${Math.round(minRate / 1000)}rb` : '–'}<span className="text-sm font-medium text-white/60">/hari</span></dd></div>
            <div><dt className="text-2xs text-white/60">Layanan</dt><dd className="text-base leading-8 font-bold">Lepas kunci & driver</dd></div>
          </dl>
        </div>
      </section>

      {/* NILAI */}
      <section aria-label="Keunggulan" className="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <ul className="grid gap-4 md:grid-cols-3">
          {values.map(({ icon: Icon, title, text }) => (
            <li key={title} className="card flex gap-4 p-5">
              <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-50 text-brand"><Icon /></span>
              <div><h3 className="font-bold">{title}</h3><p className="mt-1 text-sm leading-relaxed text-muted">{text}</p></div>
            </li>
          ))}
        </ul>
      </section>

      {/* ARMADA */}
      <section aria-labelledby="armada-title" className="mx-auto max-w-6xl px-4 pb-16 sm:px-6">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <SectionHeading id="armada-title" eyebrow="Armada" title="Pilih mobil untuk perjalanan Anda" intro="Semua unit rutin diservis dan dibersihkan sebelum diserahkan." />
          <Link href="/armada" className="btn-light">Semua armada <IconArrow /></Link>
        </div>
        {featured.length ? (
          <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            {featured.map((c, i) => <CarCard key={c.slug} car={c} priority={i === 0} />)}
          </div>
        ) : (
          <EmptyCars wa={wa} />
        )}
      </section>

      {/* CARA SEWA */}
      {s.how_to.length > 0 && (
        <section id="cara-sewa" aria-labelledby="cara-title" className="bg-white py-16">
          <div className="mx-auto max-w-6xl px-4 sm:px-6">
            <SectionHeading id="cara-title" eyebrow="Cara sewa" title={`${s.how_to.length} langkah, mobil siap jalan`} />
            <ol className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {s.how_to.map((step, i) => (
                <li key={i} className="relative rounded-[var(--radius-card)] bg-canvas p-5">
                  <span className="text-4xl font-extrabold text-brand/25" aria-hidden>{String(i + 1).padStart(2, '0')}</span>
                  <p className="mt-2 leading-relaxed font-medium"><span className="sr-only">Langkah {i + 1}: </span>{step}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>
      )}

      {/* TESTIMONI */}
      {s.testimonials.length > 0 && (
        <section aria-labelledby="testi-title" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
          <SectionHeading id="testi-title" eyebrow="Testimoni" title="Kata mereka yang sudah menyewa" />
          <ul className="mt-8 grid gap-5 md:grid-cols-3">
            {s.testimonials.map((t, i) => (
              <li key={i} className="card flex flex-col p-6">
                <div className="flex gap-0.5 text-[#E5A100]" role="img" aria-label={`Rating ${t.rating} dari 5`}>
                  {[1, 2, 3, 4, 5].map((n) => <IconStar key={n} filled={n <= t.rating} />)}
                </div>
                <blockquote className="mt-4 flex-1 leading-relaxed">“{t.text}”</blockquote>
                <p className="mt-5 text-sm font-semibold">{t.name}{t.city && <span className="font-normal text-muted"> · {t.city}</span>}</p>
              </li>
            ))}
          </ul>
        </section>
      )}

      {/* SYARAT + FAQ */}
      <section className="bg-white py-16">
        <div className="mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.9fr_1.1fr]">
          {s.terms.length > 0 && (
            <div aria-labelledby="syarat-title">
              <SectionHeading id="syarat-title" eyebrow="Syarat sewa" title="Yang perlu disiapkan" />
              <ul className="mt-6 space-y-3">
                {s.terms.slice(0, 6).map((t, i) => (
                  <li key={i} className="flex gap-3 leading-relaxed">
                    <span className="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-brand"><IconCheck /></span>{t}
                  </li>
                ))}
              </ul>
              <Link href="/syarat-ketentuan" className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
                Baca syarat & ketentuan lengkap <IconArrow />
              </Link>
            </div>
          )}
          {s.faq.length > 0 && (
            <div id="faq" aria-labelledby="faq-title" className="scroll-mt-20">
              <SectionHeading id="faq-title" eyebrow="FAQ" title="Pertanyaan yang sering diajukan" />
              <div className="mt-6"><FaqList items={s.faq} /></div>
            </div>
          )}
        </div>
      </section>

      {/* KONTAK */}
      <section id="kontak" aria-labelledby="kontak-title" className="mx-auto max-w-6xl px-4 py-16 sm:px-6">
        <div className="overflow-hidden rounded-[28px] bg-ink text-white">
          <div className="grid gap-8 p-6 sm:p-10 md:grid-cols-[1.2fr_1fr] md:items-center">
            <div>
              <p className="eyebrow text-mint">Kontak</p>
              <h2 id="kontak-title" className="mt-2 text-3xl font-extrabold tracking-tight text-balance sm:text-4xl">Siap berangkat? Tanya dulu juga boleh.</h2>
              <p className="mt-3 max-w-lg text-white/70">Tim kami membalas WhatsApp {s.business.operating_hours ? s.business.operating_hours.toLowerCase() : 'setiap hari'}.</p>
              <div className="mt-6 flex flex-wrap gap-3">
                {wa && <a href={wa} target="_blank" rel="noopener" className="btn bg-[#1FAF5A] px-6 text-white hover:bg-[#1a9a4f]"><IconWhatsApp /> Chat WhatsApp</a>}
                <Link href="/armada" className="btn-ghost-dark px-6">Cek ketersediaan</Link>
              </div>
            </div>
            <ul className="space-y-4 rounded-2xl bg-white/5 p-5 ring-1 ring-white/10">
              {s.business.address && (
                <li className="flex gap-3"><IconPin className="mt-0.5 size-5 shrink-0 text-mint" />
                  <div><p className="text-2xs text-white/55">Alamat</p><p>{s.business.address}</p>
                    {s.business.maps_url && <a href={s.business.maps_url} target="_blank" rel="noopener" className="mt-1 inline-block text-sm font-semibold text-mint hover:underline">Buka di Google Maps</a>}
                  </div>
                </li>
              )}
              {s.business.operating_hours && (
                <li className="flex gap-3"><IconClock className="mt-0.5 size-5 shrink-0 text-mint" /><div><p className="text-2xs text-white/55">Jam operasional</p><p>{s.business.operating_hours}</p></div></li>
              )}
              {s.business.whatsapp && (
                <li className="flex gap-3"><IconWhatsApp className="mt-0.5 size-5 shrink-0 text-mint" /><div><p className="text-2xs text-white/55">WhatsApp</p><p>{s.business.whatsapp}</p></div></li>
              )}
            </ul>
          </div>
        </div>
      </section>
    </>
  )
}

function EmptyCars({ wa }: { wa: string | null }) {
  return (
    <div className="card mt-8 p-10 text-center">
      <p className="text-lg font-bold">Daftar armada sedang diperbarui</p>
      <p className="mt-1 text-muted">Hubungi kami untuk menanyakan unit yang tersedia.</p>
      {wa && <a href={wa} target="_blank" rel="noopener" className="btn-primary mt-5"><IconWhatsApp className="size-4" /> Tanya via WhatsApp</a>}
    </div>
  )
}
