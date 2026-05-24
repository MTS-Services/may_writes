import { footerLinks } from '@/components/sections/home-data';
import { Link } from '@inertiajs/react';

export function FrontendFooter() {
  return (
    <footer className="bg-[#080807] px-5 py-9 text-background sm:px-10">
      <div className="mx-auto flex max-w-[1160px] flex-wrap items-center justify-between gap-4">
        <Link className="font-display text-[19px] text-background/80" href="/#hero">
          May<em className="text-primary">Writes</em>
        </Link>
        <div className="flex flex-wrap gap-x-6 gap-y-2">
          {footerLinks.map((link) =>
            link.href.startsWith('mailto:') ? (
              <a
                key={link.label}
                className="text-[13px] text-background/35 transition-colors hover:text-background/70"
                href={link.href}
              >
                {link.label}
              </a>
            ) : 'external' in link && link.external ? (
              <Link
                key={link.href}
                className="text-[13px] text-background/35 transition-colors hover:text-background/70"
                href={link.href}
              >
                {link.label}
              </Link>
            ) : (
              <Link
                key={link.href}
                className="text-[13px] text-background/35 transition-colors hover:text-background/70"
                href={`/${link.href}`}
              >
                {link.label}
              </Link>
            ),
          )}
        </div>
        <div className="text-xs text-background/25">2026 MayWrites.co. hello@maywrites.co</div>
      </div>
    </footer>
  );
}
