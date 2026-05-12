import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

const columns = [
  {
    title: 'To Do',
    cards: [
      { title: 'SEO blog post: remote work tools', tag: 'Blog', color: 'primary' },
      { title: 'LinkedIn thought leadership post', tag: 'LinkedIn', color: 'blue' },
    ],
  },
  {
    title: 'In Progress',
    cards: [{ title: 'Q4 newsletter: product updates', tag: 'Newsletter', color: 'primary' }],
  },
  {
    title: 'Done',
    cards: [
      { title: 'Homepage copy rewrite', tag: 'Approved', color: 'green' },
      { title: 'Press release: new launch', tag: 'Approved', color: 'green' },
    ],
  },
];

export function MockDashboard() {
  return (
    <Card className="overflow-hidden rounded-[18px] border-border bg-card shadow-[0_2px_4px_rgba(0,0,0,0.04),0_8px_32px_rgba(0,0,0,0.08)]">
      <div className="flex items-center gap-2 border-b bg-secondary px-4 py-3">
        <span className="size-2.5 rounded-full bg-[#ff5f57]" />
        <span className="size-2.5 rounded-full bg-[#febc2e]" />
        <span className="size-2.5 rounded-full bg-[#28c840]" />
        <div className="mx-2 flex-1 rounded-md border bg-card px-3 py-1 text-[11px] text-muted-foreground">
          trello.com/b/sarah-writes
        </div>
      </div>

      <div className="p-4 sm:p-[18px]">
        <div className="mb-3.5 flex items-center justify-between">
          <span className="text-[13px] font-bold">Sarah&apos;s Writing Board</span>
          <Badge className="bg-primary/10 text-[10px] tracking-[0.04em] text-primary hover:bg-primary/10">
            Pro Plan
          </Badge>
        </div>

        <div className="grid grid-cols-3 gap-2">
          {columns.map((column) => (
            <div key={column.title} className="rounded-lg bg-secondary p-2">
              <div className="mb-2 text-[10px] font-bold tracking-[0.06em] text-muted-foreground uppercase">
                {column.title}
              </div>
              <div className="space-y-1.5">
                {column.cards.map((card) => (
                  <div key={card.title} className="rounded-md border bg-card p-2">
                    <div className="mb-1 text-[11px] leading-snug font-semibold">{card.title}</div>
                    <span
                      className={cn(
                        'inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold',
                        card.color === 'primary' && 'bg-primary/10 text-primary',
                        card.color === 'green' && 'bg-emerald-50 text-emerald-700',
                        card.color === 'blue' && 'bg-indigo-50 text-indigo-800',
                      )}
                    >
                      {card.tag}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-3.5 flex gap-1.5">
          {[
            ['12', 'Completed'],
            ['3', 'In queue'],
            ['Unlimited', 'Requests left'],
          ].map(([value, label]) => (
            <div key={label} className="flex-1 rounded-lg bg-secondary px-3 py-2.5">
              <div className="font-display text-[22px] leading-none font-normal">{value}</div>
              <div className="mt-1 text-[10px] font-medium text-muted-foreground">{label}</div>
            </div>
          ))}
        </div>
      </div>
    </Card>
  );
}
