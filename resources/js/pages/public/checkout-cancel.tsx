import { AlertTriangle } from 'lucide-react';

export default function CheckoutCancelPage() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[#0D0D0B] px-6 text-[#F8F7F4]">
      <div className="w-full max-w-xl text-center">
        <AlertTriangle className="mx-auto mb-6 size-16 text-amber-500" />
        <h1 className="text-4xl font-semibold tracking-tight">Payment cancelled</h1>
        <p className="mt-4 text-base text-[#D8D5CC]">
          No worries — nothing was charged. Head back and pick a plan when you're ready.
        </p>
        <div className="mt-8">
          <a href="/#pricing" className="rounded-md border border-[#3A3932] px-4 py-2 text-[#F8F7F4] hover:border-[#5F5C52]">
            View plans →
          </a>
        </div>
      </div>
    </div>
  );
}
