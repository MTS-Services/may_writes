import { Form, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { LayoutGrid } from 'lucide-react';

type TrelloSettingsProps = {
  settings: {
    template_board_id: string | null;
    background_id: string | null;
  };
  resolved: {
    template_board_id: string | null;
    background_id: string | null;
  };
};

export default function AdminTrelloSettingsPage({ settings, resolved }: TrelloSettingsProps) {
  const { errors } = usePage().props as { errors?: Record<string, string> };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="flex items-center gap-3">
        <div className="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <LayoutGrid className="size-5" />
        </div>
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Trello integration</h1>
          <p className="text-sm text-muted-foreground">
            Board copy and background IDs used when onboarding customers.
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Provisioning settings</CardTitle>
          <CardDescription>
            Leave template board ID empty to build boards from application config only (recommended for
            production). If copy fails at runtime, onboarding falls back to config-only automatically.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Form action="/admin/trello" method="patch" className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="template_board_id">Template board ID</Label>
              <Input
                id="template_board_id"
                name="template_board_id"
                defaultValue={settings.template_board_id ?? ''}
                placeholder="Optional Trello board id"
              />
              {errors?.template_board_id && (
                <p className="text-sm text-destructive">{errors.template_board_id}</p>
              )}
              {resolved.template_board_id && resolved.template_board_id !== settings.template_board_id && (
                <p className="text-xs text-muted-foreground">
                  Effective value (includes env fallback): {resolved.template_board_id}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="background_id">Board background ID</Label>
              <Input
                id="background_id"
                name="background_id"
                defaultValue={settings.background_id ?? ''}
                placeholder="Optional Trello background id"
              />
              {errors?.background_id && (
                <p className="text-sm text-destructive">{errors.background_id}</p>
              )}
              {resolved.background_id && resolved.background_id !== settings.background_id && (
                <p className="text-xs text-muted-foreground">
                  Effective value (includes env fallback): {resolved.background_id}
                </p>
              )}
            </div>

            <button
              type="submit"
              className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
              Save settings
            </button>
          </Form>
        </CardContent>
      </Card>
    </div>
  );
}
