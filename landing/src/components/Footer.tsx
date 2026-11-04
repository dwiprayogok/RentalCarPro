import Link from 'next/link'
import type { SiteSettings } from '@/lib/types'
import { Logo } from './Logo'

export function Footer({ settings: s }: { settings: SiteSettings }) {
  const year = new Date().getFullYear()
  return (
    <footer className="bg-ink text-white/75">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr]">
        <div>
          <Logo name={s.business.name} dark />
          <p className="mt-4 max-w-sm text-sm leading-relaxed">
            Sewa mobil lepas kunci & dengan driver{s.business.city ? ` di ${s.business.city}` : ''}. Armada terawat, tarif jelas di depan.
          </p>
        </div>
        <div>
          <h2 className="text-sm font-semibold text-white">Jelajahi</h2>
          <ul className="mt-3 space-y-2 text-sm">
            <li><Link className="hover:text-white" href="/armada">Armada</Link></li>
            <li><Link className="hover:text-white" href="/#cara-sewa">Cara sewa</Link></li>
            <li><Link className="hover:text-white" href="/syarat-ketentuan">Syarat & ketentuan</Link></li>
            <li><Link className="hover:text-white" href="/#faq">FAQ</Link></li>
          </ul>
        </div>
        <div>
          <h2 className="text-sm font-semibold text-white">Kontak</h2>
          <ul className="mt-3 space-y-2 text-sm">
            {s.business.address && <li>{s.business.address}</li>}
            {s.business.operating_hours && <li>{s.business.operating_hours}</li>}
            {s.business.whatsapp_url && (
              <li><a className="hover:text-white" href={s.business.whatsapp_url} target="_blank" rel="noopener">WhatsApp {s.business.whatsapp}</a></li>
            )}
          </ul>
        </div>
      </div>
      <div className="border-t border-white/10">
        <p className="mx-auto max-w-6xl px-4 py-5 text-2xs text-white/55 sm:px-6">© {year} {s.business.name}. Semua hak dilindungi.</p>
      </div>
    </footer>
  )
}
