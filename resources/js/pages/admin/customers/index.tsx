import { router } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { customers as customersIndex } from '@/routes/admin';
import { show as customerShow } from '@/routes/admin/customers';
import { ChevronRight, ExternalLink, Search, Users, X } from 'lucide-react';

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

type CustomerFilters = {
    status?: string;
    plan_id?: string;
    search?: string;
};

function buildCustomersQuery(
    search: string,
    filters: CustomerFilters,
): Record<string, string> {
    const query: Record<string, string> = {};

    if (filters.status) {
        query.status = filters.status;
    }

    if (filters.plan_id) {
        query.plan_id = filters.plan_id;
    }

    const trimmed = search.trim();

    if (trimmed !== '') {
        query.search = trimmed;
    }

    return query;
}

function visitCustomersIndex(search: string, filters: CustomerFilters): void {
    router.get(
        customersIndex.url({
            query: buildCustomersQuery(search, filters),
        }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
}

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
    filters,
}: {
    customers: PaginatedCustomers;
    filters: CustomerFilters;
}) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        setSearch(filters.search ?? '');
    }, [filters.search]);

    const handleSearchSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        visitCustomersIndex(search, filters);
    };

    const handleClearSearch = (): void => {
        setSearch('');
        visitCustomersIndex('', filters);
    };

    const hasActiveSearch = (filters.search ?? '').trim() !== '';

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

            <Card className="overflow-hidden rounded-xl border bg-card p-0 shadow-sm">
                <CardHeader className="border-b bg-muted/30 py-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="min-w-0 flex-1 space-y-1">
                            <CardTitle className="text-base">All customers</CardTitle>
                            <CardDescription>
                                Select a row to open the customer profile
                            </CardDescription>
                            {customers.last_page > 1 ? (
                                <p className="text-xs text-muted-foreground lg:hidden">
                                    Page {customers.current_page} of {customers.last_page}
                                </p>
                            ) : null}
                        </div>
                        <form
                            onSubmit={handleSearchSubmit}
                            className="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-full lg:max-w-md lg:shrink-0 xl:max-w-lg"
                            role="search"
                            aria-label="Search customers"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden
                                />
                                <Input
                                    type="search"
                                    name="search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder="Search by name or email"
                                    className="h-10 bg-background pl-9"
                                    autoComplete="off"
                                />
                            </div>
                            <div className="flex shrink-0 gap-2">
                                <Button type="submit" className="flex-1 gap-2 sm:flex-none">
                                    <Search className="size-4" aria-hidden />
                                    Search
                                </Button>
                                {hasActiveSearch ? (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="gap-1.5"
                                        onClick={handleClearSearch}
                                    >
                                        <X className="size-4" aria-hidden />
                                        <span className="sr-only sm:not-sr-only">Clear</span>
                                    </Button>
                                ) : null}
                            </div>
                        </form>
                    </div>
                    {customers.last_page > 1 ? (
                        <p className="mt-3 hidden text-sm text-muted-foreground lg:block">
                            Page {customers.current_page} of {customers.last_page}
                        </p>
                    ) : null}
                </CardHeader>
                <CardContent className="p-0">
                    {customers.data.length === 0 ? (
                        <p className="px-6 py-12 text-center text-sm text-muted-foreground">
                            {hasActiveSearch
                                ? `No customers match "${filters.search}".`
                                : 'No customers found.'}
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
