import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Users,
    CreditCard,
    CheckSquare,
    FileText,
    ArrowUpRight,
} from 'lucide-react';

type SubmittedRequest = {
    id: number;
    title: string;
    submitted_at: string | null;
    customer_name: string | null;
    customer_email: string | null;
    task_id: number;
};

type DashboardProps = {
    totalCustomers: number;
    activeCustomers: number;
    totalTasks: number;
    totalFiles: number;
    recentSubmittedRequests: SubmittedRequest[];
};

export default function AdminDashboardPage({
    totalCustomers,
    activeCustomers,
    totalTasks,
    totalFiles,
    recentSubmittedRequests = [],
}: DashboardProps) {
    const stats = [
        {
            label: 'Total Customers',
            value: totalCustomers,
            description: 'Lifetime registered clients',
            icon: Users,
            trend: '+12% this month',
        },
        {
            label: 'Active Subscriptions',
            value: activeCustomers,
            description: 'Currently on recurring plans',
            icon: CreditCard,
            trend: '84% retention rate',
        },
        {
            label: 'Total Tasks Received',
            value: totalTasks,
            description: 'Content requests managed',
            icon: CheckSquare,
            trend: '+24% weekly velocity',
        },
        {
            label: 'Documents Generated',
            value: totalFiles,
            description: 'Delivered copy assets',
            icon: FileText,
            trend: 'Production optimized',
        },
    ];

    return (
        <div className="space-y-8">
            {/* Dashboard Header */}
            <div className="flex flex-col gap-1.5 border-b border-border pb-5">
                <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                    Dashboard
                </h1>
                <p className="text-sm text-muted-foreground">
                    Real-time insights and operational overview for MayWrites.
                </p>
            </div>

            {/* Grid Canvas */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {stats.map((stat) => {
                    const Icon = stat.icon;
                    return (
                        <Card
                            key={stat.label}
                            className="rounded-xl border border-border bg-card text-card-foreground shadow-xs transition-all hover:border-zinc-300 hover:shadow-md dark:hover:border-zinc-800"
                        >
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    {stat.label}
                                </CardTitle>
                                <div className="rounded-lg border border-border/50 bg-secondary p-2 text-muted-foreground">
                                    <Icon className="h-4 w-4" />
                                </div>
                            </CardHeader>

                            <CardContent className="pt-2">
                                <div className="text-3xl font-semibold tracking-tight text-foreground">
                                    {stat.value.toLocaleString()}
                                </div>

                                <div className="mt-2.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span className="inline-flex items-center gap-0.5 rounded-sm bg-emerald-500/10 px-1 py-0.5 font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                                        <ArrowUpRight className="h-3 w-3" />
                                        {stat.trend.split(' ')[0]}
                                    </span>
                                    <span className="truncate">
                                        {stat.trend.substring(
                                            stat.trend.indexOf(' ') + 1,
                                        )}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            <Card className="rounded-xl border border-border">
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="text-base">Recent submitted requests</CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Cards labeled Request Completed on client boards.
                        </p>
                    </div>
                    <Link
                        href="/admin/writing-requests"
                        className="text-sm font-medium text-primary hover:underline"
                    >
                        View all
                    </Link>
                </CardHeader>
                <CardContent>
                    {recentSubmittedRequests.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No submitted requests yet.</p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {recentSubmittedRequests.map((request) => (
                                <li
                                    key={request.id}
                                    className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-medium text-foreground">{request.title}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {request.customer_name ?? 'Unknown'} ·{' '}
                                            {request.customer_email ?? '—'}
                                        </p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {request.submitted_at
                                            ? new Date(request.submitted_at).toLocaleString()
                                            : '—'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
