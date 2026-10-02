@props(['historial'])

@if ($historial->isNotEmpty())
    <details class="mt-1 text-xs text-ink-faint">
        <summary class="cursor-pointer select-none">Historial de cambios ({{ $historial->count() }})</summary>
        <ul class="mt-2 space-y-1">
            @foreach ($historial as $entrada)
                <li>
                    {{ $entrada->old_values['celular'] ?? '—' }} → {{ $entrada->new_values['celular'] ?? '—' }}
                    · {{ $entrada->created_at?->format('d/m/Y H:i') }}
                    @if ($entrada->user)
                        · {{ $entrada->user->name }}
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
@endif
