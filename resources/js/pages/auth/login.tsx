import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
  status?: string;
};

export default function Login({ status }: Props) {
  return (
    <>
      <Head title="Log in" />

      <Card className="rounded-2xl border bg-card text-card-foreground shadow-sm">
        <CardHeader className="space-y-1 pb-2 text-center">
          <CardTitle className="text-2xl font-semibold tracking-tight">Admin login</CardTitle>
          <CardDescription>Authorized MayWrites administrators only</CardDescription>
        </CardHeader>
        <CardContent className="space-y-6 pt-2">
          {status ? (
            <div className="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-center text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">
              {status}
            </div>
          ) : null}

          <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-6">
            {({ processing, errors }) => (
              <>
                <div className="grid gap-6">
                  <div className="grid gap-2">
                    <Label htmlFor="email">Email address</Label>
                    <Input
                      id="email"
                      type="email"
                      name="email"
                      required
                      autoFocus
                      tabIndex={1}
                      autoComplete="email"
                      placeholder="email@example.com"
                    />
                    <InputError message={errors.email} />
                  </div>

                  <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <PasswordInput
                      id="password"
                      name="password"
                      required
                      tabIndex={2}
                      autoComplete="current-password"
                      placeholder="Password"
                    />
                    <InputError message={errors.password} />
                  </div>

                  <div className="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" tabIndex={3} />
                    <Label htmlFor="remember">Remember me</Label>
                  </div>

                  <Button type="submit" className="w-full" tabIndex={4} disabled={processing} data-test="login-button">
                    {processing && <Spinner />}
                    Log in
                  </Button>
                </div>
                <p className="text-center text-sm text-muted-foreground">
                  This area is restricted to authorized administrators.
                </p>
              </>
            )}
          </Form>
        </CardContent>
      </Card>
    </>
  );
}

Login.layout = {
  title: 'Admin Login',
  description: 'MayWrites.co Admin Panel',
};
