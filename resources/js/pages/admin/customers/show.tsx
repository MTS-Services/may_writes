import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { customers } from '@/routes/admin';
import { download } from '@/routes/admin/files';
import { ArrowLeft, CreditCard, Download, Mail, User } from 'lucide-react';

type Task = {
  id: number;
  title: string;
  status: string;
  document_path?: string | null;
  document_filename?: string | null;
};

type Customer = {
  id: number;
  name: string;
  email: string;
  status: string;
  trello_board_url?: string | null;
  trello_board_id?: string | null;
  plan?: { name: string } | null;
};

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  const s = status.toLowerCase();
  if (s === 'active') {
    return 'default';
  }
  if (s === 'cancelled' || s === 'past_due') {
    return 'destructive';
  }

  return 'secondary';
}

function taskStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  const s = status.toLowerCase();
  if (s === 'summarized' || s === 'received') {
    return 'default';
  }
  if (s === 'failed') {
    return 'destructive';
  }

  return 'secondary';
}

export default function AdminCustomerShowPage({ customer, tasks }: { customer: Customer; tasks: Task[] }) {
  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Button variant="ghost" size="sm" className="-ml-2 gap-2 text-muted-foreground" asChild>
          <Link href={customers.url()}>
            <ArrowLeft className="size-4" aria-hidden />
            Customers
          </Link>
        </Button>
      </div>

      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Customer profile</h1>
        <p className="text-sm text-muted-foreground">Details and recent writing tasks</p>
      </div>

      <Card className="rounded-xl border bg-card shadow-sm">
        <CardHeader className="border-b bg-muted/30">
          <div className="flex items-start gap-4">
            <div className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
              <User className="size-6" aria-hidden />
            </div>
            <div className="min-w-0 flex-1 space-y-1">
              <CardTitle className="text-xl">{customer.name}</CardTitle>
              <CardDescription className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span className="inline-flex items-center gap-1.5">
                  <Mail className="size-3.5 shrink-0" aria-hidden />
                  {customer.email}
                </span>
              </CardDescription>
            </div>
            <Badge variant={statusBadgeVariant(customer.status)} className="shrink-0">
              {customer.status}
            </Badge>
          </div>
        </CardHeader>
        <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
          <div className="flex items-center gap-3 rounded-lg border bg-muted/20 p-4">
            <CreditCard className="size-5 text-muted-foreground" aria-hidden />
            <div>
              <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Plan</div>
              <div className="font-medium">{customer.plan?.name ?? 'N/A'}</div>
            </div>
          </div>
          <div className="flex flex-col justify-center gap-1 rounded-lg border bg-muted/20 p-4 text-sm">
            <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Trello</span>
            {customer.trello_board_url ? (
              <a
                href={customer.trello_board_url}
                target="_blank"
                rel="noreferrer"
                className="font-medium text-primary underline-offset-4 hover:underline"
              >
                Open board
              </a>
            ) : (
              <span className="text-muted-foreground">Board pending</span>
            )}
            {customer.trello_board_id ? (
              <span className="font-mono text-xs text-muted-foreground">ID {customer.trello_board_id}</span>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <Card className="overflow-hidden rounded-xl border bg-card shadow-sm">
        <CardHeader className="border-b bg-muted/30 py-4">
          <CardTitle className="text-base">Recent tasks</CardTitle>
          <CardDescription>Writing requests from this customer</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          {tasks.length === 0 ? (
            <p className="px-6 py-8 text-center text-sm text-muted-foreground">No tasks yet.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow className="hover:bg-transparent">
                  <TableHead className="pl-6">Title</TableHead>
                  <TableHead>Document</TableHead>
                  <TableHead className="pr-6 text-right">Status</TableHead>
                  <TableHead className="w-14 pr-6 text-right">Get</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {tasks.map((task) => (
                  <TableRow key={task.id}>
                    <TableCell className="max-w-md pl-6 font-medium">{task.title}</TableCell>
                    <TableCell className="max-w-48 truncate font-mono text-xs text-muted-foreground">
                      {task.document_filename ?? '—'}
                    </TableCell>
                    <TableCell className="text-right">
                      <Badge variant={taskStatusVariant(task.status)}>{task.status}</Badge>
                    </TableCell>
                    <TableCell className="pr-6 text-right">
                      {task.document_path ? (
                        <Button variant="ghost" size="icon" className="size-9 shrink-0" asChild>
                          <a href={download.url(task.id)} title="Download document" download>
                            <Download className="size-4" />
                            <span className="sr-only">Download</span>
                          </a>
                        </Button>
                      ) : (
                        <span className="text-sm text-muted-foreground">—</span>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
