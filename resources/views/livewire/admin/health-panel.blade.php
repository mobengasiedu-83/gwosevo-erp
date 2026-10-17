<section class="panel"><h2>System health</h2><p class="help">Checks run when this screen loads or refreshes. Scheduler and worker heartbeats are current for five minutes.</p>
@if(session('probe_message'))<p class="notice" role="status">{{ session('probe_message') }}</p>@endif
<div class="table-scroll"><table class="admin-table"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>
@foreach($checks as $label=>$check)<tr><td>{{ $label }}</td><td><span class="status {{ $check['ok'] ? 'good' : 'warn' }}">{{ $check['ok'] ? 'Healthy' : 'Needs attention' }}</span></td><td>{{ $check['message'] }}</td></tr>@endforeach
</tbody></table></div><div class="row-actions"><button class="button" wire:click="probe" wire:loading.attr="disabled">Send queue probe</button><button class="button secondary" wire:click="$refresh" wire:loading.attr="disabled">Refresh checks</button><span wire:loading role="status">Checking…</span></div></section>
