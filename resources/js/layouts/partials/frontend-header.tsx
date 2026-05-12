

import { frontendNavLinks } from '@/components/sections/home-data';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';

function scrollToHash(hash: string) {
  document.getElementById(hash.replace('#', ''))?.scrollIntoView({ behavior: 'smooth' });
}

export function FrontendHeader() {
  return (
    <header className="fixed inset-x-0 top-0 z-50 border-b bg-background/90 backdrop-blur">
      <nav className="mx-auto flex h-[66px] max-w-[1160px] items-center justify-between px-5 sm:px-10">
        <Link
          className="font-display text-[22px] leading-none text-foreground"
          href="/#hero"
          onClick={() => scrollToHash('#hero')}
        >
          May<em className="text-primary">Writes</em>
        </Link>

        <div className="hidden items-center gap-7 md:flex">
          {frontendNavLinks.map((link) => (
            <Button
              key={link.href}
              variant="link"
              size="sm"
              className="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground hover:no-underline px-0 cursor-pointer"
              onClick={() => scrollToHash(link.href)}
            >
              {link.label}
            </Button>
          ))}
        </div>

        <div className="flex items-center gap-2.5">
          {/* <Button asChild size="sm" variant="outline">
            <Link to="/login">Log in</Link>
          </Button> */}
          <Button
            className="hidden sm:inline-flex"
            size="sm"
            onClick={() => scrollToHash('#pricing')}
          >
            View pricing
          </Button>
        </div>
      </nav>
    </header>
  );
}
