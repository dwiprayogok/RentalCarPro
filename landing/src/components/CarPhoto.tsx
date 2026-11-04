/** Foto mobil; bila belum ada foto, tampilkan ilustrasi netral agar kartu tetap rapi. */
export function CarPhoto({ src, alt, className = '', eager = false }: { src: string | null; alt: string; className?: string; eager?: boolean }) {
  if (src) {
    return <img src={src} alt={alt} loading={eager ? 'eager' : 'lazy'} decoding="async" className={`h-full w-full object-cover ${className}`} />
  }
  return (
    <div role="img" aria-label={`${alt} (foto belum tersedia)`}
      className={`grid h-full w-full place-items-center bg-[radial-gradient(120%_90%_at_30%_10%,#e7f4ef_0%,#dfe9e6_45%,#cfdcd8_100%)] ${className}`}>
      <svg viewBox="0 0 120 60" className="w-2/3 max-w-56 text-ink/25" fill="none" stroke="currentColor" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M10 42V33l8-4 10-12a6 6 0 0 1 4.6-2.2h34a6 6 0 0 1 4.3 1.8L84 29l18 3.5a6 6 0 0 1 4.9 5.9V42" />
        <path d="M6 42h108" /><circle cx="32" cy="43" r="7" fill="#f4f7f6" /><circle cx="90" cy="43" r="7" fill="#f4f7f6" /><path d="M30 29h52" />
      </svg>
    </div>
  )
}
