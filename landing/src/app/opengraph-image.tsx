import { ImageResponse } from 'next/og'
import { getCars, getSiteSettings } from '@/lib/api'

export const alt = 'Sewa mobil'
export const size = { width: 1200, height: 630 }
export const contentType = 'image/png'

export default async function OgImage() {
  const [s, cars] = await Promise.all([getSiteSettings(), getCars()])
  const min = cars.length ? Math.min(...cars.map((c) => c.rate_self_drive)) : null
  return new ImageResponse(
    (
      <div style={{ width: '100%', height: '100%', display: 'flex', flexDirection: 'column', justifyContent: 'space-between', padding: 72, color: 'white',
        background: 'radial-gradient(circle at 85% 0%, #0F7B63 0%, #14262D 55%)' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 18 }}>
          <div style={{ width: 64, height: 64, borderRadius: 16, background: '#0F7B63', border: '3px solid rgba(255,255,255,.25)' }} />
          <div style={{ fontSize: 40, fontWeight: 800 }}>{s.business.name}</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <div style={{ fontSize: 66, fontWeight: 800, lineHeight: 1.08, maxWidth: 980 }}>{s.hero.title}</div>
          <div style={{ fontSize: 30, color: '#7FD8BD' }}>
            {[s.business.city && `Rental mobil ${s.business.city}`, min && `mulai Rp ${new Intl.NumberFormat('id-ID').format(min)}/hari`].filter(Boolean).join(' · ') || 'Lepas kunci & dengan driver'}
          </div>
        </div>
      </div>
    ),
    size,
  )
}
