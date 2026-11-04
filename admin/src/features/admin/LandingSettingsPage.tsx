import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, Plus, Save, Star, Trash2 } from 'lucide-react'
import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Textarea } from '@/components/ui/Field'
import { ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'

interface Faq { q: string; a: string }
interface Testimonial { name: string; city: string; text: string; rating: number }
interface LandingValues {
  landing_hero_title: string
  landing_hero_subtitle: string
  landing_city: string
  landing_operating_hours: string
  landing_maps_url: string
  landing_how_to: string[]
  landing_terms: string[]
  landing_faq: Faq[]
  landing_testimonials: Testimonial[]
}
const KEYS: (keyof LandingValues)[] = ['landing_hero_title', 'landing_hero_subtitle', 'landing_city', 'landing_operating_hours', 'landing_maps_url', 'landing_how_to', 'landing_terms', 'landing_faq', 'landing_testimonials']
export const LANDING_URL = (import.meta.env.VITE_LANDING_URL ?? 'http://localhost:3000').replace(/\/$/, '')

function ListEditor<T>({ items, onChange, empty, render, add, max, label }: {
  items: T[]; onChange: (v: T[]) => void; empty: T; render: (item: T, set: (v: T) => void, i: number) => ReactNode; add: string; max: number; label: string
}) {
  return (
    <div className="space-y-2 sm:col-span-2">
      {items.length === 0 && <p className="rounded-xl bg-canvas px-4 py-3 text-center text-xs text-ink-muted">Belum ada {label}.</p>}
      {items.map((item, i) => (
        <div key={i} className="flex items-start gap-2 rounded-xl border border-line p-3">
          <span className="mt-2 grid size-6 shrink-0 place-items-center rounded-full bg-brand-50 text-2xs font-bold text-brand-700">{i + 1}</span>
          <div className="min-w-0 flex-1">{render(item, (v) => onChange(items.map((x, j) => (j === i ? v : x))), i)}</div>
          <button type="button" onClick={() => onChange(items.filter((_, j) => j !== i))} className="mt-1 rounded p-1.5 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Hapus ${label} ${i + 1}`}><Trash2 className="size-4" /></button>
        </div>
      ))}
      {items.length < max && <Button size="sm" variant="secondary" onClick={() => onChange([...items, empty])}><Plus className="size-3.5" /> {add}</Button>}
    </div>
  )
}

/** Konten landing page (owner, landing.manage). Identitas usaha & WhatsApp diatur di Pengaturan. */
export function LandingSettingsPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const query = useQuery({ queryKey: ['settings'], queryFn: async () => (await api.get<{ data: { key: string; value: unknown }[] }>('/settings')).data.data })
  const [v, setV] = useState<LandingValues | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (!query.data) return
    const m = Object.fromEntries(query.data.map((s) => [s.key, s.value]))
    setV({
      landing_hero_title: String(m.landing_hero_title ?? ''), landing_hero_subtitle: String(m.landing_hero_subtitle ?? ''),
      landing_city: String(m.landing_city ?? ''), landing_operating_hours: String(m.landing_operating_hours ?? ''), landing_maps_url: String(m.landing_maps_url ?? ''),
      landing_how_to: (m.landing_how_to as string[]) ?? [], landing_terms: (m.landing_terms as string[]) ?? [],
      landing_faq: (m.landing_faq as Faq[]) ?? [], landing_testimonials: ((m.landing_testimonials as Testimonial[]) ?? []).map((t) => ({ ...t, city: t.city ?? '' })),
    })
  }, [query.data])

  const save = useMutation({
    mutationFn: async (settings: LandingValues) => (await api.put<{ message: string; data: unknown }>('/settings', { settings })).data,
    onSuccess: (r) => { toast(`${r.message} Landing page diperbarui otomatis.`); setErrors({}); qc.setQueryData(['settings'], r.data) },
    onError: (e) => { const fe = fieldErrors(e); setErrors(Object.fromEntries(Object.entries(fe).map(([k, m]) => [k.replace(/^settings\./, ''), m]))); if (!Object.keys(fe).length) toast(errorMessage(e), 'error') },
  })

  if (query.isLoading || (!v && !query.isError)) return <><PageHeader eyebrow="Sistem" title="Pengaturan Landing" /><Skeleton className="h-96" /></>
  if (query.isError || !v) return <><PageHeader eyebrow="Sistem" title="Pengaturan Landing" /><div className="card"><ErrorState error={query.error} onRetry={() => query.refetch()} /></div></>

  const set = <K extends keyof LandingValues>(k: K, val: LandingValues[K]) => setV((x) => (x ? { ...x, [k]: val } : x))
  const err = (prefix: string) => Object.entries(errors).find(([k]) => k === prefix || k.startsWith(`${prefix}.`))?.[1]
  const hasErrors = Object.keys(errors).length > 0

  return (
    <form onSubmit={(e) => { e.preventDefault(); save.mutate(Object.fromEntries(KEYS.map((k) => [k, v[k]])) as unknown as LandingValues) }} noValidate>
      <PageHeader
        eyebrow="Sistem"
        title="Pengaturan Landing"
        subtitle="Konten halaman publik. Perubahan langsung tampil di landing (cache diperbarui otomatis)."
        action={
          <div className="flex gap-2">
            <a href={LANDING_URL} target="_blank" rel="noreferrer" className="inline-flex h-10 items-center gap-2 rounded-[10px] border border-line-strong bg-white px-4 text-sm font-semibold text-ink hover:bg-canvas"><ExternalLink className="size-4" /> Buka landing</a>
            <Button type="submit" loading={save.isPending}><Save className="size-4" /> Simpan</Button>
          </div>
        }
      />
      {hasErrors && <div className="mb-4"><Alert>Periksa kembali isian yang ditandai merah.</Alert></div>}

      <div className="card space-y-6 p-5">
        <FormSection title="Hero" description="Bagian pertama yang dilihat calon penyewa.">
          <Field label="Judul" className="sm:col-span-2" error={err('landing_hero_title')}>{(a) => <Input {...a} value={v.landing_hero_title} onChange={(e) => set('landing_hero_title', e.target.value)} invalid={!!err('landing_hero_title')} />}</Field>
          <Field label="Subjudul" className="sm:col-span-2" error={err('landing_hero_subtitle')}>{(a) => <Textarea {...a} rows={2} value={v.landing_hero_subtitle} onChange={(e) => set('landing_hero_subtitle', e.target.value)} />}</Field>
        </FormSection>

        <FormSection title="Kontak & lokasi" description="Nama usaha, alamat, dan WhatsApp diambil dari Pengaturan → Identitas usaha.">
          <Field label="Kota layanan" hint="Dipakai di judul & SEO, mis. Bogor." error={err('landing_city')}>{(a) => <Input {...a} value={v.landing_city} onChange={(e) => set('landing_city', e.target.value)} />}</Field>
          <Field label="Jam operasional" error={err('landing_operating_hours')}>{(a) => <Input {...a} value={v.landing_operating_hours} onChange={(e) => set('landing_operating_hours', e.target.value)} />}</Field>
          <Field label="Tautan Google Maps" className="sm:col-span-2" hint="https://maps.google.com/... atau https://maps.app.goo.gl/..." error={err('landing_maps_url')}>{(a) => <Input {...a} type="url" value={v.landing_maps_url} onChange={(e) => set('landing_maps_url', e.target.value)} invalid={!!err('landing_maps_url')} />}</Field>
          <p className="text-xs text-ink-muted sm:col-span-2"><Link to="/pengaturan" className="font-semibold text-brand-600 hover:underline">Ubah nama usaha, alamat, & WhatsApp</Link></p>
        </FormSection>

        <FormSection title="Cara sewa">
          <ListEditor label="langkah" add="Tambah langkah" max={8} items={v.landing_how_to} empty="" onChange={(x) => set('landing_how_to', x)}
            render={(item, s, i) => <Input aria-label={`Langkah ${i + 1}`} value={item} onChange={(e) => s(e.target.value)} invalid={!!err(`landing_how_to.${i}`)} />} />
        </FormSection>

        <FormSection title="Syarat sewa">
          <ListEditor label="syarat" add="Tambah syarat" max={15} items={v.landing_terms} empty="" onChange={(x) => set('landing_terms', x)}
            render={(item, s, i) => <Input aria-label={`Syarat ${i + 1}`} value={item} onChange={(e) => s(e.target.value)} invalid={!!err(`landing_terms.${i}`)} />} />
        </FormSection>

        <FormSection title="FAQ">
          <ListEditor label="pertanyaan" add="Tambah pertanyaan" max={15} items={v.landing_faq} empty={{ q: '', a: '' }} onChange={(x) => set('landing_faq', x)}
            render={(item, s, i) => (
              <div className="space-y-2">
                <Input aria-label={`Pertanyaan ${i + 1}`} placeholder="Pertanyaan" value={item.q} onChange={(e) => s({ ...item, q: e.target.value })} invalid={!!err(`landing_faq.${i}.q`)} />
                <Textarea aria-label={`Jawaban ${i + 1}`} rows={2} placeholder="Jawaban" value={item.a} onChange={(e) => s({ ...item, a: e.target.value })} invalid={!!err(`landing_faq.${i}.a`)} />
                {(err(`landing_faq.${i}.q`) || err(`landing_faq.${i}.a`)) && <p className="text-2xs font-medium text-red-600">{err(`landing_faq.${i}.q`) ?? err(`landing_faq.${i}.a`)}</p>}
              </div>
            )} />
        </FormSection>

        <FormSection title="Testimoni" description="Tampilkan hanya ulasan asli dari pelanggan yang setuju dipublikasikan.">
          <ListEditor label="testimoni" add="Tambah testimoni" max={12} items={v.landing_testimonials} empty={{ name: '', city: '', text: '', rating: 5 }} onChange={(x) => set('landing_testimonials', x)}
            render={(item, s, i) => (
              <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                <Input aria-label={`Nama testimoni ${i + 1}`} placeholder="Nama (mis. Rizky A.)" value={item.name} onChange={(e) => s({ ...item, name: e.target.value })} invalid={!!err(`landing_testimonials.${i}.name`)} />
                <Input aria-label={`Kota testimoni ${i + 1}`} placeholder="Kota" value={item.city} onChange={(e) => s({ ...item, city: e.target.value })} />
                <div className="flex items-center gap-0.5" role="radiogroup" aria-label={`Rating testimoni ${i + 1}`}>
                  {[1, 2, 3, 4, 5].map((n) => (
                    <button key={n} type="button" role="radio" aria-checked={item.rating === n} aria-label={`${n} bintang`} onClick={() => s({ ...item, rating: n })}>
                      <Star className={cn('size-5', n <= item.rating ? 'fill-amber-400 text-amber-400' : 'text-line-strong')} />
                    </button>
                  ))}
                </div>
                <Textarea className="sm:col-span-3" aria-label={`Ulasan ${i + 1}`} rows={2} placeholder="Ulasan" value={item.text} onChange={(e) => s({ ...item, text: e.target.value })} invalid={!!err(`landing_testimonials.${i}.text`)} />
              </div>
            )} />
        </FormSection>
      </div>
    </form>
  )
}
