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
import { download } from '@/routes/admin/files';
import { Download, FileText } from 'lucide-react';

type Task = {
  id: number;
  document_filename?: string | null;
  title: string;
  status: string;
  customer?: { name: string } | null;
};

function taskStatusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  const s = status.toLowerCase();
  if (s === 'summarized') {
    return 'default';
  }
  if (s === 'failed') {
    return 'destructive';
  }

  return 'secondary';
}

export default function AdminFilesIndexPage({ tasks }: { tasks: { data: Task[] } }) {
  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <FileText className="size-5" aria-hidden />
        </div>
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Generated documents</h1>
          <p className="text-sm text-muted-foreground">DOCX briefs produced for client tasks</p>
        </div>
      </div>

      <Card className="overflow-hidden rounded-xl border bg-card shadow-sm">
        <CardHeader className="border-b bg-muted/30 py-4">
          <CardTitle className="text-base">Files</CardTitle>
          <CardDescription>Download is authenticated; files are not public URLs</CardDescription>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader>
              <TableRow className="hover:bg-transparent">
                <TableHead className="pl-6">Filename</TableHead>
                <TableHead>Customer</TableHead>
                <TableHead>Task</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-14 pr-6 text-right">Get</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {tasks.data.map((task) => (
                <TableRow key={task.id}>
                  <TableCell className="pl-6 font-mono text-xs sm:text-sm">
                    {task.document_filename ?? '—'}
                  </TableCell>
                  <TableCell>{task.customer?.name ?? 'N/A'}</TableCell>
                  <TableCell className="max-w-xs truncate font-medium">{task.title}</TableCell>
                  <TableCell>
                    <Badge variant={taskStatusVariant(task.status)}>{task.status}</Badge>
                  </TableCell>
                  <TableCell className="pr-6 text-right">
                    <Button variant="ghost" size="icon" className="size-9 shrink-0" asChild>
                      <a href={download.url(task.id)} title="Download document" download>
                        <Download className="size-4" />
                        <span className="sr-only">Download</span>
                      </a>
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
