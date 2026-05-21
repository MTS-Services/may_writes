import { router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as customerShow } from '@/routes/admin/customers';
import { ChevronRight, ExternalLink, Users } from 'lucide-react';

type Customer = {
    id: number;
    name: string;
    email: string;
    status: string;
    plan?: { name: string } | null;
    trello_board_url?: string | null;
};

type PaginatedCustomers = {
    data: Customer[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    const s = (status ?? '').toLowerCase();
    if (s === 'active') {
        return 'default';
    }
    if (s === 'cancelled' || s === 'past_due') {
        return 'destructive';
    }

    return 'secondary';
}

export default function AdminCustomersIndexPage({
    customers,
}: {
    customers: PaginatedCustomers;
}) {
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Users className="size-5" aria-hidden />
                    </div>
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Customers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Subscribers and their workspace links
                        </p>
                    </div>
                </div>
                {customers.total > 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {customers.total} customer{customers.total === 1 ? '' : 's'}
                    </p>
                ) : null}
            </div>

            <Card className="overflow-hidden rounded-xl border bg-card shadow-sm p-0">
                <CardHeader className="border-b bg-muted/30 py-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle className="text-base">All customers</CardTitle>
                            <CardDescription>
                                Select a row to open the customer profile
                            </CardDescription>
                        </div>
                        {customers.last_page > 1 ? (
                            <p className="text-sm text-muted-foreground">
                                Page {customers.current_page} of {customers.last_page}
                            </p>
                        ) : null}
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    {customers.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            No customers found.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="pl-6">Name</TableHead>
                                    <TableHead>Plan</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Trello</TableHead>
                                    <TableHead className="w-12 pr-6 text-right" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {customers.data.map((customer) => (
                                    <TableRow
                                        key={customer.id}
                                        role="link"
                                        tabIndex={0}
                                        className="cursor-pointer"
                                        onClick={() =>
                                            router.visit(
                                                customerShow.url(customer.id),
                                            )
                                        }
                                        onKeyDown={(event) => {
                                            if (
                                                event.key === 'Enter' ||
                                                event.key === ' '
                                            ) {
                                                event.preventDefault();
                                                router.visit(
                                                    customerShow.url(customer.id),
                                                );
                                            }
                                        }}
                                    >
                                        <TableCell className="pl-6">
                                            <div className="font-medium">
                                                {customer.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {customer.email}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {customer.plan?.name ?? 'N/A'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={statusBadgeVariant(
                                                    customer.status,
                                                )}
                                                className="capitalize"
                                            >
                                                {customer.status.replace('_', ' ')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            {customer.trello_board_url ? (
                                                <a
                                                    href={customer.trello_board_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1.5 text-sm font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    <ExternalLink
                                                        className="size-4 shrink-0"
                                                        aria-hidden
                                                    />
                                                    Board
                                                </a>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Pending
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="pr-6 text-right text-muted-foreground">
                                            <ChevronRight
                                                className="ml-auto size-4"
                                                aria-hidden
                                            />
                                            <span className="sr-only">
                                                Open customer
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    {customers.last_page > 1 ? (
                        <div className="flex flex-col gap-4 border-t bg-muted/20 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Showing {customers.from ?? 0}–{customers.to ?? 0} of{' '}
                                {customers.total}
                            </p>
                            <nav
                                className="flex flex-wrap gap-1"
                                aria-label="Customers pagination"
                            >
                                {customers.links.map((link, index) => {
                                    if (link.url === null) {
                                        return (
                                            <span
                                                key={`${link.label}-${index}`}
                                                className="inline-flex min-w-9 items-center justify-center rounded-md px-3 py-1.5 text-sm text-muted-foreground"
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        );
                                    }

                                    return (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            variant={
                                                link.active ? 'default' : 'outline'
                                            }
                                            size="sm"
                                            className="min-w-9"
                                            onClick={() =>
                                                router.visit(link.url!, {
                                                    preserveScroll: true,
                                                    preserveState: true,
                                                })
                                            }
                                        >
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        </Button>
                                    );
                                })}
                            </nav>
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        </div>
    );
}
