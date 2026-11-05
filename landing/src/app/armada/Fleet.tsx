'use client'

import { usePathname, useRouter, useSearchParams } from 'next/navigation'
import { useEffect, useMemo, useOptimistic, useState, useTransition } from 'react'
import type { Period, PublicCar } from '@/lib/types'
import { ApiError, publicFetch } from '@/lib/client-api'
import { waLink, wibLocal, wibToIso } from '@/lib/format'
import { CarCard } from '@/components/CarCard'
import { IconWhatsApp } from '@/components/Icons'

type Filters = { transmission: string; seats: string; sort: string; service: string; start: string; end: string; tersedia: string }

const DT = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/

export function Fleet({ cars, whatsappUrl }: { cars: PublicCar[]; whatsappUrl: string | null }) {
  const router = useRouter()
  const pathname = usePathname()
  const sp = useSearchParams()
  // Filter baru langsung tampil aktif (optimistic) selama URL diperbarui di latar belakang.
  const [isPending, startTransition] = useTransition()
  const [optimistic, applyOptimistic] = useOptimistic<Partial<Filters>, Partial<Filters>>({}, (cur, patch) => ({ ...cur, ...patch }))
  const fromUrl: Filters = {
    transmission: sp.get('transmission') ?? '',
    seats: sp.get('seats') ?? '',
    sort: sp.get('sort') ?? 'price_asc',
    service: sp.get('service') === 'dengan_driver' ? 'dengan_driver' : 'lepas_kunci',
    start: DT.test(sp.get('start') ?? '') ? sp.get('start')! : '',
    end: DT.test(sp.get('end') ?? '') ? sp.get('end')! : '',
    tersedia: sp.get('tersedia') ?? '',
  }
  const f: Filters = { ...fromUrl, ...optimistic }
  const set = (patch: Partial<Filters>) => {
    const next = new URLSearchParams()
    Object.entries({ ...f, ...patch }).forEach(([k, v]) => v && !(k === 'sort' && v === 'price_asc') && !(k === 'service' && v === 'lepas_kunci') && next.set(k, v))
    startTransition(() => {
      applyOptimistic(patch)
      router.replace(`${pathname}${next.size ? `?${next}` : ''}`, { scroll: false })
    })
  }

  // Draf tanggal: baru dikirim saat tombol "Cek" ditekan.
  const [draft, setDraft] = useState({ start: f.start, end: f.end })
  useEffect(() => setDraft({ start: f.start, end: f.end }), [f.start, f.end])
  const [min, setMin] = useState('')
  useEffect(() => setMin(wibLocal(1)), [])

  const [periods, setPeriods] = useState<Record<string, Period> | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!f.start || !f.end) { setPeriods(null); setError(''); return }
    if (f.end <= f.start) { setPeriods(null); setError('Waktu kembali harus setelah waktu ambil.'); return }
    const ctrl = new AbortController()
    setLoading(true)
    setError('')
    publicFetch<{ data: PublicCar[] }>(`/cars?${new URLSearchParams({ start: wibToIso(f.start), end: wibToIso(f.end), service: f.service })}`, { signal: ctrl.signal })
      .then((r) => setPeriods(Object.fromEntries(r.data.filter((c) => c.period).map((c) => [c.slug, c.period!]))))
      .catch((e) => {
        if (ctrl.signal.aborted) return
        setPeriods(null)
        setError(e instanceof ApiError ? Object.values(e.errors)[0]?.[0] ?? e.message : 'Gagal memeriksa ketersediaan.')
      })
      .finally(() => !ctrl.signal.aborted && setLoading(false))
    return () => ctrl.abort()
  }, [f.start, f.end, f.service])

  const list = useMemo(() => {
    let out = cars.map((c) => ({ ...c, period: periods?.[c.slug] }))
    if (f.transmission) out = out.filter((c) => c.transmission.toLowerCase() === f.transmission)
    if (f.seats) out = out.filter((c) => c.seats >= Number(f.seats))
    if (periods && f.tersedia) out = out.filter((c) => c.period?.available)
    const rate = (c: PublicCar) => (f.service === 'dengan_driver' ? c.rate_with_driver ?? Infinity : c.rate_self_drive)
    const price = (c: PublicCar) => c.period?.estimated_total ?? rate(c)
    out.sort((a, b) => (f.sort === 'name' ? a.name.localeCompare(b.name, 'id') : f.sort === 'price_desc' ? price(b) - price(a) : price(a) - price(b)))
    if (periods) out.sort((a, b) => Number(!!b.period?.available) - Number(!!a.period?.available))
    return out
  }, [cars, periods, f.transmission, f.seats, f.sort, f.service, f.tersedia])

  const busy = isPending || loading
  const busyText = loading ? 'Memeriksa ketersediaan…' : 'Memuat mobil…'
  const query = f.start && f.end ? `?${new URLSearchParams({ start: f.start, end: f.end, service: f.service })}` : ''
  const availableCount = periods ? Object.values(periods).filter((p) => p.available).length : 0
  const seatOptions = [...new Set(cars.map((c) => c.seats))].sort((a, b) => a - b)

  return (
    <>
      <form
        className="card p-4 shadow-xl shadow-ink/5 sm:p-5"
        onSubmit={(e) => { e.preventDefault(); set({ start: draft.start, end: draft.end }) }}
        aria-label="Cek ketersediaan"
      >
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_0.8fr_auto] lg:items-end">
          <label className="block"><span className="label">Ambil mobil</span>
            <input type="datetime-local" className="field" min={min} step={1800} value={draft.start} onChange={(e) => setDraft((d) => ({ ...d, start: e.target.value }))} required />
          </label>
          <label className="block"><span className="label">Kembalikan</span>
            <input type="datetime-local" className="field" min={draft.start || min} step={1800} value={draft.end} onChange={(e) => setDraft((d) => ({ ...d, end: e.target.value }))} required />
          </label>
          <label className="block sm:col-span-2 lg:col-span-1"><span className="label">Layanan</span>
            <select className="field" value={f.service} onChange={(e) => set({ service: e.target.value })}>
              <option value="lepas_kunci">Lepas kunci</option>
              <option value="dengan_driver">Dengan driver</option>
            </select>
          </label>
          <div className="flex gap-2 sm:col-span-2 lg:col-span-1">
            <button className="btn-primary h-11 flex-1" disabled={loading} aria-busy={loading}>
              {loading ? <><Spinner className="size-4 border-white/40 border-t-white" /> Memeriksa…</> : 'Cek'}
            </button>
            {(f.start || f.end) && <button type="button" className="btn-light h-11" onClick={() => set({ start: '', end: '', tersedia: '' })}>Reset</button>}
          </div>
        </div>
        <p className={`mt-2 text-2xs ${error ? 'font-medium text-danger' : 'text-muted'}`} role={error ? 'alert' : undefined}>
          {error || 'Semua waktu dalam WIB.'}
        </p>
      </form>

      <div id="hasil" className="mt-6 flex scroll-mt-24 flex-wrap items-center gap-2">
        <Chip active={!f.transmission} onClick={() => set({ transmission: '' })}>Semua</Chip>
        <Chip active={f.transmission === 'matic'} onClick={() => set({ transmission: 'matic' })}>Matic</Chip>
        <Chip active={f.transmission === 'manual'} onClick={() => set({ transmission: 'manual' })}>Manual</Chip>
        <label className="ml-auto flex items-center gap-2 text-sm text-muted">
          <span className="sr-only sm:not-sr-only">Kursi</span>
          <select className="min-h-10 rounded-full border border-line bg-white px-3 text-sm text-ink" value={f.seats} onChange={(e) => set({ seats: e.target.value })} aria-label="Minimal kursi">
            <option value="">Semua kursi</option>
            {seatOptions.map((n) => <option key={n} value={n}>≥ {n} kursi</option>)}
          </select>
        </label>
        <select className="min-h-10 rounded-full border border-line bg-white px-3 text-sm" value={f.sort} onChange={(e) => set({ sort: e.target.value })} aria-label="Urutkan">
          <option value="price_asc">Harga terendah</option>
          <option value="price_desc">Harga tertinggi</option>
          <option value="name">Nama A–Z</option>
        </select>
      </div>

      <div className="relative mt-4 h-1 overflow-hidden rounded-full bg-line/60" aria-hidden>
        {busy && <span className="loading-bar absolute inset-y-0 w-1/3 rounded-full bg-brand" />}
      </div>

      <div className="mt-3 flex min-h-6 flex-wrap items-center justify-between gap-2 text-sm text-muted" aria-live="polite">
        {busy ? (
          <span className="delayed-in inline-flex items-center gap-2 font-medium text-brand"><Spinner /> {busyText}</span>
        ) : (
          <span>{periods ? `${availableCount} dari ${cars.length} mobil tersedia di tanggal pilihan` : `${list.length} mobil`}</span>
        )}
        {periods && (
          <label className="inline-flex items-center gap-2">
            <input type="checkbox" className="size-4 accent-brand" checked={!!f.tersedia} onChange={(e) => set({ tersedia: e.target.checked ? '1' : '' })} />
            Hanya yang tersedia
          </label>
        )}
      </div>

      {loading && !periods ? (
        <div className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true" aria-label={busyText}>
          {Array.from({ length: Math.min(Math.max(list.length, 3), 6) }, (_, i) => <CardSkeleton key={i} />)}
        </div>
      ) : list.length ? (
        <div className="mt-4" aria-busy={busy}>
          <div className={`grid gap-5 transition-opacity sm:grid-cols-2 lg:grid-cols-3 ${busy ? 'pointer-events-none opacity-50 delay-150' : 'opacity-100'}`}>
            {list.map((c, i) => <CarCard key={c.slug} car={c} query={query} priority={i < 2} />)}
          </div>
        </div>
      ) : (
        <div className="card mt-4 p-10 text-center">
          <p className="text-lg font-bold">Tidak ada mobil yang cocok</p>
          <p className="mt-1 text-muted">Coba ubah filter atau tanggal. Kami juga bisa bantu carikan lewat WhatsApp.</p>
          <div className="mt-5 flex flex-wrap justify-center gap-3">
            <button className="btn-light" onClick={() => set({ transmission: '', seats: '', tersedia: '' })}>Hapus filter</button>
            {whatsappUrl && (
              <a className="btn-primary" target="_blank" rel="noopener" href={waLink(whatsappUrl, 'Halo, saya mencari mobil sewa. Unit apa yang tersedia?')!}>
                <IconWhatsApp className="size-4" /> Tanya via WhatsApp
              </a>
            )}
          </div>
        </div>
      )}
    </>
  )
}

function Chip({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button type="button" onClick={onClick} aria-pressed={active}
      className={`min-h-10 rounded-full px-4 text-sm font-semibold transition-colors ${active ? 'bg-ink text-white' : 'bg-white text-ink ring-1 ring-line hover:bg-canvas'}`}>
      {children}
    </button>
  )
}

function Spinner({ className = 'size-4 border-brand/25 border-t-brand' }: { className?: string }) {
  return <span aria-hidden className={`inline-block animate-spin rounded-full border-2 motion-reduce:animate-none ${className}`} />
}

function CardSkeleton() {
  return (
    <div className="card overflow-hidden" aria-hidden>
      <div className="skeleton aspect-[16/10]" />
      <div className="space-y-3 p-5">
        <div className="skeleton h-3 w-24 rounded-full" />
        <div className="skeleton h-5 w-40 rounded-full" />
        <div className="skeleton h-3 w-52 rounded-full" />
        <div className="flex items-end justify-between pt-3">
          <div className="skeleton h-7 w-32 rounded-full" />
          <div className="skeleton h-10 w-16 rounded-full" />
        </div>
      </div>
    </div>
  )
}
