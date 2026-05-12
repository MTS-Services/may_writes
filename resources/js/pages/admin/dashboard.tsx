type DashboardProps = {
  totalCustomers: number;
  activeCustomers: number;
  totalTasks: number;
  totalFiles: number;
};

export default function AdminDashboardPage({ totalCustomers, activeCustomers, totalTasks, totalFiles }: DashboardProps) {
  const stats = [
    ['Total Customers', totalCustomers],
    ['Active Subscriptions', activeCustomers],
    ['Total Tasks Received', totalTasks],
    ['Documents Generated', totalFiles],
  ];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Dashboard</h1>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {stats.map(([label, value]) => (
          <div key={label} className="rounded-lg border bg-white p-4">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-2 text-3xl font-semibold">{value}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
