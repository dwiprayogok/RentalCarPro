import { useEffect, useState } from 'react'
import { Button } from '@/components/ui/Button'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Field, Input, Select, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { EmptyState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { todayWib } from '@/lib/datetime'
import { dateOnly } from '@/lib/format'
import { periodPreset, useCreateSettlement, useOwners, useSettlementPreview, type Settlement } from './api'
import { SettlementTable } from './SettlementTable'

export function NewSettlementModal({ open, ownerId, onClose, onCreated }: { open: boolean; ownerId: number | null; onClose: () => void; onCreated: (s: Settlement) => void }) {
  const toast = useToast()
  const owners = useOwners('', 'aktif')
  const create = useCreateSettlement()
  const [owner, setOwner] = useState<number | null>(ownerId)
  const [range, setRange] = useState<'bulan_lalu' | 'bulan_ini' | 'kustom'>('bulan_lalu')
  const [period, setPeriod] = useState(() => periodPreset('bulan_lalu', todayWib()))
  const [notes, setNotes] = useState('')
  const preview = useSettlementPreview(open ? owner : null, period.from, period.to)

  useEffect(() => {
    if (!open) return
    setOwner(ownerId); setRange('bulan_lalu'); setPeriod(periodPreset('bulan_lalu', todayWib())); setNotes(''); create.reset()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, ownerId])

  const errs = create.error ? fieldErrors(create.error) : {}
  const general = create.error && !Object.keys(errs).length ? errorMessage(create.error) : (errs.period ?? errs.owner_id ?? errs.from ?? errs.to)
  const ownerList = (owners.data?.data ?? []).filter((o) => (o.cars_count ?? 0) > 0)
  const selected = ownerList.find((o) => o.id === owner)

  return (
    <Modal open={open} onClose={onClose} dismissible={!create.isPending} size="lg" title="Buat setoran bagi hasil"
      description="Angka disimpan sebagai snapshot. Perubahan data sesudahnya tidak mengubah setoran ini."
      footer={<><Button variant="secondary" onClick={onClose} disabled={create.isPending}>Batal</Button>
        <Button loading={create.isPending} disabled={!owner || !preview.data || period.from > period.to}
          onClick={() => owner && create.mutate({ owner_id: owner, from: period.from, to: period.to, notes: notes.trim() || null }, {
            onSuccess: (r) => { toast(r.message); onCreated(r.data); onClose() },
          })}>Simpan setoran</Button></>}>
      <div className="space-y-4">
        {general && <Alert>{general}</Alert>}
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Field label="Pemilik" className="sm:col-span-2 lg:col-span-4">
            {(a) => (
              <Select {...a} value={owner ?? ''} onChange={(e) => setOwner(e.target.value ? Number(e.target.value) : null)}>
                <option value="">Pilih pemilik…</option>
                {ownerList.map((o) => <option key={o.id} value={o.id}>{o.name} · {o.cars_count} mobil · {o.share_label}</option>)}
              </Select>
            )}
          </Field>
          <Field label="Periode">
            {(a) => (
              <Select {...a} value={range} onChange={(e) => {
                const v = e.target.value as typeof range
                setRange(v)
                if (v !== 'kustom') setPeriod(periodPreset(v, todayWib()))
              }}>
                <option value="bulan_lalu">Bulan lalu</option>
                <option value="bulan_ini">Bulan ini s/d hari ini</option>
                <option value="kustom">Kustom</option>
              </Select>
            )}
          </Field>
          <Field label="Dari">{(a) => <Input {...a} type="date" value={period.from} max={period.to} onChange={(e) => { setRange('kustom'); setPeriod((p) => ({ ...p, from: e.target.value })) }} />}</Field>
          <Field label="Sampai">{(a) => <Input {...a} type="date" value={period.to} min={period.from} max={todayWib()} onChange={(e) => { setRange('kustom'); setPeriod((p) => ({ ...p, to: e.target.value })) }} />}</Field>
          <div className="flex items-end pb-2 text-xs text-ink-muted">{selected?.last_period_to && <>Setoran terakhir s/d {dateOnly(selected.last_period_to)}</>}</div>
        </div>

        {!owner ? (
          <EmptyState title="Pilih pemilik" description={ownerList.length ? 'Pratinjau bagi hasil per mobil akan tampil di sini.' : 'Belum ada pemilik aktif yang punya mobil titipan.'} />
        ) : preview.isLoading ? <Skeleton className="h-56" /> : preview.isError ? <Alert>{errorMessage(preview.error)}</Alert> : preview.data && (
          <div className={cn(preview.isPlaceholderData && 'opacity-60')}>
            <p className="mb-2 text-xs text-ink-muted">Periode {dateOnly(preview.data.period_from)} – {dateOnly(preview.data.period_to)} · pendapatan per tanggal bayar (WIB)</p>
            <SettlementTable lines={preview.data.lines} t={preview.data} />
          </div>
        )}
        <Field label="Catatan (opsional)">{(a) => <Textarea {...a} rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={500} />}</Field>
      </div>
    </Modal>
  )
}
