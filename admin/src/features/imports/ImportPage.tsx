import { useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Download, FileSpreadsheet, RotateCcw, Upload } from 'lucide-react'
import { useRef, useState, type DragEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useCan } from '@/auth/useAuth'
import { PageHeader } from '@/components/layout/PageHeader'
import { Badge, type Tone } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { FilterChips } from '@/components/ui/FilterChips'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { dateTimeWib, number } from '@/lib/format'
import { P } from '@/lib/permissions'
import { downloadTemplate, useCommitImport, usePreviewImport, type CommitResult, type ImportType, type Preview, type PreviewRow } from './api'

const TYPES: { value: ImportType; label: string; perm: string; link: string; hint: string }[] = [
  { value: 'armada', label: 'Armada', perm: P.carCreate, link: '/armada', hint: 'Merk, tipe, plat, transmisi, tahun, tarif, odometer, jatuh tempo pajak/STNK.' },
  { value: 'pelanggan', label: 'Pelanggan', perm: P.customerCreate, link: '/pelanggan', hint: 'Nama, HP, NIK (16 digit), data SIM & kontak darurat untuk lepas kunci.' },
  { value: 'driver', label: 'Driver', perm: P.driverCreate, link: '/', hint: 'Nama, HP, SIM, honor harian, status aktif.' },
]
const MAX_SIZE = 5 * 1024 * 1024
const STATUS: Record<PreviewRow['status'], { label: string; tone: Tone }> = {
  baru: { label: 'Siap diimpor', tone: 'green' }, lewati: { label: 'Dilewati', tone: 'gray' }, error: { label: 'Bermasalah', tone: 'red' },
}
type Filter = 'semua' | PreviewRow['status']

function Step({ n, title, children, done }: { n: number; title: string; children: React.ReactNode; done?: boolean }) {
  return (
    <section className="card p-5">
      <div className="mb-3 flex items-center gap-3">
        <span className={cn('grid size-7 place-items-center rounded-full text-xs font-bold', done ? 'bg-brand-500 text-white' : 'bg-canvas text-ink-soft ring-1 ring-line')}>{done ? '✓' : n}</span>
        <h2 className="text-base font-bold text-ink">{title}</h2>
      </div>
      {children}
    </section>
  )
}

