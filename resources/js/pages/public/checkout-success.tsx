import { Head, Link } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    trial?: {
        enabled: boolean;
        days: number;
    };
};

export default function CheckoutSuccessPage({ trial }: Props) {
    const trialMessage =
        trial?.enabled && trial.days > 0
            ? ` Your ${trial.days}-day free trial has started — you will not be charged until it ends, then billing continues monthly.`
            : '';

    return (
        <>
            <Head>
                <title>Welcome aboard — MayWrites</title>
                <meta
                    name="description"
                    content="Your MayWrites subscription is active. Check your email for next steps."
                />
            </Head>
            <section className="flex min-h-[90vh] items-center justify-center bg-card px-5 py-10">
                <div className="mx-auto max-w-[640px]">
                    <Card className="rounded-[20px] border bg-background shadow-none">
                        <CardContent className="space-y-8 p-8 text-center sm:p-10">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">
                                <CheckCircle
                                    className="size-9"
                                    aria-hidden="true"
                                />
                            </div>
                            <SectionHeading
                                align="center"
                                eyebrow="Checkout"
                                title={
                                    <>
                                        You&apos;re{' '}
                                        <em className="text-primary">
                                            all set
                                        </em>
                                    </>
                                }
                                description={`Thank you for subscribing.${trialMessage} You will receive a welcome email shortly with onboarding details and your Trello workspace invitation when your board is ready.`}
                            />
                            <div className="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                                <Button
                                    asChild
                                    size="lg"
                                    className="w-full sm:w-auto"
                                >
                                    <Link href="/">Back to MayWrites</Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="w-full sm:w-auto"
                                >
                                    <a
                                        href="https://trello.com"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        Open Trello
                                    </a>
                                </Button>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Questions?{' '}
                                <a
                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                    href="mailto:hello@maywrites.co"
                                >
                                    hello@maywrites.co
                                </a>
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </>
    );
}
