<x-filament-panels::page>
    <div class="flex gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-filament::icon
            icon="heroicon-o-information-circle"
            class="mt-0.5 h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
        />

        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                How performance is measured
            </p>
            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">
                Assigned tickets are all tickets currently owned by each staff member. Resolution rate compares completed tickets (resolved or closed) with all assigned tickets. Average resolution time is measured from ticket creation to resolution.
            </p>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
