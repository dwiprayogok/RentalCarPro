'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { useEffect, useState } from 'react'
import { Logo } from './Logo'
import { IconClose, IconMenu, IconWhatsApp } from './Icons'

const NAV = [
  { href: '/armada', label: 'Armada' },
  { href: '/#cara-sewa', label: 'Cara Sewa' },
  { href: '/syarat-ketentuan', label: 'Syarat' },
  { href: '/#faq', label: 'FAQ' },
  { href: '/#kontak', label: 'Kontak' },
]

export function Header({ name, whatsappUrl }: { name: string; whatsappUrl: string | null }) {
  const [open, setOpen] = useState(false)
  const pathname = usePathname()
  useEffect(() => setOpen(false), [pathname])
  useEffect(() => {
    if (!open) return
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  return (
    <header className="sticky top-0 z-40 border-b border-line/70 bg-white/85 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
        <Link href="/" aria-label={`${name} — beranda`}><Logo name={name} /></Link>
        <nav aria-label="Navigasi utama" className="hidden items-center gap-1 md:flex">
          {NAV.map((n) => (
            <Link key={n.href} href={n.href}
              className={`rounded-full px-3.5 py-2 text-sm font-medium transition-colors hover:bg-canvas ${pathname === n.href ? 'text-brand' : 'text-ink/80'}`}>
              {n.label}
            </Link>
          ))}
        </nav>
        <div className="flex items-center gap-2">
          {whatsappUrl && (
            <a href={whatsappUrl} target="_blank" rel="noopener" className="btn-primary hidden sm:inline-flex">
              <IconWhatsApp className="size-4" /> Chat WhatsApp
            </a>
          )}
          <button type="button" onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-controls="menu-mobile"
            className="grid size-11 place-items-center rounded-full text-ink hover:bg-canvas md:hidden" aria-label={open ? 'Tutup menu' : 'Buka menu'}>
            {open ? <IconClose /> : <IconMenu />}
          </button>
        </div>
      </div>
      {open && (
        <nav id="menu-mobile" aria-label="Navigasi utama" className="border-t border-line bg-white px-4 pb-4 md:hidden">
          <ul className="divide-y divide-line">
            {NAV.map((n) => (
              <li key={n.href}>
                <Link href={n.href} onClick={() => setOpen(false)} className="block py-3.5 text-base font-medium">{n.label}</Link>
              </li>
            ))}
          </ul>
          {whatsappUrl && (
            <a href={whatsappUrl} target="_blank" rel="noopener" className="btn-primary mt-3 w-full">
              <IconWhatsApp className="size-4" /> Chat WhatsApp
            </a>
          )}
        </nav>
      )}
    </header>
  )
}
