import { Check, X } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { type CompareCell, compareRows } from '@/components/sections/home-data';

function renderCell(value: CompareCell) {
  if (value === true) return <Check className="size-4 text-emerald-700" aria-label="Yes" />;
  if (value === false) return <X className="size-4 text-border" aria-label="No" />;
  return <span className="text-xs text-muted-foreground">Sometimes</span>;
}

export function ComparisonSection() {
  return (
    <section id="compare" className="bg-card px-5 py-24 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <SectionHeading
          align="center"
          eyebrow="Why MayWrites"
          title={
            <>
              The smarter way
              <br />
              to get <em className="text-primary">content done.</em>
            </>
          }
          description="Compare us against freelancers and agencies. The difference is clear."
        />
        <div className="overflow-x-auto rounded-[20px] border bg-background">
          <div className="min-w-[680px]">
            <div className="grid grid-cols-[2.2fr_1fr_1fr_1fr] border-b bg-secondary">
              {['Feature', 'MayWrites', 'Freelancer', 'Agency'].map((heading, index) => (
                <div
                  key={heading}
                  className={`px-5 py-4 text-center text-[13px] font-bold ${index === 0 ? 'text-left text-xs text-muted-foreground' : ''} ${index === 1 ? 'bg-foreground text-background' : ''}`}
                >
                  {heading}
                </div>
              ))}
            </div>
            {compareRows.map(([feature, mayWrites, freelancer, agency]) => (
              <div
                key={feature}
                className="grid grid-cols-[2.2fr_1fr_1fr_1fr] border-b last:border-b-0"
              >
                <div className="flex items-center px-5 py-3.5 text-[13px] font-medium">
                  {feature}
                </div>
                <div className="flex items-center justify-center bg-foreground/5 px-5 py-3.5">
                  {renderCell(mayWrites)}
                </div>
                <div className="flex items-center justify-center px-5 py-3.5">
                  {renderCell(freelancer)}
                </div>
                <div className="flex items-center justify-center px-5 py-3.5">
                  {renderCell(agency)}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
