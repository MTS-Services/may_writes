type Task = {
  id: number;
  document_filename?: string | null;
  title: string;
  status: string;
  customer?: { name: string } | null;
};

export default function AdminFilesIndexPage({ tasks }: { tasks: { data: Task[] } }) {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Generated Documents</h1>
      <div className="overflow-hidden rounded-lg border bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-neutral-50">
            <tr>
              <th className="px-4 py-3">Filename</th>
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3">Task</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Download</th>
            </tr>
          </thead>
          <tbody>
            {tasks.data.map((task) => (
              <tr key={task.id} className="border-t">
                <td className="px-4 py-3">{task.document_filename ?? 'No document'}</td>
                <td className="px-4 py-3">{task.customer?.name ?? 'N/A'}</td>
                <td className="px-4 py-3">{task.title}</td>
                <td className="px-4 py-3">{task.status}</td>
                <td className="px-4 py-3">
                  <a href={`/admin/files/${task.id}/download`} target="_blank" rel="noreferrer">
                    Download
                  </a>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
