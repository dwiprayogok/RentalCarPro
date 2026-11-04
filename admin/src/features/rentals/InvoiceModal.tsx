import { MessageCircle, Printer } from 'lucide-react'
import { useState } from 'react'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Feedback'
import { Modal } from '@/components/ui/Modal'
import { ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage } from '@/lib/api'
import { number, rupiah } from '@/lib/format'
import { openInvoicePdf, useInvoice } from './api'
import { PaymentBadge } from './badges'

function L({ children }: { children: React.ReactNode }) {
  return <p className="text-2xs font-bold tracking-[0.12em] text-ink-muted uppercase">{children}</p>
}

function Row({ label, value, strong, detail }: { label: string; value: string; strong?: boolean; detail?: string | null }) {
  return (
    <div className="flex items-start justify-between gap-4 py-1">
      <div className={strong ? 'text-sm font-bold text-ink' : 'text-xs text-ink-muted'}>
        {label}{detail && <p className="text-2xs font-normal text-ink-muted">{detail}</p>}
      </div>
      <span className={strong ? 'text-base font-extrabold text-ink tabular-nums' : 'text-xs font-medium text-ink tabular-nums'}>{value}</span>
    </div>
  )
}

/** Nota transaksi: tampilan mengikuti prototipe, PDF dibuat di server. */
export function InvoiceModal({ rentalId, onClose }: { rentalId: number | null; onClose: () => void }) {
  const { data: n, isLoading, isError, error, refetch } = useInvoice(rentalId)
  const toast = useToast()
  const [printing, setPrinting] = useState(false)

  return (
    <Modal
      open={rentalId !== null}
      onClose={onClose}
      size="md"
      title="Nota transaksi"
      description="Cetak nota atau kirim lewat WhatsApp."
      footer={n && (
        <>
          <Button variant="secondary" loading={printing} onClick={async () => {
            setPrinting(true)
            try { await openInvoicePdf(rentalId!) } catch (e) { toast(errorMessage(e), 'error') } finally { setPrinting(false) }
          }}><Printer className="size-4" /> Cetak / PDF</Button>
          <a href={n.whatsapp_url} target="_blank" rel="noreferrer" className="inline-flex h-10 items-center gap-2 rounded-[10px] bg-brand-500 px-4 text-sm font-semibold text-white shadow-sm hover:bg-brand-600">
            <MessageCircle className="size-4" /> WhatsApp
          </a>
        </>
      )}
    >
      {isLoading && <div className="space-y-3"><Skeleton className="h-8 w-1/2" /><Skeleton className="h-64 w-full" /></div>}
      {isError && <ErrorState error={error} onRetry={() => refetch()} />}
      {n && (
        <div className="space-y-4">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p className="text-xl font-extrabold text-brand-600">{n.business.name}</p>
              <p className="text-xs text-ink-muted">{n.business.tagline}</p>
              {n.business.address && <p className="text-2xs text-ink-muted">{n.business.address}</p>}
            </div>
            <div className="text-right">
              <L>Nota sewa</L>
              <p className="text-sm font-bold text-ink">{n.code}</p>
              <div className="mt-1 flex justify-end gap-1.5">
                <Badge tone={n.status === 'terlambat' ? 'red' : n.status === 'selesai' ? 'green' : n.status === 'batal' ? 'gray' : 'teal'}>{n.status_label}</Badge>
                <PaymentBadge status={n.payment_status} label={n.payment_status_label} />
              </div>
            </div>
          </div>

          <div className="grid gap-4 border-y border-line py-4 sm:grid-cols-2">
            <div>
              <L>Pelanggan</L>
              <p className="mt-1 font-bold text-ink">{n.customer.name}</p>
              <p className="text-xs text-ink-soft">{n.customer.phone}</p>
              <p className="text-2xs text-ink-muted">KTP {n.customer.nik_masked}</p>
            </div>
            <div>
              <L>Kendaraan</L>
              <p className="mt-1 font-bold text-ink">{n.car.name}</p>
              <p className="text-xs text-ink-soft">{n.car.plate_number} · {n.service_type_label}</p>
              {n.driver && <p className="text-2xs text-ink-muted">Driver: {n.driver.name}</p>}
            </div>
          </div>

          <div>
            <Row label="Mulai (WIB)" value={n.schedule.start_at} />
            <Row label="Kembali rencana (WIB)" value={n.schedule.planned_end_at} />
            {n.schedule.returned_at && <Row label="Kembali aktual (WIB)" value={n.schedule.returned_at} />}
            {n.lines.map((l, i) => <Row key={i} label={l.label} detail={l.detail} value={rupiah(l.amount).replace('Rp -', '− Rp ')} />)}
          </div>

          <div className="border-t border-dashed border-line-strong pt-2">
            <Row strong label="Total tagihan" value={rupiah(n.totals.total)} />
            <Row label="Pembayaran diterima" value={rupiah(n.totals.paid)} />
            <Row strong label="Sisa tagihan" value={rupiah(n.totals.balance)} />
            {n.totals.running_late_fee > 0 && <p className="mt-1 text-right text-xs font-semibold text-red-700">+ estimasi denda berjalan {rupiah(n.totals.running_late_fee)}</p>}
          </div>

          {(n.deposit.required > 0 || n.deposit.received > 0) && (
            <div className="rounded-xl bg-canvas px-4 py-2">
              <L>Deposit</L>
              <Row label="Diterima" value={rupiah(n.deposit.received)} />
              {n.deposit.deducted > 0 && <Row label="Dipotong untuk tagihan" value={`− ${rupiah(n.deposit.deducted)}`} />}
              {n.deposit.refunded > 0 && <Row label="Dikembalikan" value={`− ${rupiah(n.deposit.refunded)}`} />}
              <Row label="Masih ditahan" value={rupiah(n.deposit.held)} />
            </div>
          )}

          {n.payments.length > 0 && (
            <div>
              <L>Riwayat pembayaran</L>
              {n.payments.map((p, i) => (
                <Row key={i} label={`${p.paid_at} WIB · ${p.type_label} · ${p.method_label}${p.reference_number ? ` · ${p.reference_number}` : ''}`} value={`${p.direction === 'out' ? '− ' : ''}${rupiah(p.amount)}`} />
              ))}
            </div>
          )}

          {(n.inspections.serah_terima || n.inspections.kembali) && (
            <div className="grid gap-3 sm:grid-cols-2">
              {(['serah_terima', 'kembali'] as const).map((k) => {
                const i = n.inspections[k]
                return (
                  <div key={k} className="rounded-xl border border-line px-3 py-2 text-xs">
                    <L>{k === 'serah_terima' ? 'Serah terima' : 'Kembali'}</L>
                    {i ? <><p className="mt-1">Odometer {number(i.odometer)} km · BBM {i.fuel_label}</p>{i.issues.map((x) => <p key={x} className="text-amber-800">• {x}</p>)}</> : <p className="mt-1 text-ink-muted">-</p>}
                  </div>
                )
              })}
            </div>
          )}

          {n.note && <p className="rounded-xl bg-canvas px-4 py-3 text-xs text-ink-soft">{n.note}</p>}

          {n.terms.length > 0 && (
            <div>
              <L>Syarat singkat</L>
              <ol className="mt-1 list-decimal space-y-0.5 pl-4 text-2xs text-ink-muted">{n.terms.map((t) => <li key={t}>{t}</li>)}</ol>
            </div>
          )}

          <div className="grid grid-cols-2 gap-8 pt-8 text-center text-xs">
            <div className="border-t border-ink pt-1">Penyewa<p className="text-ink-muted">{n.customer.name}</p></div>
            <div className="border-t border-ink pt-1">Petugas<p className="text-ink-muted">{n.business.name}</p></div>
          </div>

          <p className="text-center text-xs text-ink-muted">{n.footer}</p>
        </div>
      )}
    </Modal>
  )
}
