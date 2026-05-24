<x-mail::message>
# New writing request submitted

**Customer:** {{ $customer->name }} ({{ $customer->email }})

**Request:** {{ $task->title }}

**Version:** {{ $version->version_number }} (submitted {{ $version->submitted_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'just now' }})

<x-mail::button :url="$adminUrl">
View writing requests
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
