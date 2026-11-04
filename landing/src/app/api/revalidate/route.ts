import { revalidateTag } from 'next/cache'
import { NextResponse, type NextRequest } from 'next/server'
import { timingSafeEqual } from 'node:crypto'
import { PUBLIC_TAG } from '@/lib/api'

/**
 * Webhook dari Laravel (job RevalidateLanding) saat data publik berubah.
 * Header `X-Revalidate-Secret` wajib sama dengan REVALIDATE_SECRET. Body: { tags: ['public'], reason }.
 */
export async function POST(req: NextRequest) {
  const secret = process.env.REVALIDATE_SECRET
  if (!secret) return NextResponse.json({ message: 'Revalidate belum dikonfigurasi.' }, { status: 503 })

  const given = Buffer.from(req.headers.get('x-revalidate-secret') ?? '')
  const expected = Buffer.from(secret)
  if (given.length !== expected.length || !timingSafeEqual(given, expected)) {
    return NextResponse.json({ message: 'Tidak diizinkan.' }, { status: 401 })
  }

  const body = (await req.json().catch(() => ({}))) as { tags?: unknown; reason?: unknown }
  // Hanya tag yang dikenal; tag lain diabaikan.
  const tags = (Array.isArray(body.tags) ? body.tags : [PUBLIC_TAG]).filter((t): t is string => t === PUBLIC_TAG)
  if (!tags.length) tags.push(PUBLIC_TAG)
  tags.forEach((t) => revalidateTag(t))

  return NextResponse.json({ revalidated: true, tags, reason: typeof body.reason === 'string' ? body.reason.slice(0, 40) : null, at: new Date().toISOString() })
}
