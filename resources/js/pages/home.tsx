import { ComparisonSection } from '@/components/sections/ComparisonSection';
import { FaqSection } from '@/components/sections/FaqSection';
import { FinalCtaSection } from '@/components/sections/FinalCtaSection';
import { HeroSection } from '@/components/sections/HeroSection';
import { HowItWorksSection } from '@/components/sections/HowItWorksSection';
import { PortfolioSection } from '@/components/sections/PortfolioSection';
import { PricingSection } from '@/components/sections/PricingSection';
import { ServicesSection } from '@/components/sections/ServicesSection';
import { TrustedBySection } from '@/components/sections/TrustedBySection';

export default function HomePage() {
  return (
    <>
      <HeroSection />
      <TrustedBySection />
      <ServicesSection />
      <HowItWorksSection />
      <PricingSection />
      <PortfolioSection />
      <ComparisonSection />
      <FaqSection />
      <FinalCtaSection />
    </>
  );
}
