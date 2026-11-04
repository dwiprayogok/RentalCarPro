import { CarFront } from 'lucide-react'

export function Logo({ inverted = true }: { inverted?: boolean }) {
  return (
    <div className="flex items-center gap-3">
      <span className="grid size-10 place-items-center rounded-xl bg-brand-300 text-sidebar">
        <CarFront className="size-5" strokeWidth={2.2} />
      </span>
      <span className="leading-tight">
        <span className={inverted ? 'block text-lg font-extrabold text-white' : 'block text-lg font-extrabold text-ink'}>RentalPro</span>
        <span className={inverted ? 'block text-2xs font-semibold tracking-[0.16em] text-sidebar-muted uppercase' : 'block text-2xs font-semibold tracking-[0.16em] text-ink-muted uppercase'}>
          Rental Workspace
        </span>
      </span>
    </div>
  )
}
