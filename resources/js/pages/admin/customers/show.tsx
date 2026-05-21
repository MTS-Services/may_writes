import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import {
  ArrowLeft,
  ChevronRight,
  CreditCard,
  Download,
  ExternalLink,
  FileStack,
  Mail,
  User,
} from 'lucide-react';

type TaskVersion = {
  id: number;
  version_number: number;
  trigger: string;
  pipeline_status: string;
  was_truncated: boolean;
  document_filename?: string | null;
  processed_at?: string | null;
  is_latest: boolean;
  has_document: boolean;
};

type Task = {
  id: number;
  title: string;
  created_at?: string | null;
  workflow_status: string;
  workflow_label: string;
  pipeline_status?: string | null;
  versions_count: number;
  versions: TaskVersion[];
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

type PaginatedTasks = {
  data: Task[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
};

function statusBadgeVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  const s = (status ?? '').toLowerCase();
  if (s === 'active') {
    return 'default';
  }
  if (s === 'cancelled' || s === 'past_due') {
    return 'destructive';
  }

  return 'secondary';
}

function pipelineVariant(status: string | null | undefined): 'default' | 'secondary' | 'destructive' | 'outline' {
  const s = (status ?? '').toLowerCase();
  if (s === 'summarized') {
    return 'default';
  }
  if (s === 'failed') {
    return 'destructive';
  }

  return 'secondary';
}

function formatDateTime(iso: string | null | undefined): string {
  if (! iso) {
    return '—';
  }

  return new Date(iso).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

function versionDownloadUrl(versionId: number): string {
  return `/admin/writing-requests/versions/${versionId}/download`;
}

export default function AdminCustomerShowPage({
  customer,
  tasks,
}: {
  customer: Customer;
  tasks: PaginatedTasks;
}) {
  const [selectedTask, setSelectedTask] = useState<Task | null>(null);

  return (
    <div className="space-y-8">
      <div className="flex flex-wrap items-center gap-4">
        <Button variant="ghost" size="sm" className="-ml-2 gap-2 text-muted-foreground" asChild>
          <Link href="/admin/customers">
            <ArrowLeft className="size-4" aria-hidden />
            Customers
          </Link>
        </Button>
      </div>

      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Customer profile</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Account overview and writing request history
          </p>
        </div>
        <Badge variant={statusBadgeVariant(customer.status)} className="capitalize">
          {customer.status.replace('_', ' ')}
        </Badge>
      </div>

      <Card className="overflow-hidden rounded-xl border shadow-sm">
        <CardHeader className="border-b bg-muted/40 pb-6">
          <div className="flex flex-col gap-6 sm:flex-row sm:items-start">
            <div className="flex size-14 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
              <User className="size-7" aria-hidden />
            </div>
            <div className="min-w-0 flex-1 space-y-2">
              <CardTitle className="text-xl font-semibold">{customer.name}</CardTitle>
              <CardDescription className="flex items-center gap-2 text-sm">
                <Mail className="size-4 shrink-0" aria-hidden />
                {customer.email}
              </CardDescription>
            </div>
          </div>
        </CardHeader>
        <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
          <div className="flex items-center gap-4 rounded-lg border bg-background p-4 shadow-sm">
            <div className="flex size-10 items-center justify-center rounded-md bg-muted">
              <CreditCard className="size-5 text-muted-foreground" aria-hidden />
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">Plan</p>
              <p className="font-semibold">{customer.plan?.name ?? 'N/A'}</p>
            </div>
          </div>
          <div className="flex flex-col justify-center gap-2 rounded-lg border bg-background p-4 shadow-sm">
            <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">Trello board</p>
            {customer.trello_board_url ? (
              <a
                href={customer.trello_board_url}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
              >
                <ExternalLink className="size-4" aria-hidden />
                Open board
              </a>
            ) : (
              <span className="text-sm text-muted-foreground">Board pending</span>
            )}
            {customer.trello_board_id ? (
              <span className="font-mono text-xs text-muted-foreground">{customer.trello_board_id}</span>
            ) : null}
          </div>
        </CardContent>
      </Card>

      <Card className="overflow-hidden rounded-xl border shadow-sm">
        <CardHeader className="border-b bg-muted/40">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <CardTitle className="text-lg">Writing requests</CardTitle>
              <CardDescription>
                {tasks.total === 0
                  ? 'No Trello cards processed for this customer yet.'
                  : `${tasks.total} request${tasks.total === 1 ? '' : 's'} total`}
              </CardDescription>
            </div>
            {tasks.total > 0 ? (
              <div className="flex items-center gap-2 rounded-md border bg-background px-3 py-1.5 text-sm text-muted-foreground">
                <FileStack className="size-4" aria-hidden />
                Page {tasks.current_page} of {tasks.last_page}
              </div>
            ) : null}
          </div>
        </CardHeader>
        <CardContent className="p-0">
          {tasks.data.length === 0 ? (
            <p className="px-6 py-12 text-center text-sm text-muted-foreground">No writing requests yet.</p>
          ) : (
            <ul className="divide-y">
              {tasks.data.map((task) => (
                <li key={task.id} className="px-6 py-5 transition-colors hover:bg-muted/30">
                  <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0 flex-1 space-y-2">
                      <h3 className="font-semibold leading-snug text-foreground">{task.title}</h3>
                      <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-muted-foreground">
                        <span>
                          <span className="font-medium text-foreground/80">Created</span>{' '}
                          {formatDateTime(task.created_at)}
                        </span>
                        <span className="hidden h-4 w-px bg-border sm:inline-block" aria-hidden />
                        <span>
                          <span className="font-medium text-foreground/80">Versions</span> {task.versions_count}
                        </span>
                      </div>
                      <div className="flex flex-wrap gap-2">
                        <Badge variant="outline">{task.workflow_label}</Badge>
                        {task.pipeline_status ? (
                          <Badge variant={pipelineVariant(task.pipeline_status)} className="capitalize">
                            {task.pipeline_status}
                          </Badge>
                        ) : (
                          <Badge variant="secondary">No pipeline</Badge>
                        )}
                      </div>
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      className="shrink-0 gap-2"
                      onClick={() => setSelectedTask(task)}
                    >
                      View documents
                      <ChevronRight className="size-4" aria-hidden />
                    </Button>
                  </div>
                </li>
              ))}
            </ul>
          )}
          {tasks.last_page > 1 ? (
            <div className="flex flex-col gap-4 border-t bg-muted/20 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-sm text-muted-foreground">
                Showing {tasks.from ?? 0}–{tasks.to ?? 0} of {tasks.total}
              </p>
              <nav className="flex flex-wrap gap-1" aria-label="Tasks pagination">
                {tasks.links.map((link, index) => {
                  if (link.url === null) {
                    return (
                      <span
                        key={`${link.label}-${index}`}
                        className="inline-flex min-w-9 items-center justify-center rounded-md px-3 py-1.5 text-sm text-muted-foreground"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    );
                  }

                  return (
                    <Button
                      key={`${link.label}-${index}`}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="min-w-9"
                      onClick={() => router.visit(link.url!, { preserveScroll: true })}
                    >
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  );
                })}
              </nav>
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Sheet open={selectedTask !== null} onOpenChange={(open) => !open && setSelectedTask(null)}>
        <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
          {selectedTask ? (
            <>
              <SheetHeader className="border-b px-6 py-5 text-left">
                <SheetTitle className="text-left text-base leading-snug">{selectedTask.title}</SheetTitle>
                <SheetDescription className="text-left">
                  {selectedTask.versions_count} version{selectedTask.versions_count === 1 ? '' : 's'} ·{' '}
                  {selectedTask.workflow_label}
                  {selectedTask.pipeline_status ? ` · ${selectedTask.pipeline_status}` : ''}
                </SheetDescription>
              </SheetHeader>
              <div className="flex-1 overflow-y-auto">
                {selectedTask.versions.length === 0 ? (
                  <p className="px-6 py-10 text-center text-sm text-muted-foreground">No versions recorded.</p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow className="hover:bg-transparent">
                        <TableHead className="pl-6">Version</TableHead>
                        <TableHead>Pipeline</TableHead>
                        <TableHead>Processed</TableHead>
                        <TableHead className="pr-6 text-right">File</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {selectedTask.versions.map((version) => (
                        <TableRow key={version.id}>
                          <TableCell className="pl-6">
                            <div className="font-medium">v{version.version_number}</div>
                            <div className="mt-1 flex flex-wrap gap-1">
                              {version.is_latest ? (
                                <Badge>Latest</Badge>
                              ) : (
                                <Badge variant="secondary">Superseded</Badge>
                              )}
                              <Badge variant="outline" className="capitalize">
                                {version.trigger}
                              </Badge>
                            </div>
                            {version.was_truncated ? (
                              <p className="mt-1 text-xs text-amber-700 dark:text-amber-500">Truncated</p>
                            ) : null}
                          </TableCell>
                          <TableCell>
                            <Badge variant={pipelineVariant(version.pipeline_status)} className="capitalize">
                              {version.pipeline_status}
                            </Badge>
                          </TableCell>
                          <TableCell className="text-sm text-muted-foreground">
                            {formatDateTime(version.processed_at)}
                          </TableCell>
                          <TableCell className="pr-6 text-right">
                            {version.has_document ? (
                              <Button variant="outline" size="sm" className="gap-1.5" asChild>
                                <a href={versionDownloadUrl(version.id)} download title={version.document_filename ?? 'Download'}>
                                  <Download className="size-4" aria-hidden />
                                  <span className="sr-only sm:not-sr-only">Download</span>
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
                {selectedTask.versions.some((v) => v.document_filename) ? (
                  <div className="border-t px-6 py-4">
                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">Filenames</p>
                    <ul className="mt-2 space-y-1 text-xs font-mono text-muted-foreground">
                      {selectedTask.versions
                        .filter((v) => v.document_filename)
                        .map((v) => (
                          <li key={v.id} className="truncate" title={v.document_filename ?? undefined}>
                            v{v.version_number}: {v.document_filename}
                          </li>
                        ))}
                    </ul>
                  </div>
                ) : null}
              </div>
            </>
          ) : null}
        </SheetContent>
      </Sheet>
    </div>
  );
}
