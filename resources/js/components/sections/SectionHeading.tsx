import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type SectionHeadingProps = {
  eyebrow: string;
  title: ReactNode;
  description: string;
  align?: 'left' | 'center';
};

export function SectionHeading({
  eyebrow,
  title,
  description,
  align = 'left',
}: SectionHeadingProps) {
  const centered = align === 'center';

  return (
    <div className={cn('mb-14', centered && 'text-center')}>
      <div
        className={cn(
          'mb-4 inline-flex items-center gap-2.5 text-[11px] font-bold tracking-[0.09em] text-primary uppercase',
          centered && 'justify-center',
        )}
      >
        <span className="h-0.5 w-5 rounded-full bg-primary" />
        {eyebrow}
      </div>
      <h2 className="mb-4 font-display text-4xl leading-[1.1] font-normal text-foreground sm:text-[46px]">
        {title}
      </h2>
      <p
        className={cn(
          'max-w-xl text-[15px] leading-7 text-muted-foreground',
          centered && 'mx-auto',
        )}
      >
        {description}
      </p>
    </div>
  );
}
