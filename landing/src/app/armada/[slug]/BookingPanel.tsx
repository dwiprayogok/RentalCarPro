'use client'

import { useSearchParams } from 'next/navigation'
import { useEffect, useState } from 'react'
import type { Period, ServiceType } from '@/lib/types'
import { ApiError, publicFetch } from '@/lib/client-api'
import { rupiah, wibLocal, wibToIso, wibTomorrowAt } from '@/lib/format'
import { IconCheck, IconWhatsApp } from '@/components/Icons'

type Props = { slug: string; carName: string; hasDriver: boolean; whatsappUrl: string | null }
type Errors = Record<string, string>
const DT = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/

export function BookingPanel({ slug, carName, hasDriver, whatsappUrl }: Props) {
  const sp = useSearchParams()
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [service, setService] = useState<ServiceType>('lepas_kunci')
  const [min, setMin] = useState('')
  const [period, setPeriod] = useState<Period | null>(null)
  const [checking, setChecking] = useState(false)
  const [form, setForm] = useState({ name: '', phone: '', notes: '', website: '' })
  const [errors, setErrors] = useState<Errors>({})
  const [sending, setSending] = useState(false)
  const [done, setDone] = useState<{ message: string; whatsapp_url: string | null } | null>(null)

  useEffect(() => {
    const s = sp.get('start') ?? '', e = sp.get('end') ?? ''
    setStart(DT.test(s) ? s : wibTomorrowAt(9))
    setEnd(DT.test(e) ? e : wibTomorrowAt(9, 2))
    if (sp.get('service') === 'dengan_driver' && hasDriver) setService('dengan_driver')
    setMin(wibLocal(1))
  }, [sp, hasDriver])

  // Cek ketersediaan otomatis (debounce) setiap tanggal/layanan berubah.
  useEffect(() => {
    if (!start || !end) return
    if (end <= start) { setPeriod(null); setErrors((x) => ({ ...x, end_at: 'Waktu kembali harus setelah waktu ambil.' })); return }
    setErrors((x) => Object.fromEntries(Object.entries(x).filter(([k]) => !['start_at', 'end_at', 'period'].includes(k))))
    const ctrl = new AbortController()
    const t = setTimeout(() => {
      setChecking(true)
      publicFetch<{ data: Period }>(`/availability?${new URLSearchParams({ car: slug, start: wibToIso(start), end: wibToIso(end), service })}`, { signal: ctrl.signal })
        .then((r) => setPeriod(r.data))
        .catch((e) => {
          if (ctrl.signal.aborted) return
          setPeriod(null)
          setErrors((x) => ({ ...x, period: e instanceof ApiError ? Object.values(e.errors)[0]?.[0] ?? e.message : 'Gagal memeriksa ketersediaan.' }))
        })
        .finally(() => !ctrl.signal.aborted && setChecking(false))
    }, 350)
    return () => { clearTimeout(t); ctrl.abort() }
  }, [slug, start, end, service])

  const edit = (k: keyof typeof form, value: string) => {
    setForm((f) => ({ ...f, [k]: value }))
    setErrors((x) => (x[k] ? Object.fromEntries(Object.entries(x).filter(([key]) => key !== k)) : x))
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault()
    const local: Errors = {}
    if (form.name.trim().length < 2) local.name = 'Nama wajib diisi.'
    if (form.phone.replace(/\D/g, '').length < 9) local.phone = 'Nomor WhatsApp wajib diisi.'
    if (end <= start) local.end_at = 'Waktu kembali harus setelah waktu ambil.'
    if (Object.keys(local).length) { setErrors(local); return }

    setSending(true)
    setErrors({})
    try {
      const r = await publicFetch<{ message: string; whatsapp_url: string | null }>('/booking-requests', {
        method: 'POST',
        body: JSON.stringify({ ...form, car: slug, service_type: service, start_at: wibToIso(start), end_at: wibToIso(end) }),
      })
      setDone(r)
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        setErrors(Object.fromEntries(Object.entries(err.errors).map(([k, v]) => [k, v[0]])))
      } else {
        setErrors({ form: err instanceof Error ? err.message : 'Gagal mengirim permintaan.' })
      }
    } finally {
      setSending(false)
    }
  }

  if (done) {
    return (
      <div className="card p-6 text-center" role="status">
        <span className="mx-auto grid size-14 place-items-center rounded-full bg-brand-50 text-brand"><IconCheck className="size-7" /></span>
        <h2 className="mt-4 text-xl font-extrabold">Permintaan terkirim</h2>
        <p className="mt-2 leading-relaxed text-muted">{done.message}</p>
        {done.whatsapp_url && (
          <a href={done.whatsapp_url} target="_blank" rel="noopener" className="btn mt-5 w-full bg-[#1FAF5A] text-white hover:bg-[#1a9a4f]">
            <IconWhatsApp /> Konfirmasi lebih cepat via WhatsApp
          </a>
        )}
        <button type="button" className="mt-3 text-sm font-semibold text-brand hover:underline" onClick={() => { setDone(null); setForm({ name: '', phone: '', notes: '', website: '' }) }}>
          Kirim permintaan lain
        </button>
      </div>
    )
  }

  const err = (k: string) => errors[k] && <p id={`${k}-err`} className="mt-1 text-2xs font-medium text-danger">{errors[k]}</p>
  const aria = (k: string) => (errors[k] ? { 'aria-invalid': true, 'aria-describedby': `${k}-err` } : {})

  return (
    <form onSubmit={submit} noValidate className="card overflow-hidden" aria-labelledby="pesan-title">
      <div className="border-b border-line p-5">
        <h2 id="pesan-title" className="text-lg font-extrabold">Cek & ajukan sewa</h2>
        <p className="text-2xs text-muted">Belum ada pembayaran. Tim kami konfirmasi via WhatsApp.</p>
      </div>
      <div className="space-y-4 p-5">
        {hasDriver && (
          <div className="grid grid-cols-2 gap-1 rounded-full bg-canvas p-1" role="radiogroup" aria-label="Layanan">
            {(['lepas_kunci', 'dengan_driver'] as const).map((v) => (
              <button key={v} type="button" role="radio" aria-checked={service === v} onClick={() => setService(v)}
                className={`min-h-10 rounded-full text-sm font-semibold transition ${service === v ? 'bg-white text-ink shadow-sm' : 'text-muted'}`}>
                {v === 'lepas_kunci' ? 'Lepas kunci' : 'Dengan driver'}
              </button>
            ))}
          </div>
        )}
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
          <label className="block"><span className="label">Ambil</span>
            <input type="datetime-local" className="field" min={min} step={1800} value={start} onChange={(e) => setStart(e.target.value)} required {...aria('start_at')} />
            {err('start_at')}
          </label>
          <label className="block"><span className="label">Kembali</span>
            <input type="datetime-local" className="field" min={start || min} step={1800} value={end} onChange={(e) => setEnd(e.target.value)} required {...aria('end_at')} />
            {err('end_at')}
          </label>
        </div>

        <div aria-live="polite" className="rounded-2xl bg-canvas p-4">
          {checking && !period ? (
            <p className="text-sm text-muted">Memeriksa ketersediaan…</p>
          ) : errors.period ? (
            <p className="text-sm font-medium text-danger">{errors.period}</p>
          ) : period ? (
            <>
              <p className={`flex items-center gap-2 text-sm font-bold ${period.available ? 'text-brand' : 'text-danger'}`}>
                <span className={`size-2 rounded-full ${period.available ? 'bg-brand' : 'bg-danger'}`} aria-hidden />
                {period.available ? 'Tersedia di tanggal ini' : 'Tidak tersedia di tanggal ini'}
              </p>
              <dl className={`mt-3 space-y-1.5 text-sm ${checking ? 'opacity-60' : ''}`}>
                <div className="flex justify-between"><dt className="text-muted">Durasi</dt><dd className="font-medium">{period.days} hari</dd></div>
                <div className="flex justify-between"><dt className="text-muted">Estimasi sewa</dt><dd className="text-lg font-extrabold">{rupiah(period.estimated_total)}</dd></div>
                {period.deposit > 0 && <div className="flex justify-between"><dt className="text-muted">Deposit (dikembalikan)</dt><dd className="font-medium">{rupiah(period.deposit)}</dd></div>}
              </dl>
              {!period.available && <p className="mt-2 text-2xs text-muted">Coba geser tanggal (lihat kalender), atau tetap kirim permintaan dan kami carikan alternatif.</p>}
            </>
          ) : (
            <p className="text-sm text-muted">Pilih tanggal untuk melihat estimasi.</p>
          )}
        </div>

        <label className="block"><span className="label">Nama lengkap</span>
          <input className="field" autoComplete="name" maxLength={100} value={form.name} onChange={(e) => edit('name', e.target.value)} {...aria('name')} />
          {err('name')}
        </label>
        <label className="block"><span className="label">Nomor WhatsApp</span>
          <input className="field" type="tel" inputMode="tel" autoComplete="tel" placeholder="08xx-xxxx-xxxx" maxLength={20} value={form.phone} onChange={(e) => edit('phone', e.target.value)} {...aria('phone')} />
          {err('phone')}
        </label>
        <label className="block"><span className="label">Catatan <span className="font-normal text-muted">(opsional)</span></span>
          <textarea className="field min-h-20 py-2" maxLength={500} placeholder="Mis. antar ke stasiun, perlu kursi bayi" value={form.notes} onChange={(e) => edit('notes', e.target.value)} {...aria('notes')} />
          {err('notes')}
        </label>
        {/* Honeypot anti-bot: tersembunyi dari manusia & pembaca layar. */}
        <div aria-hidden className="absolute -left-[9999px] h-0 w-0 overflow-hidden">
          <label>Website<input tabIndex={-1} autoComplete="off" value={form.website} onChange={(e) => edit('website', e.target.value)} name="website" /></label>
        </div>
        {(errors.car || errors.service_type || errors.form) && (
          <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm font-medium text-danger">{errors.car || errors.service_type || errors.form}</p>
        )}
        <button className="btn-primary w-full" disabled={sending}>{sending ? 'Mengirim…' : 'Kirim permintaan sewa'}</button>
        {whatsappUrl && (
          <a className="btn-light w-full" target="_blank" rel="noopener"
            href={`${whatsappUrl}?text=${encodeURIComponent(`Halo, saya tertarik sewa ${carName}. Apakah tersedia?`)}`}>
            <IconWhatsApp className="size-4 text-[#1FAF5A]" /> Tanya dulu via WhatsApp
          </a>
        )}
      </div>
    </form>
  )
}
