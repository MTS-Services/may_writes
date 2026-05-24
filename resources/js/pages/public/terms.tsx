import { Head, Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    content: string;
    termsVersion: string;
};

export default function TermsPage({ content, termsVersion }: Props) {
    return (
        <>
            <Head>
                <title>Terms and Conditions — MayWrites</title>
                <meta
                    name="description"
                    content="MayWrites terms and conditions for subscription and writing services."
                />
            </Head>
            <section className="bg-card px-5 py-16 sm:px-10 sm:py-24">
                <div className="mx-auto max-w-[800px]">
                    <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Legal
                            </p>
                            <h1 className="font-display mt-1 text-3xl text-foreground sm:text-4xl">
                                Terms and Conditions
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">Version {termsVersion}</p>
                        </div>
                        <Button asChild variant="outline" className="w-full sm:w-auto">
                            <Link href="/#pricing">Back to pricing</Link>
                        </Button>
                    </div>
                    <Card className="rounded-[20px] border bg-background shadow-none">
                        <CardContent
                            className="prose prose-neutral dark:prose-invert max-w-none p-8 sm:p-10"
                            dangerouslySetInnerHTML={{ __html: content }}
                        />
                    </Card>
                </div>
            </section>
        </>
    );
}
