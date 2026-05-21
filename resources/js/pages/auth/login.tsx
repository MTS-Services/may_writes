import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { store } from '@/routes/login';

type Props = {
  status?: string;
};

// Data structure for the integrated mock dashboard layout
const columns = [
  {
    title: 'To Do',
    cards: [
      { title: 'SEO blog post: remote work tools', tag: 'Blog', color: 'primary' },
      { title: 'LinkedIn thought leadership post', tag: 'LinkedIn', color: 'blue' },
    ],
  },
  {
    title: 'In Progress',
    cards: [{ title: 'Q4 newsletter: product updates', tag: 'Newsletter', color: 'primary' }],
  },
  {
    title: 'Done',
    cards: [
      { title: 'Homepage copy rewrite', tag: 'Approved', color: 'green' },
      { title: 'Press release: new launch', tag: 'Approved', color: 'green' },
    ],
  },
];

export default function Login({ status }: Props) {
  return (
    <>
      <Head title="Log in" />

      <div className="relative min-h-screen grid lg:grid-cols-2 overflow-hidden">
        
        {/* Left Side: Modern Interactive Branding & Mock Dashboard Frame */}
        <div className="relative hidden lg:flex flex-col justify-between bg-zinc-950 p-10 text-white dark:border-r dark:border-zinc-800">
          <div className="absolute inset-0 bg-linear-to-b from-zinc-950/40 to-zinc-950" />
          
          {/* Subtle grid backdrop */}
          <div className="absolute inset-0 bg-[linear-gradient(to_right,#80808008_1px,transparent_1px),linear-gradient(to_bottom,#80808008_1px,transparent_1px)] bg-[size:32px_32px]" />
          
          {/* Header branding */}
          <div className="relative z-20 flex items-center gap-2 text-lg font-medium">
            <span className="h-6 w-6 rounded-md bg-white text-zinc-950 flex items-center justify-center font-bold text-sm">
              M
            </span>
            MayWrites
          </div>

          {/* Central Section: Embedded & Stylized Dashboard Element */}
          <div className="relative z-20 my-auto flex items-center justify-center pt-8 [perspective:2000px]">
            <div className="w-full max-w-[540px] transform-gpu rotate-y-[12deg] rotate-x-[6deg] -rotate-z-[2deg] transition-transform duration-700 hover:rotate-y-[4deg] hover:rotate-x-[2deg] hover:rotate-z-0">
              {/* Fade out mask effect toward the bottom to blend with layout */}
              <div className="absolute -inset-px rounded-[19px] bg-linear-to-b from-white/10 via-transparent to-transparent pointer-events-none" />
              <div className="[mask-image:linear-gradient(to_bottom,white_85%,transparent_100%)]">
                <MockDashboardFrame />
              </div>
            </div>
          </div>

          {/* Footer Quote */}
          <div className="relative z-20">
            <blockquote className="space-y-1">
              <p className="text-base font-light text-zinc-400">
                &ldquo;Great systems don't just manage content; they streamline thought.&rdquo;
              </p>
              <footer className="text-xs font-medium text-zinc-500">MayWrites Core Engine</footer>
            </blockquote>
          </div>
        </div>

        {/* Right Side: Simple & Modular Form Column */}
        <div className="flex flex-col justify-center items-center p-6 sm:p-10 bg-background">
          <div className="w-full max-w-sm sm:max-w-[360px] space-y-6">
            
            <div className="flex flex-col space-y-2 text-center lg:text-left">
              <h1 className="text-3xl font-semibold tracking-tight text-foreground">
                Welcome back
              </h1>
              <p className="text-sm text-muted-foreground">
                Authorized MayWrites administrators only
              </p>
            </div>

            {status && (
              <div className="rounded-lg border border-emerald-200 bg-emerald-50/50 px-3 py-2.5 text-center text-sm font-medium text-emerald-800 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400">
                {status}
              </div>
            )}

            <Form {...store.form()} resetOnSuccess={['password']} className="space-y-5">
              {({ processing, errors }) => (
                <>
                  <div className="space-y-4">
                    <div className="space-y-1.5">
                      <Label htmlFor="email" className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                        Email address
                      </Label>
                      <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        placeholder="name@maywrites.co"
                        className="h-11 px-3.5 focus-visible:ring-1 focus-visible:ring-ring"
                      />
                      <InputError message={errors.email} />
                    </div>

                    <div className="space-y-1.5">
                      <Label htmlFor="password" className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                        Password
                      </Label>
                      <PasswordInput
                        id="password"
                        name="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        placeholder="••••••••"
                        className="h-11 px-3.5 focus-visible:ring-1 focus-visible:ring-ring"
                      />
                      <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center space-x-2.5 pt-1">
                      <Checkbox id="remember" name="remember" tabIndex={3} className="rounded-[4px]" />
                      <Label htmlFor="remember" className="text-sm font-normal text-muted-foreground cursor-pointer select-none">
                        Keep me logged in
                      </Label>
                    </div>
                  </div>

                  <Button 
                    type="submit" 
                    className="w-full h-11 text-sm font-medium cursor-pointer shadow-xs transition-colors" 
                    tabIndex={4} 
                    disabled={processing} 
                    data-test="login-button"
                  >
                    {processing ? <Spinner className="mr-2" /> : null}
                    Sign in to Dashboard
                  </Button>

                  <p className="text-center text-xs text-muted-foreground/80 pt-2">
                    Secured admin area. Activity is actively logged.
                  </p>
                </>
              )}
            </Form>
          </div>
        </div>
      </div>
    </>
  );
}

