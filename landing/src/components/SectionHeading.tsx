export function SectionHeading({ eyebrow, title, intro, id }: { eyebrow: string; title: string; intro?: string; id?: string }) {
  return (
    <div className="max-w-2xl">
      <p className="eyebrow">{eyebrow}</p>
      <h2 id={id} className="mt-2 text-[28px] leading-tight font-extrabold tracking-tight text-balance sm:text-4xl">{title}</h2>
      {intro && <p className="mt-3 text-base leading-relaxed text-muted">{intro}</p>}
    </div>
  )
}
