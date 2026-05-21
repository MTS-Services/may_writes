import { Link, usePage } from '@inertiajs/react';
import { FileText, LayoutDashboard, LayoutGrid, Settings, Users } from 'lucide-react';

const navItems = [
  { href: '/admin/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { href: '/admin/customers', label: 'Customers', icon: Users },
  { href: '/admin/writing-requests', label: 'Writing requests', icon: FileText },
  { href: '/admin/trello', label: 'Trello', icon: LayoutGrid },
  { href: '/admin/settings', label: 'Settings', icon: Settings },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  const { auth } = usePage().props as { auth: { user?: { name?: string } } };

  return (
    <div className="flex min-h-screen bg-neutral-50">
      <aside className="w-60 bg-[#0D0D0B] p-4 text-white">
        <div className="mb-8">
          <div className="text-xl font-semibold">MayWrites</div>
          <div className="text-xs text-white/70">Admin</div>
        </div>
        <nav className="space-y-2">
          {navItems.map((item) => (
            <Link key={item.href} href={item.href} className="flex items-center gap-2 rounded-md px-3 py-2 hover:bg-white/10">
              <item.icon className="size-4" />
              {item.label}
            </Link>
          ))}
        </nav>
        <div className="mt-10 text-sm text-white/80">{auth.user?.name}</div>
      </aside>
      <div className="flex-1">
        <div className="h-14 border-b bg-white" />
        <main className="p-6">{children}</main>
      </div>
    </div>
  );
}