export function ImportPage() {
  const can = useCan()
  const toast = useToast()
  const qc = useQueryClient()
  const [params, setParams] = useSearchParams()
  const allowed = TYPES.filter((t) => can(t.perm))
  const type = (allowed.find((t) => t.value === params.get('jenis')) ?? allowed[0])?.value ?? 'armada'
  const meta = TYPES.find((t) => t.value === type)!
  const preview = usePreviewImport(type)
  const commit = useCommitImport(type)
  const fileRef = useRef<HTMLInputElement>(null)
  const [dragging, setDragging] = useState(false)
  const [downloading, setDownloading] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)
  const [filter, setFilter] = useState<Filter>('semua')
  const [result, setResult] = useState<CommitResult | null>(null)
  const data: Preview | undefined = preview.data

  const reset = () => { preview.reset(); commit.reset(); setResult(null); setFilter('semua'); setLocalError(null) }
  const choose = (t: ImportType) => { reset(); setParams(t === allowed[0]?.value ? {} : { jenis: t }, { replace: true }) }
  const upload = (f: File | undefined) => {
    if (!f) return
    reset()
    if (!/\.(xlsx|xls|csv)$/i.test(f.name)) { setLocalError('File harus berformat Excel (.xlsx/.xls) atau CSV.'); return }
    if (f.size > MAX_SIZE) { setLocalError('Ukuran file maksimal 5 MB.'); return }
    preview.mutate(f, { onSuccess: (d) => setFilter(d.summary.error ? 'error' : 'semua') })
  }
  const onDrop = (e: DragEvent) => { e.preventDefault(); setDragging(false); upload(e.dataTransfer.files[0]) }

  const fileError = localError ?? (preview.error ? (fieldErrors(preview.error).file ?? errorMessage(preview.error)) : null)
  const rows = (data?.rows ?? []).filter((r) => filter === 'semua' || r.status === filter)
  const shown = rows.slice(0, 300)

  return (
    <>
      <PageHeader title="Import Data" subtitle="Pindahkan data dari Excel sekaligus. Data diperiksa per baris sebelum disimpan; data yang sudah ada tidak ditimpa." />

      {allowed.length > 1 && (
        <div className="mb-4">
          <FilterChips<ImportType> label="Jenis data" value={type} onChange={choose} options={allowed.map((t) => ({ value: t.value, label: t.label }))} />
        </div>
      )}

      <div className="space-y-4">
        <Step n={1} title={`Unduh template ${meta.label.toLowerCase()}`}>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="max-w-2xl text-sm text-ink-soft">{meta.hint} Kolom hijau tua wajib diisi; pilihan (Matic/Manual, Ya/Tidak) tersedia sebagai dropdown. Petunjuk lengkap ada di sheet <b>Petunjuk</b>.</p>
            <Button variant="secondary" loading={downloading} onClick={async () => {
              setDownloading(true)
              try { await downloadTemplate(type) } catch (e) { toast(errorMessage(e), 'error') } finally { setDownloading(false) }
            }}><Download className="size-4" /> Unduh template</Button>
          </div>
        </Step>

        <Step n={2} title="Unggah file yang sudah diisi" done={!!data}>
          <div
            onDragOver={(e) => { e.preventDefault(); setDragging(true) }} onDragLeave={() => setDragging(false)} onDrop={onDrop}
            className={cn('flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 py-8 text-center transition',
              dragging ? 'border-brand-500 bg-brand-50/60' : 'border-line bg-canvas/40')}>
            <FileSpreadsheet className="size-8 text-brand-600" aria-hidden />
            {data ? <p className="text-sm"><b>{data.file}</b> · {number(data.summary.total)} baris dibaca</p> : <p className="text-sm text-ink-soft">Tarik file ke sini, atau</p>}
            <Button variant={data ? 'secondary' : 'primary'} loading={preview.isPending} onClick={() => fileRef.current?.click()}>
              <Upload className="size-4" /> {preview.isPending ? 'Memeriksa file…' : data ? 'Ganti file' : 'Pilih file'}
            </Button>
            <p className="text-2xs text-ink-muted">.xlsx, .xls, atau .csv · maks. 5 MB · maks. 1.000 baris</p>
            <input ref={fileRef} type="file" hidden accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
              onChange={(e) => { upload(e.target.files?.[0]); e.target.value = '' }} />
          </div>
          {fileError && <div className="mt-3"><Alert>{fileError}</Alert></div>}
        </Step>

        {data && !result && (
          <Step n={3} title="Periksa & import">
            <div className="mb-4 grid gap-3 sm:grid-cols-3">
              {(['baru', 'lewati', 'error'] as const).map((s) => (
                <button key={s} type="button" onClick={() => setFilter(filter === s ? 'semua' : s)}
                  className={cn('rounded-xl px-4 py-3 text-left ring-1 transition', filter === s ? 'ring-2 ring-brand-500' : 'ring-line hover:bg-canvas',
                    s === 'baru' ? 'bg-emerald-50/60' : s === 'error' ? 'bg-red-50/60' : 'bg-white')}>
                  <p className="text-xs font-semibold text-ink-muted">{STATUS[s].label}</p>
                  <p className={cn('text-2xl font-extrabold tabular-nums', s === 'error' && data.summary.error ? 'text-red-700' : s === 'baru' ? 'text-emerald-800' : 'text-ink')}>{number(data.summary[s])}</p>
                </button>
              ))}
            </div>

            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
              <FilterChips<Filter> label="Tampilkan baris" value={filter} onChange={setFilter}
                options={[{ value: 'semua', label: 'Semua', count: data.summary.total }, { value: 'error', label: 'Bermasalah', count: data.summary.error },
                  { value: 'baru', label: 'Siap', count: data.summary.baru }, { value: 'lewati', label: 'Dilewati', count: data.summary.lewati }]} />
            </div>

            <div className="max-h-[480px] overflow-auto rounded-xl border border-line">
              <table className="w-full min-w-[620px] text-sm">
                <thead className="sticky top-0 bg-white">
                  <tr className="border-b border-line text-left text-2xs font-bold tracking-[0.1em] text-ink-muted uppercase">
                    <th className="w-16 px-3 py-2">Baris</th><th className="px-3 py-2">Data</th><th className="w-32 px-3 py-2">Status</th><th className="px-3 py-2">Keterangan</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {shown.map((r) => (
                    <tr key={r.row} className={cn(r.status === 'error' && 'bg-red-50/40')}>
                      <td className="px-3 py-2 text-xs text-ink-muted tabular-nums">{r.row}</td>
                      <td className="px-3 py-2 font-medium">{r.summary || <span className="text-ink-muted">—</span>}</td>
                      <td className="px-3 py-2"><Badge tone={STATUS[r.status].tone}>{STATUS[r.status].label}</Badge></td>
                      <td className="px-3 py-2 text-xs">
                        {r.errors.length ? (
                          <ul className="space-y-0.5 text-red-700">{r.errors.map((e, i) => <li key={i}>{e.field && <b>{e.field}: </b>}{e.message}</li>)}</ul>
                        ) : <span className="text-ink-soft">{r.message ?? 'OK'}</span>}
                      </td>
                    </tr>
                  ))}
                  {!shown.length && <tr><td colSpan={4} className="px-3 py-6 text-center text-ink-muted">Tidak ada baris dengan status ini.</td></tr>}
                </tbody>
              </table>
            </div>
            {rows.length > shown.length && <p className="mt-1 text-2xs text-ink-muted">Menampilkan 300 dari {number(rows.length)} baris.</p>}

            <div className="mt-4 flex flex-col gap-3 rounded-xl bg-canvas p-4 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-sm text-ink-soft">
                {data.summary.baru
                  ? <>Hanya <b>{number(data.summary.baru)} baris siap</b> yang akan disimpan.{data.summary.error > 0 && ' Perbaiki baris bermasalah di Excel lalu unggah ulang bila perlu.'} Pratinjau berlaku sampai {dateTimeWib(data.expires_at)}.</>
                  : 'Tidak ada baris yang bisa diimpor. Perbaiki file lalu unggah ulang.'}
              </p>
              <div className="flex gap-2">
                <Button variant="ghost" onClick={reset}><RotateCcw className="size-4" /> Batal</Button>
                <Button disabled={!data.token || !data.summary.baru} loading={commit.isPending}
                  onClick={() => data.token && commit.mutate(data.token, {
                    onSuccess: (r) => { setResult(r.data); toast(r.message); qc.invalidateQueries() },
                    onError: (e) => toast(fieldErrors(e).token ?? errorMessage(e), 'error'),
                  })}>Import {number(data.summary.baru)} {meta.label.toLowerCase()}</Button>
              </div>
            </div>
          </Step>
        )}

        {result && (
          <section className="card flex flex-col items-center gap-3 p-8 text-center" role="status">
            <CheckCircle2 className="size-10 text-brand-600" aria-hidden />
            <p className="text-lg font-extrabold">{number(result.created)} {meta.label.toLowerCase()} berhasil diimpor</p>
            {result.skipped.length > 0 && (
              <div className="w-full max-w-xl rounded-xl bg-amber-50 p-3 text-left text-xs text-amber-900 ring-1 ring-amber-200">
                <p className="mb-1 font-semibold">{result.skipped.length} baris dilewati saat menyimpan (data berubah sejak pratinjau):</p>
                <ul className="space-y-0.5">{result.skipped.slice(0, 20).map((s) => <li key={s.row}>Baris {s.row}: {s.message}</li>)}</ul>
              </div>
            )}
            <div className="flex flex-wrap justify-center gap-2">
              {type !== 'driver' && <Link to={meta.link} className="inline-flex h-10 items-center rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">Lihat {meta.label}</Link>}
              <Button variant="secondary" onClick={reset}>Import file lain</Button>
            </div>
          </section>
        )}
      </div>
    </>
  )
}
