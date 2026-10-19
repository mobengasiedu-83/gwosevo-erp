@can('calls.view')
<section class="mt-5 rounded bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold">VitalPBX extensions</h2>
            <p class="text-sm text-slate-500">Read-only directory imported from the configured PBX.</p>
        </div>
    </div>

    @forelse($connections as $connection)
        @php($directory = data_get($connection->settings, 'extensions_directory', []))
        <div class="border-t py-4 first:border-0 first:pt-0">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-medium">{{ $connection->name }}</h3>
                    @if(data_get($connection->settings, 'extensions_synced_at'))
                        <p class="text-xs text-slate-500">Last synced {{ \Illuminate\Support\Carbon::parse(data_get($connection->settings, 'extensions_synced_at'))->format('Y-m-d H:i') }}</p>
                    @else
                        <p class="text-xs text-slate-500">Not synced yet</p>
                    @endif
                    @if(data_get($connection->settings, 'calls_synced_at'))
                        <p class="text-xs text-slate-500">Call history last synced {{ \Illuminate\Support\Carbon::parse(data_get($connection->settings, 'calls_synced_at'))->format('Y-m-d H:i') }}</p>
                    @endif
                </div>
                @can('calls.configure')
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('contact-centre.connections.extensions.sync', $connection) }}">
                            @csrf
                            <button class="workspace-action workspace-action--compact">Sync extensions</button>
                        </form>
                        <form method="POST" action="{{ route('contact-centre.connections.calls.sync', $connection) }}">
                            @csrf
                            <label class="sr-only" for="call-history-lookback-{{ $connection->id }}">Call history lookback in days</label>
                            <input id="call-history-lookback-{{ $connection->id }}" name="lookback_days" type="number" min="1" max="365" value="{{ config('vitalpbx.cdr_initial_lookback_days', 7) }}" class="w-24 rounded border border-slate-300 px-2 py-1.5 text-sm" title="Days of call history to sync">
                            <button class="workspace-action workspace-action--compact">Sync call history</button>
                        </form>
                    </div>
                @endcan
            </div>

            @if(count($directory))
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-2">Extension</th>
                                <th class="p-2">Name</th>
                                <th class="p-2">Technology</th>
                                <th class="p-2">Registration status</th>
                                <th class="p-2">Mapped ERP user</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($directory as $extension)
                                @php($mapping = $connection->extensions->firstWhere('extension', $extension['extension'] ?? null))
                                <tr class="border-t">
                                    <td class="p-2 font-medium">{{ $extension['extension'] ?? '—' }}</td>
                                    <td class="p-2">{{ $extension['name'] ?: '—' }}</td>
                                    <td class="p-2">{{ $extension['technology'] ?: '—' }}</td>
                                    <td class="p-2">{{ $extension['status'] ?: '—' }}</td>
                                    <td class="p-2">{{ $mapping?->user?->name ?? 'Not mapped' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-slate-500">No extension directory has been synced for this profile.</p>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-500">Create a PBX profile, then select <strong>Sync extensions</strong> to import the directory.</p>
    @endforelse
</section>
@endcan
