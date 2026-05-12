import { Form, useForm } from '@inertiajs/react';

type User = { name: string; email: string };

export default function AdminSettingsPage({ user }: { user: User }) {
  const profileForm = useForm({ name: user.name, email: user.email });
  const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Form action="/admin/settings" method="patch" className="rounded-lg border bg-white p-4">
        <h2 className="mb-4 text-lg font-semibold">Profile Information</h2>
        <input className="mb-3 w-full rounded border px-3 py-2" name="name" value={profileForm.data.name} onChange={(e) => profileForm.setData('name', e.target.value)} />
        <input className="mb-3 w-full rounded border px-3 py-2" name="email" value={profileForm.data.email} onChange={(e) => profileForm.setData('email', e.target.value)} />
        <button className="rounded bg-black px-4 py-2 text-white" type="submit">Save</button>
      </Form>

      <Form action="/admin/settings/password" method="put" className="rounded-lg border bg-white p-4">
        <h2 className="mb-4 text-lg font-semibold">Update Password</h2>
        <input className="mb-3 w-full rounded border px-3 py-2" type="password" name="current_password" value={passwordForm.data.current_password} onChange={(e) => passwordForm.setData('current_password', e.target.value)} placeholder="Current password" />
        <input className="mb-3 w-full rounded border px-3 py-2" type="password" name="password" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} placeholder="New password" />
        <input className="mb-3 w-full rounded border px-3 py-2" type="password" name="password_confirmation" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} placeholder="Confirm password" />
        <button className="rounded bg-black px-4 py-2 text-white" type="submit">Update</button>
      </Form>
    </div>
  );
}
