import { ArrowRight } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { portfolio } from '@/components/sections/home-data';
import { Card, CardContent, CardFooter } from '@/components/ui/card';

export function PortfolioSection() {
  return (
    <section id="portfolio" className="bg-secondary px-5 py-24 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <SectionHeading
          eyebrow="Sample work"
          title={
            <>
              Content that <em className="text-primary">moves</em> people.
            </>
          }
          description="A look at the kind of work we produce every day, across every format and industry."
        />
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {portfolio.map((item) => (
            <Card
              key={item.title}
              className="overflow-hidden rounded-xl bg-card shadow-none transition-all hover:-translate-y-1 hover:border-border/80 hover:shadow-[0_8px_28px_rgba(0,0,0,0.08)]"
            >
              <CardContent className="min-h-[145px] border-b p-6">
                <div className="mb-2.5 text-[10px] font-extrabold tracking-[0.1em] text-primary uppercase">
                  {item.type}
                </div>
                <h3 className="mb-2 font-display text-[17px] leading-snug font-normal">
                  {item.title}
                </h3>
                <p className="text-[12.5px] leading-5 text-muted-foreground">{item.excerpt}</p>
              </CardContent>
              <CardFooter className="justify-between p-4 py-3">
                <span className="text-xs text-muted-foreground">Sample excerpt</span>
                <span className="inline-flex items-center gap-1 text-xs font-semibold text-primary">
                  Read more <ArrowRight className="size-3" aria-hidden="true" />
                </span>
              </CardFooter>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}
