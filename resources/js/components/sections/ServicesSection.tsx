import {
  BookOpen,
  BriefcaseBusiness,
  FileText,
  Globe,
  Mail,
  Megaphone,
  PenLine,
  ShoppingBag,
  UserRoundPen,
} from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { services } from '@/components/sections/home-data';
import { Card, CardContent } from '@/components/ui/card';

const icons = [
  PenLine,
  UserRoundPen,
  FileText,
  Mail,
  Megaphone,
  Globe,
  ShoppingBag,
  BriefcaseBusiness,
  BookOpen,
];

export function ServicesSection() {
  return (
    <section id="services" className="bg-card px-5 py-24 sm:px-10">
      <div className="mx-auto max-w-[1160px]">
        <SectionHeading
          eyebrow="Services"
          title={
            <>
              Every type of content,
              <br />
              <em className="text-primary">one subscription.</em>
            </>
          }
          description="From high-converting copy to long-form editorial, submit any writing request and receive polished, publish-ready content."
        />
        <div className="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
          {services.map((service, index) => {
            const Icon = icons[index] ?? BookOpen;

            return (
              <Card
                key={service.name}
                className="rounded-xl bg-background shadow-none transition-all hover:-translate-y-1 hover:border-primary hover:bg-card hover:shadow-[0_0_0_3px_hsl(var(--primary)/0.10)]"
              >
                <CardContent className="p-5">
                  <Icon className="mb-3 size-6 text-primary" aria-hidden="true" />
                  <h3 className="mb-1.5 text-sm font-semibold">{service.name}</h3>
                  <p className="text-[12.5px] leading-5 text-muted-foreground">{service.desc}</p>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </div>
    </section>
  );
}
