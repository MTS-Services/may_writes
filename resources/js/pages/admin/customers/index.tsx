import { Link } from '@inertiajs/react';

type Customer = {
  id: number;
  name: string;
  email: string;
  status: string;
  plan?: { name: string } | null;
  trello_board_url?: string | null;
};

export default function AdminCustomersIndexPage({ customers }: { customers: { data: Customer[] } }) {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Customers</h1>
      <div className="overflow-hidden rounded-lg border bg-white">
        <table className="w-full text-left text-sm">
          <thead className="bg-neutral-50">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">Plan</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3">Trello</th>
              <th className="px-4 py-3">Action</th>
            </tr>
          </thead>
          <tbody>
            {customers.data.map((customer) => (
              <tr key={customer.id} className="border-t">
                <td className="px-4 py-3">
                  <div className="font-medium">{customer.name}</div>
                  <div className="text-xs text-muted-foreground">{customer.email}</div>
                </td>
                <td className="px-4 py-3">{customer.plan?.name ?? 'N/A'}</td>
                <td className="px-4 py-3">{customer.status}</td>
                <td className="px-4 py-3">
                  {customer.trello_board_url ? <a href={customer.trello_board_url} target="_blank" rel="noreferrer">Open</a> : 'Pending'}
                </td>
                <td className="px-4 py-3">
                  <Link href={`/admin/customers/${customer.id}`}>View</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
