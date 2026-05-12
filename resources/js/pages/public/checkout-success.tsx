import { CheckCircle } from 'lucide-react';

export default function CheckoutSuccessPage({ sessionId }: { sessionId: string }) {
  return (
    <div className="flex min-h-screen items-center justify-center bg-[#0D0D0B] px-6 text-[#F8F7F4]">
      <div className="w-full max-w-xl text-center">
        <CheckCircle className="mx-auto mb-6 size-16 text-[#1A8A5A]" />
        <h1 className="text-4xl font-semibold tracking-tight">You're all set!</h1>
        <p className="mt-4 text-base text-[#D8D5CC]">
          Check your inbox — a welcome email with your Trello board invitation is on its way. It may take a few
          minutes.
        </p>
        {sessionId ? <p className="mt-3 text-xs text-[#9A968B]">Session: {sessionId}</p> : null}
        <div className="mt-8 flex items-center justify-center gap-4">
          <a href="/" className="rounded-md bg-[#F8F7F4] px-4 py-2 text-[#0D0D0B] transition hover:bg-white">
            Back to MayWrites.co
          </a>
          <a
            href="https://trello.com"
            target="_blank"
            rel="noreferrer"
            className="rounded-md border border-[#3A3932] px-4 py-2 text-[#F8F7F4] transition hover:border-[#5F5C52]"
          >
            Visit Trello
          </a>
        </div>
        <p className="mt-8 text-sm text-[#9A968B]">Questions? Email hello@maywrites.co</p>
      </div>
    </div>
  );
}
