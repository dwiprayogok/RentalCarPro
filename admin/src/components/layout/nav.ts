import { BarChart3, CarFront, FileText, HelpCircle, Inbox, LayoutGrid, Users, type LucideIcon } from 'lucide-react'
import { P } from '@/lib/permissions'

export interface NavItem {
  to: string
  label: string
  icon: LucideIcon
  anyOf: string[]
  end?: boolean
}

export const workspaceNav: NavItem[] = [
  { to: '/', label: 'Dashboard', icon: LayoutGrid, anyOf: [P.dashboardView], end: true },
  { to: '/transaksi', label: 'Transaksi Sewa', icon: FileText, anyOf: [P.rentalView] },
  { to: '/armada', label: 'Armada Mobil', icon: CarFront, anyOf: [P.carView] },
  { to: '/pelanggan', label: 'Pelanggan', icon: Users, anyOf: [P.customerView] },
  { to: '/permintaan-booking', label: 'Permintaan Booking', icon: Inbox, anyOf: [P.bookingRequestView] },
]

export const insightNav: NavItem[] = [
  { to: '/laporan', label: 'Laporan Pembayaran', icon: BarChart3, anyOf: [P.reportView] },
  { to: '/panduan', label: 'Panduan Penggunaan', icon: HelpCircle, anyOf: [] },
]

export const pageTitles: Record<string, string> = Object.fromEntries(
  [...workspaceNav, ...insightNav].map((n) => [n.to, n.label]),
)
