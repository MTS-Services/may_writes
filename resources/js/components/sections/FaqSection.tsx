import { useState } from 'react';
import { Plus } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { faqs } from '@/components/sections/home-data';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export function FaqSection() {
  const [open, setOpen] = useState<number | null>(null);

  return (
    <section id="faq" className="bg-secondary px-5 py-24 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <SectionHeading
          align="center"
          eyebrow="FAQ"
          title={
            <>
              Questions <em className="text-primary">answered.</em>
            </>
          }
          description="Everything you need to know before getting started."
        />
        <div className="mx-auto max-w-[700px] border-y">
          {faqs.map((item, index) => {
            const isOpen = open === index;

            return (
              <div key={item.q} className="border-b last:border-b-0">
                <button
                  className="flex w-full items-center justify-between gap-4 py-5 text-left"
                  onClick={() => setOpen(isOpen ? null : index)}
                  type="button"
                >
                  <span className="text-[15px] leading-6 font-semibold transition-colors hover:text-primary">
                    {item.q}
                  </span>
                  <Button
                    aria-hidden="true"
                    className={cn(
                      'pointer-events-none size-6 shrink-0 rounded-full p-0 transition-transform',
                      isOpen && 'rotate-45',
                    )}
                    size="icon"
                    tabIndex={-1}
                    variant={isOpen ? 'default' : 'outline'}
                  >
                    <Plus className="size-4" />
                  </Button>
                </button>
                {isOpen && (
                  <p className="pr-10 pb-5 text-sm leading-7 text-muted-foreground">{item.a}</p>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
