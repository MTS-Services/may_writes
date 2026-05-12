import { Button } from '@/components/ui/button';

function scrollToSection(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
}

export function FinalCtaSection() {
  return (
    <section className="bg-foreground px-5 py-24 text-center text-background sm:px-10">
      <div className="mx-auto max-w-[640px]">
        <div className="mb-5 text-[11px] font-bold tracking-[0.09em] text-background/40 uppercase">
          Get started today
        </div>
        <h2 className="mb-4 font-display text-4xl leading-[1.08] font-normal text-background sm:text-[50px]">
          Ready to stop chasing writers and start <em className="text-primary">publishing?</em>
        </h2>
        <p className="mb-9 text-[15px] leading-7 text-background/50">
          Join 47+ brands who never worry about content again. Your Trello board is ready in under
          an hour.
        </p>
        <div className="mb-7 flex flex-wrap items-center justify-center gap-3">
          <Button size="lg" onClick={() => scrollToSection('pricing')}>
            Start your subscription
          </Button>
          <Button
            className="border-background/30 bg-transparent text-background hover:bg-background/10 hover:text-background"
            size="lg"
            variant="outline"
            onClick={() => scrollToSection('how')}
          >
            See how it works
          </Button>
        </div>
        <p className="text-xs text-background/30">
          No contracts. Cancel anytime. Setup in under 1 hour.
        </p>
      </div>
    </section>
  );
}
