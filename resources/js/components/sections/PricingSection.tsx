import { Check } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { plans } from '@/components/sections/home-data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

export function PricingSection() {
  return (
    <section id="pricing" className="bg-card px-5 py-24 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <SectionHeading
          align="center"
          eyebrow="Pricing"
          title={
            <>
              Simple, <em className="text-primary">transparent</em> pricing.
            </>
          }
          description="No hidden fees. No per-word billing. Just a flat monthly rate for unlimited writing requests."
        />
        <div className="grid gap-5 lg:grid-cols-3 lg:items-start">
          {plans.map((plan) => (
            <Card
              key={plan.name}
              className={cn(
                'relative rounded-[20px] bg-background shadow-none transition-all hover:-translate-y-0.5 hover:shadow-[0_8px_32px_rgba(0,0,0,0.08)]',
                plan.featured && 'border-foreground bg-foreground pt-4 text-background lg:-mt-4',
              )}
            >
              {plan.featured && (
                <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 text-[10px] tracking-[0.08em] whitespace-nowrap uppercase">
                  Most Popular
                </Badge>
              )}
              <CardContent className={cn('p-7', plan.featured && 'pt-8')}>
                <div className="mb-3.5 text-[11px] font-bold tracking-[0.1em] text-muted-foreground uppercase">
                  {plan.name}
                </div>
                <div className="font-display text-[52px] leading-none font-normal tracking-normal">
                  {plan.price}
                </div>
                <p
                  className={cn(
                    'mt-1 mb-6 text-sm text-muted-foreground',
                    plan.featured && 'text-background/50',
                  )}
                >
                  per month
                </p>
                <Separator className={cn('my-5', plan.featured && 'bg-background/15')} />
                <ul className="mb-7 space-y-3">
                  {plan.feats.map((feat) => (
                    <li
                      key={feat}
                      className={cn(
                        'flex items-start gap-2.5 text-[13.5px]',
                        plan.featured && 'text-background/85',
                      )}
                    >
                      <span
                        className={cn(
                          'mt-0.5 flex size-[18px] shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700',
                          plan.featured && 'bg-background/20 text-background',
                        )}
                      >
                        <Check className="size-3" aria-hidden="true" />
                      </span>
                      {feat}
                    </li>
                  ))}
                </ul>
                <Button
                  className="w-full"
                  size="lg"
                  variant={plan.featured ? 'default' : 'secondary'}
                >
                  Get started
                </Button>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}
