import { trustedLogos } from '@/components/sections/home-data';

export function TrustedBySection() {
  return (
    <section className="border-y bg-card px-5 py-10 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <p className="mb-6 text-center text-[11px] font-semibold tracking-[0.1em] text-muted-foreground uppercase">
          Trusted by fast-growing brands
        </p>
        <div className="flex flex-wrap items-center justify-center gap-x-12 gap-y-4">
          {trustedLogos.map((logo) => (
            <span
              key={logo}
              className="font-display text-lg text-border transition-colors hover:text-muted-foreground"
            >
              {logo}
            </span>
          ))}
        </div>
      </div>
    </section>
  );
}