// Inlined explicit component optimized for the dark background canvas context
function MockDashboardFrame() {
  return (
    <Card className="overflow-hidden rounded-[18px] border-zinc-800 bg-zinc-900 text-zinc-100 shadow-2xl shadow-black/80">
      <div className="flex items-center gap-2 border-b border-zinc-800 bg-zinc-900/50 px-4 py-3">
        <span className="size-2.5 rounded-full bg-[#ff5f57]/80" />
        <span className="size-2.5 rounded-full bg-[#febc2e]/80" />
        <span className="size-2.5 rounded-full bg-[#28c840]/80" />
        <div className="mx-2 flex-1 rounded-md border border-zinc-800 bg-zinc-950 px-3 py-1 text-[11px] text-zinc-500 font-mono">
          trello.com/b/sarah-writes
        </div>
      </div>

      <div className="p-4 sm:p-[18px]">
        <div className="mb-3.5 flex items-center justify-between">
          <span className="text-[13px] font-bold text-zinc-200">Sarah&apos;s Writing Board</span>
          <Badge className="bg-zinc-800 text-[10px] tracking-[0.04em] text-zinc-300 border-zinc-700 hover:bg-zinc-800">
            Pro Plan
          </Badge>
        </div>

        <div className="grid grid-cols-3 gap-2">
          {columns.map((column) => (
            <div key={column.title} className="rounded-lg bg-zinc-950/60 p-2 border border-zinc-900">
              <div className="mb-2 text-[9px] font-bold tracking-[0.06em] text-zinc-500 uppercase">
                {column.title}
              </div>
              <div className="space-y-1.5">
                {column.cards.map((card) => (
                  <div key={card.title} className="rounded-md border border-zinc-800/80 bg-zinc-900 p-2">
                    <div className="mb-1.5 text-[11px] leading-snug font-medium text-zinc-300">{card.title}</div>
                    <span
                      className={cn(
                        'inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold tracking-wide uppercase',
                        card.color === 'primary' && 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
                        card.color === 'green' && 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
                        card.color === 'blue' && 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
                      )}
                    >
                      {card.tag}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>

        <div className="mt-3.5 flex gap-1.5">
          {[
            ['12', 'Completed'],
            ['3', 'In queue'],
            ['Unlimited', 'Requests left'],
          ].map(([value, label]) => (
            <div key={label} className="flex-1 rounded-lg bg-zinc-950/40 p-2.5 border border-zinc-900">
              <div className="text-xl leading-none font-semibold text-zinc-200">{value}</div>
              <div className="mt-1 text-[9px] font-medium text-zinc-500 uppercase tracking-wider">{label}</div>
            </div>
          ))}
        </div>
      </div>
    </Card>
  );
}

Login.layout = {
  title: 'Admin Login',
  description: 'MayWrites.co Admin Panel',
};