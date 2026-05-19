import { useEffect, useState } from 'react';
import { Check, Loader2 } from 'lucide-react';
import toast from 'react-hot-toast';

import { CheckoutTrialDialog } from '@/components/checkout/CheckoutTrialDialog';
import { SectionHeading } from '@/components/sections/SectionHeading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type TrialConfig = {
  enabled: boolean;
  days: number;
};

type Plan = {
  id: number;
  name: string;
  slug: string;
  price: string;
  features: string[];
  is_featured: boolean;
  trial?: TrialConfig;
  /** When false, checkout is disabled (e.g. no Stripe secret or zero price). */
  checkout_available?: boolean;
};

export function PricingSection() {
  const [plans, setPlans] = useState<Plan[]>([]);
  const [isLoadingPlans, setIsLoadingPlans] = useState(true);
  const [loadingPlanId, setLoadingPlanId] = useState<number | null>(null);
  const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null);
  const [trialDialogOpen, setTrialDialogOpen] = useState(false);


  useEffect(() => {
    const loadPlans = async () => {
      try {
        const response = await fetch('/plans', {
          headers: {
            Accept: 'application/json',
          },
        });

        if (!response.ok) {
          throw new Error('Failed to load plans.');
        }

        const planData = (await response.json()) as Plan[];
        setPlans(planData);
      } catch {
        toast.error('Unable to load plans right now.');
      } finally {
        setIsLoadingPlans(false);
      }
    };

    void loadPlans();
  }, []);

  const startCheckout = async (plan: Plan): Promise<void> => {
    if (plan.checkout_available === false) {
      toast.error('Checkout is unavailable for this plan (payments not configured or invalid price).');

      return;
    }

    setLoadingPlanId(plan.id);

    try {
      const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

      const response = await fetch('/checkout', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify({ plan_id: plan.id }),
      });

      const payload = (await response.json()) as { checkout_url?: string; message?: string };

      if (!response.ok || !payload.checkout_url) {
        throw new Error(payload.message ?? 'Checkout failed.');
      }

      window.location.href = payload.checkout_url;
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'Something went wrong.');
    } finally {
      setLoadingPlanId(null);
    }
  };

  const handleGetStarted = (plan: Plan): void => {
    if (plan.checkout_available === false) {
      return;
    }

    if (plan.trial?.enabled && plan.trial.days > 0) {
      setSelectedPlan(plan);
      setTrialDialogOpen(true);

      return;
    }

    void startCheckout(plan);
  };

  const handleTrialContinue = (): void => {
    if (!selectedPlan) {
      return;
    }

    void startCheckout(selectedPlan);
  };

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
          {isLoadingPlans &&
            Array.from({ length: 3 }).map((_, index) => (
              <Card key={`plan-skeleton-${index}`} className="rounded-[20px] bg-background shadow-none">
                <CardContent className="space-y-4 p-7">
                  <Skeleton className="h-4 w-24" />
                  <Skeleton className="h-12 w-28" />
                  <Skeleton className="h-4 w-20" />
                  <Separator className="my-5" />
                  <Skeleton className="h-4 w-full" />
                  <Skeleton className="h-4 w-5/6" />
                  <Skeleton className="h-4 w-3/4" />
                  <Skeleton className="h-10 w-full" />
                </CardContent>
              </Card>
            ))}

          {!isLoadingPlans &&
            plans.map((plan) => (
              <Card
                key={plan.id}
                className={cn(
                  'relative rounded-[20px] bg-background shadow-none transition-all hover:-translate-y-0.5 hover:shadow-[0_8px_32px_rgba(0,0,0,0.08)]',
                  plan.is_featured && 'border-foreground bg-foreground pt-4 text-background lg:-mt-4',
                )}
              >
                {plan.is_featured && (
                  <Badge className="absolute -top-3 left-1/2 -translate-x-1/2 px-4 py-1 text-[10px] tracking-[0.08em] whitespace-nowrap uppercase">
                    Most Popular
                  </Badge>
                )}
                <CardContent className={cn('p-7', plan.is_featured && 'pt-8')}>
                  <div className="mb-3.5 text-[11px] font-bold tracking-[0.1em] text-muted-foreground uppercase">
                    {plan.name}
                  </div>
                  <div className="font-display text-[52px] leading-none font-normal tracking-normal">
                    ${plan.price}
                  </div>
                  <p
                    className={cn(
                      'mt-1 mb-6 text-sm text-muted-foreground',
                      plan.is_featured && 'text-background/50',
                    )}
                  >
                    per month
                    {plan.trial?.enabled && plan.trial.days > 0 ? (
                      <span className="block text-xs">Includes {plan.trial.days}-day free trial</span>
                    ) : null}
                  </p>
                  <Separator className={cn('my-5', plan.is_featured && 'bg-background/15')} />
                  <ul className="mb-7 space-y-3">
                    {plan.features.map((feat) => (
                      <li
                        key={feat}
                        className={cn(
                          'flex items-start gap-2.5 text-[13.5px]',
                          plan.is_featured && 'text-background/85',
                        )}
                      >
                        <span
                          className={cn(
                            'mt-0.5 flex size-[18px] shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700',
                            plan.is_featured && 'bg-background/20 text-background',
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
                    variant={plan.is_featured ? 'default' : 'secondary'}
                    disabled={loadingPlanId !== null || plan.checkout_available === false}
                    onClick={() => handleGetStarted(plan)}
                  >
                    {plan.checkout_available === false ? (
                      'Checkout unavailable'
                    ) : loadingPlanId === plan.id ? (
                      <span className="inline-flex items-center gap-2">
                        <Loader2 className="size-4 animate-spin" />
                        Redirecting...
                      </span>
                    ) : (
                      'Get started'
                    )}
                  </Button>
                </CardContent>
              </Card>
            ))}
        </div>
      </div>

      <CheckoutTrialDialog
        open={trialDialogOpen}
        onOpenChange={setTrialDialogOpen}
        plan={selectedPlan}
        trialDays={selectedPlan?.trial?.days ?? 7}
        onContinue={handleTrialContinue}
        isContinuing={selectedPlan !== null && loadingPlanId === selectedPlan.id}
      />
    </section>
  );
}
