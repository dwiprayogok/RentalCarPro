import type { Metadata, Viewport } from 'next'
import './globals.css'
import { getSiteSettings } from '@/lib/api'
import { SITE_URL } from '@/lib/site'
import { Header } from '@/components/Header'
import { Footer } from '@/components/Footer'
import { WhatsAppFab } from '@/components/WhatsAppFab'

export async function generateMetadata(): Promise<Metadata> {
  const s = await getSiteSettings()
  const name = s.business.name
  const city = s.business.city ? ` di ${s.business.city}` : ''
  const description = s.hero.subtitle || `Sewa mobil lepas kunci dan dengan driver${city}. Tarif transparan, cek ketersediaan online.`
  return {
    metadataBase: new URL(SITE_URL),
    title: { default: `${name} — Sewa Mobil${city}`, template: `%s · ${name}` },
    description,
    applicationName: name,
    alternates: { canonical: '/' },
    openGraph: { type: 'website', locale: 'id_ID', siteName: name, title: `${name} — Sewa Mobil${city}`, description, url: '/' },
    twitter: { card: 'summary_large_image', title: `${name} — Sewa Mobil${city}`, description },
    robots: { index: true, follow: true },
    formatDetection: { telephone: false },
  }
}

export const viewport: Viewport = { themeColor: '#14262D', width: 'device-width', initialScale: 1 }

export default async function RootLayout({ children }: { children: React.ReactNode }) {
  const s = await getSiteSettings()
  return (
    <html lang="id">
      <body className="min-h-dvh font-sans antialiased">
        <a href="#konten" className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-white focus:px-4 focus:py-2 focus:text-sm">
          Lewati ke konten
        </a>
        <Header name={s.business.name} whatsappUrl={s.business.whatsapp_url} />
        <main id="konten">{children}</main>
        <Footer settings={s} />
        <WhatsAppFab url={s.business.whatsapp_url} />
      </body>
    </html>
  )
}
