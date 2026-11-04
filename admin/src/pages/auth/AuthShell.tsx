import type { ReactNode } from 'react'
import { CalendarCheck, ShieldCheck, Wallet } from 'lucide-react'
import { Logo } from '@/components/layout/Logo'

/** Kerangka halaman auth: panel merek di kiri (desktop), form di kanan. */
export function AuthShell({ title, subtitle, children }: { title: string; subtitle: string; children: ReactNode }) {
  return (
    <div className="grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
      <aside className="relative hidden overflow-hidden bg-sidebar p-12 text-white lg:flex lg:flex-col">
        <Logo />
        <div className="mt-auto max-w-md">
          <p className="eyebrow text-brand-300">Manajemen Rental Mobil</p>
          <h2 className="mt-3 text-3xl leading-tight font-extrabold">Sewa, armada, dan pembayaran dalam satu tempat.</h2>
          <ul className="mt-8 space-y-4 text-sm text-sidebar-muted">
            <li className="flex gap-3"><CalendarCheck className="size-5 shrink-0 text-brand-300" /> Jadwal anti bentrok dan pengingat jatuh tempo.</li>
            <li className="flex gap-3"><Wallet className="size-5 shrink-0 text-brand-300" /> Tagihan, denda, dan deposit tercatat rapi.</li>
            <li className="flex gap-3"><ShieldCheck className="size-5 shrink-0 text-brand-300" /> Data KTP pelanggan terenkripsi dan aman.</li>
          </ul>
        </div>
        <div className="pointer-events-none absolute -right-24 -bottom-24 size-96 rounded-full bg-brand-500/20 blur-3xl" aria-hidden />
      </aside>

      <main className="flex items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-sm">
          <div className="mb-8 lg:hidden"><Logo inverted={false} /></div>
          <h1 className="text-2xl font-extrabold tracking-tight text-ink">{title}</h1>
          <p className="mt-1.5 text-sm text-ink-muted">{subtitle}</p>
          <div className="mt-8">{children}</div>
        </div>
      </main>
    </div>
  )
}
