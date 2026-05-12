import { FrontendHeader } from './partials/frontend-header';
import { FrontendFooter } from './partials/frontend-footer';

export default function FrontendLayout({ children }: { children: React.ReactNode }) {
    return (
      <div className="min-h-dvh bg-background text-foreground">
        <FrontendHeader />
        <main>
          {children}
        </main>
        <FrontendFooter />
      </div>
    );
  }
  