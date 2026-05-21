import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { router } from '@inertiajs/react';
import { Download, FileText } from 'lucide-react';

type Version = {
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
  workflow_status: string;
  workflow_label: string;
  trello_card_id: string;
  customer: { id: number; name: string };
  versions: Version[];
};

type WorkflowOption = { value: string; label: string };

function pipelineVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (status === 'summarized') {
    return 'default';
  }
  if (status === 'failed') {
    return 'destructive';
  }

  return 'secondary';
}

export default function AdminWritingRequestsPage({
  tasks,
  workflowStatuses,
}: {
  tasks: Task[];
  workflowStatuses: WorkflowOption[];
}) {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <FileText className="size-5" aria-hidden />
        </div>
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Writing requests</h1>
          <p className="text-sm text-muted-foreground">
            Client cards, AI brief versions, and workflow status
          </p>
        </div>
      </div>

      {tasks.length === 0 ? (
        <Card>
          <CardContent className="py-10 text-center text-sm text-muted-foreground">
            No writing requests yet.
          </CardContent>
        </Card>
      ) : (
        tasks.map((task) => (
          <Card key={task.id} className="overflow-hidden rounded-xl border bg-card shadow-sm">
            <CardHeader className="border-b bg-muted/30 py-4">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <CardTitle className="text-base">{task.title}</CardTitle>
                  <CardDescription>
                    {task.customer.name} · Card {task.trello_card_id}
                  </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="outline">{task.workflow_label}</Badge>
                  <Select
                    value={task.workflow_status}
                    onValueChange={(value) =>
                      router.patch(`/admin/writing-requests/${task.id}/workflow-status`, {
                        workflow_status: value,
                      })
                    }
                  >
                    <SelectTrigger className="w-[180px]">
                      <SelectValue placeholder="Change status" />
                    </SelectTrigger>
                    <SelectContent>
                      {workflowStatuses.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow className="hover:bg-transparent">
                    <TableHead className="pl-6">Version</TableHead>
                    <TableHead>Trigger</TableHead>
                    <TableHead>Pipeline</TableHead>
                    <TableHead>Truncated</TableHead>
                    <TableHead>Document</TableHead>
                    <TableHead className="w-14 pr-6 text-right">Get</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {task.versions.map((version) => (
                    <TableRow key={version.id}>
                      <TableCell className="pl-6 font-medium">
                        v{version.version_number}
                        {version.is_latest ? (
                          <Badge className="ml-2" variant="default">
                            Latest
                          </Badge>
                        ) : (
                          <Badge className="ml-2" variant="secondary">
                            Superseded
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="capitalize">{version.trigger}</TableCell>
                      <TableCell>
                        <Badge variant={pipelineVariant(version.pipeline_status)}>
                          {version.pipeline_status}
                        </Badge>
                      </TableCell>
                      <TableCell>{version.was_truncated ? 'Yes' : '—'}</TableCell>
                      <TableCell className="font-mono text-xs">
                        {version.document_filename ?? '—'}
                      </TableCell>
                      <TableCell className="pr-6 text-right">
                        {version.has_document ? (
                          <Button variant="ghost" size="icon" className="size-9 shrink-0" asChild>
                            <a
                              href={`/admin/writing-requests/versions/${version.id}/download`}
                              title="Download document"
                              download
                            >
                              <Download className="size-4" />
                              <span className="sr-only">Download</span>
                            </a>
                          </Button>
                        ) : (
                          '—'
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        ))
      )}
    </div>
  );
}
