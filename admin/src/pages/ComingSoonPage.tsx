import { Hourglass } from 'lucide-react'
import { PageHeader } from '@/components/layout/PageHeader'
import { EmptyState } from '@/components/ui/States'

export function ComingSoonPage({ title, subtitle, note }: { title: string; subtitle: string; note: string }) {
  return (
    <>
      <PageHeader title={title} subtitle={subtitle} />
      <div className="card">
        <EmptyState icon={<Hourglass className="size-7" />} title="Segera hadir" description={note} />
      </div>
    </>
  )
}
