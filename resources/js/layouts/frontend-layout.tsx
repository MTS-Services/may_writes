import { FrontendHeader } from './partials/frontend-header';
import { FrontendFooter } from './partials/frontend-footer';
import { useAppearance } from '@/hooks/use-appearance';
import { useEffect } from 'react';

export default function FrontendLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { appearance, updateAppearance } = useAppearance();
    useEffect(() => {
        if (appearance !== 'light') {
            updateAppearance('light');
        }
    }, [appearance, updateAppearance]);
    return (
        <div className="min-h-dvh bg-background text-foreground">
            <FrontendHeader />
            <main>{children}</main>
            <FrontendFooter />
        </div>
    );
}
