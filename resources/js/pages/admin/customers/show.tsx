type Task = {
  id: number;
  title: string;
  status: string;
  document_path?: string | null;
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

export default function AdminCustomerShowPage({ customer, tasks }: { customer: Customer; tasks: Task[] }) {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Customer</h1>
      <div className="rounded-lg border bg-white p-4">
        <div className="font-semibold">{customer.name}</div>
        <div className="text-sm text-muted-foreground">{customer.email}</div>
        <div className="mt-2 text-sm">Plan: {customer.plan?.name ?? 'N/A'}</div>
        <div className="text-sm">Status: {customer.status}</div>
      </div>
      <div className="rounded-lg border bg-white p-4">
        <h2 className="mb-3 font-semibold">Recent Tasks</h2>
        <ul className="space-y-2 text-sm">
          {tasks.map((task) => (
            <li key={task.id} className="flex items-center justify-between border-b pb-2">
              <span>{task.title}</span>
              <span>{task.status}</span>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}
