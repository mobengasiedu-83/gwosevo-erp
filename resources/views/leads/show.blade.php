<x-layouts.app>
    <div class="mb-6 flex flex-wrap justify-between gap-3">
        <div>
            <p class="text-sm text-slate-500">{{ $lead->reference }} · {{ ucfirst($lead->status) }}</p>
            <h1 class="text-3xl font-bold">{{ $lead->full_name }}</h1>
            <p class="text-slate-600">{{ $lead->company_name }} · {{ $lead->email }} · {{ $lead->phone }}</p>
        </div>
        @can('update', $lead)
            <a class="rounded border px-4 py-2" href="{{ route('leads.edit', $lead) }}">Edit</a>
        @endcan
    </div>
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="space-y-5 lg:col-span-2">
            <div class="rounded bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-semibold">Lead profile</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-500">Source</dt><dd>{{ ucfirst(str_replace('_', ' ', $lead->source)) }}</dd></div>
                    <div><dt class="text-slate-500">Owner</dt><dd>{{ $lead->owner?->name ?: 'Unassigned' }}</dd></div>
                    <div><dt class="text-slate-500">Industry</dt><dd>{{ $lead->industry ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Score</dt><dd>{{ $lead->score }}/100</dd></div>
                    @foreach($customFields as $field)<div><dt class="text-slate-500">{{ $field->label }}</dt><dd>{{ $lead->custom_values[$field->key] ?? '—' }}</dd></div>@endforeach
                    <div><dt class="text-slate-500">Next follow-up</dt><dd>{{ $erpSettings->formatDate($lead->next_follow_up_at,true) }}</dd></div>
                </dl>
            </div>
            <div class="rounded bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-semibold">Activity timeline</h2>
                @forelse ($lead->activities as $activity)
                    <div class="border-b py-3"><strong>{{ ucfirst($activity->type) }}: {{ $activity->subject }}</strong><p class="text-sm text-slate-600">{{ $activity->body }}</p></div>
                @empty
                    <p class="text-slate-500">No activities recorded.</p>
                @endforelse
                @can('update', $lead)
                    <form method="POST" action="{{ route('leads.activities.store', $lead) }}" class="mt-4 grid gap-2">
                        @csrf
                        <select name="type" class="rounded border-slate-300"><option value="note">Note</option><option value="call">Call</option><option value="meeting">Meeting</option><option value="task">Task</option></select>
                        <input class="rounded border-slate-300" name="subject" placeholder="Activity subject" required>
                        <textarea class="rounded border-slate-300" name="body" placeholder="Details"></textarea>
                        <button class="w-fit rounded bg-slate-800 px-3 py-2 text-white">Add activity</button>
                    </form>
                @endcan
            </div>
        </section>
        <aside class="space-y-5">
            @can('assign', $lead)
                <div class="rounded bg-white p-5 shadow-sm">
                    <h2 class="mb-3 font-semibold">Assign</h2>
                    <form method="POST" action="{{ route('leads.assign', $lead) }}">
                        @csrf
                        <select name="owner_id" class="w-full rounded border-slate-300">
                            @foreach ($owners as $owner)
                                <option value="{{ $owner->id }}" @selected($lead->owner_id === $owner->id)>{{ $owner->name }}</option>
                            @endforeach
                        </select>
                        <button class="mt-2 rounded bg-slate-800 px-3 py-2 text-white">Update owner</button>
                    </form>
                </div>
            @endcan
            @if (! in_array($lead->status, ['converted', 'disqualified'], true))
                @can('qualify', $lead)
                    <div class="rounded bg-white p-5 shadow-sm">
                        <h2 class="mb-3 font-semibold">Qualify lead</h2>
                        <form method="POST" action="{{ route('leads.qualify', $lead) }}" class="space-y-2">
                            @csrf
                            <input class="w-full rounded border-slate-300" name="budget" type="number" step="0.01" placeholder="Budget ({{ $erpSettings->get('currency') }})">
                            <input class="w-full rounded border-slate-300" name="authority" placeholder="Decision authority">
                            <textarea class="w-full rounded border-slate-300" name="need" placeholder="Business need"></textarea>
                            <input class="w-full rounded border-slate-300" name="timeline" placeholder="Timeline">
                            <input class="w-full rounded border-slate-300" name="score" type="number" min="0" max="100" value="{{ $lead->score }}">
                            <button class="rounded bg-indigo-600 px-3 py-2 text-white">Mark qualified</button>
                        </form>
                    </div>
                @endcan
            @endif
            @if ($lead->status === 'qualified')
                @can('convert', $lead)
                    <form method="POST" action="{{ route('leads.convert', $lead) }}">@csrf<button class="w-full rounded bg-emerald-600 px-3 py-2 font-semibold text-white">Convert to opportunity</button></form>
                @endcan
            @endif
        </aside>
    </div>
</x-layouts.app>