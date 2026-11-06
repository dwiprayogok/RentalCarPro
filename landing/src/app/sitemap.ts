import type { MetadataRoute } from 'next'
import { getCars } from '@/lib/api'
import { SITE_URL } from '@/lib/site'

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const cars = await getCars()
  const now = new Date()
  return [
    { url: `${SITE_URL}/`, lastModified: now, changeFrequency: 'weekly', priority: 1 },
    { url: `${SITE_URL}/armada`, lastModified: now, changeFrequency: 'daily', priority: 0.9 },
    { url: `${SITE_URL}/syarat-ketentuan`, lastModified: now, changeFrequency: 'monthly', priority: 0.4 },
    ...cars.map((c) => ({
      url: `${SITE_URL}/armada/${c.slug}`,
      lastModified: now,
      changeFrequency: 'daily' as const,
      priority: 0.8,
      images: c.cover_photo_url ? [c.cover_photo_url] : undefined,
    })),
  ]
}
