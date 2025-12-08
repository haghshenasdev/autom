<div class="space-y-6">
    @foreach($events as $event)
        <div class="flex items-start space-x-3">
            <!-- آیکون -->
            <x-filament::icon
                :icon="$event['icon']"
                class="w-6 h-6 text-{{ $event['color'] }}-500"
            />

            <!-- متن -->
            <div class="flex-1">
                <div class="flex justify-between">
                    <span class="font-bold text-{{ $event['color'] }}-600">
                        {{ $event['title'] }}
                    </span>
                    <span class="text-sm text-gray-400">
                        {{ \Morilog\Jalali\Jalalian::fromCarbon($event['created_at'])->format('Y/m/d H:i') }}
                    </span>
                </div>
                @if($event['type'] === 'activity' or $event['type'] === 'referral_activity')
                    <p class="text-sm text-gray-500">
                        توسط: <strong>{{ $event['user'] }}</strong>
                    </p>

                    @if($event['event'] === 'updated' or $event['type'] === 'referral_activity')
                        @foreach($event['changes'] as $field => $change)
                            <p>
                                فیلد <strong>{{ $change['label'] }}</strong>
                                از <span class="text-red-500">"{{ $change['old'] }}"</span>
                                به <span class="text-green-500">"{{ $change['new'] }}"</span> تغییر کرد.
                            </p>
                        @endforeach
                    @endif
                    @else
                    <p class="text-gray-700 mt-1">
                        {{ $event['description'] }}
                    </p>

                @endif
            </div>
        </div>
    @endforeach
</div>
