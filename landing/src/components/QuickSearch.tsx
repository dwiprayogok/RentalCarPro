'use client'

import { useRouter } from 'next/navigation'
import { useEffect, useState } from 'react'
import { wibLocal, wibTomorrowAt } from '@/lib/format'
import { IconArrow } from './Icons'

/** Form cek ketersediaan cepat di hero → ke /armada dengan tanggal terisi. */
export function QuickSearch() {
  const router = useRouter()
  const [start, setStart] = useState('')
  const [end, setEnd] = useState('')
  const [service, setService] = useState('lepas_kunci')
  const [min, setMin] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    setStart(wibTomorrowAt(9))
    setEnd(wibTomorrowAt(9, 2))
    setMin(wibLocal(1))
  }, [])

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault()
        if (!start || !end || end <= start) return setError('Waktu kembali harus setelah waktu ambil.')
        setError('')
        router.push(`/armada?${new URLSearchParams({ start, end, service })}#hasil`)
      }}
      className="rounded-3xl bg-white p-4 text-ink shadow-2xl shadow-black/25 sm:p-5"
      aria-label="Cek ketersediaan mobil"
    >
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_0.9fr_auto] lg:items-end">
        <label className="block">
          <span className="label">Ambil mobil</span>
          <input type="datetime-local" className="field" value={start} min={min} step={1800} required onChange={(e) => setStart(e.target.value)} />
        </label>
        <label className="block">
          <span className="label">Kembalikan</span>
          <input type="datetime-local" className="field" value={end} min={start || min} step={1800} required onChange={(e) => setEnd(e.target.value)} />
        </label>
        <label className="block sm:col-span-2 lg:col-span-1">
          <span className="label">Layanan</span>
          <select className="field" value={service} onChange={(e) => setService(e.target.value)}>
            <option value="lepas_kunci">Lepas kunci</option>
            <option value="dengan_driver">Dengan driver</option>
          </select>
        </label>
        <button type="submit" className="btn-primary h-11 sm:col-span-2 lg:col-span-1">
          Cek mobil tersedia <IconArrow />
        </button>
      </div>
      <p className="mt-2 text-2xs text-muted" aria-live="polite">{error || 'Semua waktu dalam WIB.'}</p>
    </form>
  )
}
