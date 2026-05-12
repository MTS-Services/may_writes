import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

import { SectionHeading } from '@/components/sections/SectionHeading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function CheckoutCancelPage() {
    return (
        <>
            <Head>
                <title>Checkout cancelled — MayWrites</title>
                <meta
                    name="description"
                    content="No payment was taken. Return to MayWrites when you are ready to subscribe."
                />
            </Head>
            <section className="flex min-h-[90vh] items-center justify-center bg-card px-5 py-10">
                <div className="mx-auto max-w-[640px]">
                    <Card className="rounded-[20px] border bg-background shadow-none">
                        <CardContent className="space-y-8 p-8 text-center sm:p-10">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                <AlertTriangle
                                    className="size-9"
                                    aria-hidden="true"
                                />
                            </div>
                            <SectionHeading
                                align="center"
                                eyebrow="Checkout"
                                title={
                                    <>
                                        Payment{' '}
                                        <em className="text-primary">
                                            cancelled
                                        </em>
                                    </>
                                }
                                description="Nothing was charged. When you are ready, you can pick a plan again — we will be here."
                            />
                            <div className="flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
                                <Button
                                    asChild
                                    size="lg"
                                    className="w-full sm:w-auto"
                                >
                                    <Link href="/#pricing">View pricing</Link>
                                </Button>
                                <Button
                                    asChild
                                    size="lg"
                                    variant="outline"
                                    className="w-full sm:w-auto"
                                >
                                    <Link href="/">Back to home</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </>
    );
}
