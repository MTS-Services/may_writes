import { Gift } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

type PlanSummary = {
  id: number;
  name: string;
  price: string;
};

type CheckoutTrialDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  plan: PlanSummary | null;
  trialDays: number;
  onContinue: () => void;
  isContinuing?: boolean;
};

export function CheckoutTrialDialog({
  open,
  onOpenChange,
  plan,
  trialDays,
  onContinue,
  isContinuing = false,
}: CheckoutTrialDialogProps) {
  if (!plan) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10">
            <Gift className="size-5 text-primary" aria-hidden />
          </div>
          <DialogTitle>Start your {trialDays}-day free trial</DialogTitle>
          <DialogDescription>
            You are subscribing to the <strong>{plan.name}</strong> plan (${plan.price}/month after your trial).
          </DialogDescription>
        </DialogHeader>

        <ul className="space-y-2 text-sm text-muted-foreground">
          <li>Full access during your {trialDays}-day trial — no charge today.</li>
          <li>Your card is collected securely at checkout to continue after the trial.</li>
          <li>After the trial, you are billed monthly. Renewals do not include another free trial.</li>
          <li>Cancel anytime from your account or by contacting support.</li>
        </ul>

        <DialogFooter className="gap-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isContinuing}>
            Close
          </Button>
          <Button type="button" onClick={onContinue} disabled={isContinuing}>
            {isContinuing ? 'Redirecting…' : 'Continue to checkout'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
