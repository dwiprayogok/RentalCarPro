import { Badge, type Tone } from '@/components/ui/Badge'
import type { PaymentStatus, Rental } from './types'

const statusTone: Record<Rental['display_status'], Tone> = {
  booking: 'blue', aktif: 'teal', terlambat: 'red', selesai: 'green', batal: 'gray',
}

const paymentTone: Record<PaymentStatus, Tone> = {
  belum_bayar: 'red', belum_lunas: 'yellow', lunas: 'green', lunas_belum_denda: 'yellow', batal: 'gray',
}

export function RentalStatusBadge({ rental }: { rental: Pick<Rental, 'display_status' | 'display_status_label'> }) {
  return <Badge tone={statusTone[rental.display_status]}>{rental.display_status_label}</Badge>
}

export function PaymentBadge({ status, label }: { status: PaymentStatus; label: string }) {
  return <Badge tone={paymentTone[status]}>{label}</Badge>
}
