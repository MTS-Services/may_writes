<x-mail::message>
<div style="background:#F8F7F4;padding:24px;">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;">
        <h1 style="margin:0 0 16px;color:#0D0D0B;font-size:28px;font-weight:700;">
            MayWrites <span style="color:#E8572A;">Welcome</span>
        </h1>
        <p style="margin:0 0 12px;color:#1E1D1A;">Hi {{ $customer->name }},</p>
        <p style="margin:0 0 16px;color:#1E1D1A;">
            Welcome to MayWrites! We're thrilled to have you on the {{ $customer->plan?->name ?? 'selected' }} plan.
        </p>
        <p style="margin:0 0 8px;color:#1E1D1A;font-weight:600;">What happens next:</p>
        <ol style="margin:0 0 20px 20px;padding:0;color:#44413A;">
            <li>Your personal Trello board is being created right now.</li>
            <li>You'll receive a Trello invitation email within the next few minutes.</li>
            <li>Accept the invitation, log into Trello, and start submitting your writing requests as cards.</li>
        </ol>
        <x-mail::button url="https://trello.com" color="dark">
            Visit Trello
        </x-mail::button>
        <p style="margin:20px 0 0;color:#5F5C52;">Questions? Reply to this email or write to hello@maywrites.co</p>
    </div>
</div>
</x-mail::message>
