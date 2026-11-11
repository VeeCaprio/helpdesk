<x-filament-panels::page>
    @php
        $summary = $this->summary();
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($summary as $stat)
            <x-filament::section>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $stat['label'] }}
                        </p>
                        <p class="mt-1 text-3xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ number_format($stat['value']) }}
                        </p>
                        <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">
                            {{ $stat['description'] }}
                        </p>
                    </div>

                    <span class="rounded-lg bg-primary-50 p-2 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon :icon="$stat['icon']" class="h-6 w-6" />
                    </span>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
