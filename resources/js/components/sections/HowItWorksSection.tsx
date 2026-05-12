import { Check } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { steps } from '@/components/sections/home-data';
import { cn } from '@/lib/utils';

export function HowItWorksSection() {
  return (
    <section id="how" className="bg-secondary px-5 py-24 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <SectionHeading
          align="center"
          eyebrow="How it works"
          title={
            <>
              Up and running in
              <br />
              <em className="text-primary">under an hour.</em>
            </>
          }
          description="No calls, no onboarding, no complexity. Subscribe, get your board, and start submitting requests immediately."
        />
        <div className="relative grid gap-8 md:grid-cols-4 md:gap-0">
          <div className="absolute top-[34px] left-[12.5%] z-0 hidden h-px w-3/4 bg-border md:block" />
          {steps.map((step) => (
            <div key={step.num} className="relative z-10 text-center md:px-3">
              <div
                className={cn(
                  'mx-auto mb-5 flex size-[68px] items-center justify-center rounded-full border bg-card font-display text-[26px] font-normal text-muted-foreground transition-all',
                  step.active &&
                    'border-primary bg-primary text-primary-foreground shadow-[0_4px_20px_hsl(var(--primary)/0.30)]',
                )}
              >
                {step.num}
              </div>
              <h3 className="mb-2 text-[15px] font-bold">{step.title}</h3>
              <p className="text-[13px] leading-6 text-muted-foreground">{step.desc}</p>
              <div className="mt-2.5 space-y-1">
                {step.feats.map((feat) => (
                  <div
                    key={feat}
                    className="inline-flex items-center gap-1.5 text-[11px] text-muted-foreground md:flex md:justify-center"
                  >
                    <Check className="size-3 text-emerald-700" aria-hidden="true" />
                    {feat}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
