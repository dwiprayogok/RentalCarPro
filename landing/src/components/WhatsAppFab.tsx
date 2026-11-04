import { IconWhatsApp } from './Icons'

/** Tombol WhatsApp melayang khusus layar kecil (di desktop sudah ada di header). */
export function WhatsAppFab({ url }: { url: string | null }) {
  if (!url) return null
  return (
    <a href={url} target="_blank" rel="noopener" aria-label="Chat via WhatsApp"
      className="fixed right-4 bottom-4 z-30 grid size-14 place-items-center rounded-full bg-[#1FAF5A] text-white shadow-lg shadow-black/20 transition-transform hover:scale-105 sm:hidden"
      style={{ marginBottom: 'env(safe-area-inset-bottom)' }}>
      <IconWhatsApp className="size-7" />
    </a>
  )
}
