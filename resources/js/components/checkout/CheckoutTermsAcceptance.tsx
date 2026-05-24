import { Link } from '@inertiajs/react';

import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type CheckoutTermsAcceptanceProps = {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    termsVersion: string;
    disabled?: boolean;
    className?: string;
};

export function CheckoutTermsAcceptance({
    checked,
    onCheckedChange,
    termsVersion,
    disabled = false,
    className,
}: CheckoutTermsAcceptanceProps) {
    return (
        <div className={cn('flex items-start gap-3 rounded-lg border bg-muted/30 p-4 text-left', className)}>
            <Checkbox
                id="accept-terms"
                checked={checked}
                onCheckedChange={(value) => onCheckedChange(value === true)}
                disabled={disabled}
                className="mt-0.5"
            />
            <Label htmlFor="accept-terms" className="cursor-pointer text-sm leading-relaxed font-normal">
                I have read and agree to the{' '}
                <Link href="/terms" className="text-primary underline underline-offset-2" target="_blank">
                    Terms and Conditions
                </Link>{' '}
                (version {termsVersion}).
            </Label>
        </div>
    );
}
