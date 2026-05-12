import { Button } from '@/components/ui/button';
import { MockDashboard } from '@/components/sections/MockDashboard';

function scrollToSection(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
}

export function HeroSection() {
  return (
    <section id="hero" className="px-5 pt-32 pb-24 sm:px-10 lg:pt-36">
      <div className="mx-auto grid max-w-[1160px] items-center gap-14 lg:grid-cols-2 lg:gap-[72px]">
        <div>
          <div className="mb-5 inline-flex items-center gap-2 rounded-full bg-primary/10 px-3.5 py-1.5 text-[11px] font-bold tracking-[0.07em] text-primary uppercase">
            <span className="size-1.5 animate-pulse rounded-full bg-primary" />
            Now accepting new clients
          </div>
          <h1 className="mb-5 font-display text-5xl leading-[1.06] font-normal text-foreground sm:text-[60px]">
            Unlimited writing,
            <br />
            one <em className="text-primary">flat</em> monthly
            <br />
            rate.
          </h1>
          <p className="mb-9 max-w-[430px] text-base leading-7 text-muted-foreground">
            Your on-demand writing team, without hiring in-house. Copywriting, newsletters, blogs,
            press releases, and more. All in one simple subscription.
          </p>
          <div className="mb-10 flex flex-wrap items-center gap-3">
            <Button size="lg" onClick={() => scrollToSection('pricing')}>
              View pricing
            </Button>
            <Button size="lg" variant="outline" onClick={() => scrollToSection('how')}>
              See how it works
            </Button>
          </div>
          <div className="flex items-center gap-4">
            <div className="flex">
              {[
                ['A', 'bg-amber-100 text-amber-800'],
                ['J', 'bg-sky-100 text-sky-800'],
                ['M', 'bg-emerald-100 text-emerald-800'],
                ['R', 'bg-violet-100 text-violet-800'],
              ].map(([letter, color], index) => (
                <div
                  key={letter}
                  className={`flex size-[30px] items-center justify-center rounded-full border-2 border-background text-[11px] font-bold ${color} ${index > 0 ? '-ml-2' : ''}`}
                >
                  {letter}
                </div>
              ))}
            </div>
            <p className="text-xs text-muted-foreground">
              <strong className="text-foreground">47+ brands</strong> publishing consistently with
              MayWrites
            </p>
          </div>
        </div>
        <MockDashboard />
      </div>
    </section>
  );
}
