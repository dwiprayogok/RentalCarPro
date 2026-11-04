'use client'

import { useState } from 'react'
import { CarPhoto } from '@/components/CarPhoto'

export function Gallery({ photos, name }: { photos: { url: string; caption: string | null }[]; name: string }) {
  const [i, setI] = useState(0)
  const current = photos[i]
  return (
    <div>
      <div className="relative aspect-[16/10] overflow-hidden rounded-[var(--radius-card)] bg-white ring-1 ring-line/70">
        <CarPhoto src={current?.url ?? null} alt={current?.caption || name} eager />
        {current?.caption && <p className="absolute bottom-3 left-3 rounded-full bg-ink/75 px-3 py-1 text-2xs text-white">{current.caption}</p>}
      </div>
      {photos.length > 1 && (
        <ul className="mt-3 flex gap-2 overflow-x-auto pb-1" aria-label="Galeri foto">
          {photos.map((p, n) => (
            <li key={p.url} className="shrink-0">
              <button type="button" onClick={() => setI(n)} aria-label={`Foto ${n + 1}${p.caption ? `: ${p.caption}` : ''}`} aria-current={n === i}
                className={`block h-16 w-24 overflow-hidden rounded-xl ring-2 transition ${n === i ? 'ring-brand' : 'ring-transparent opacity-75 hover:opacity-100'}`}>
                <img src={p.url} alt="" loading="lazy" className="h-full w-full object-cover" />
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
