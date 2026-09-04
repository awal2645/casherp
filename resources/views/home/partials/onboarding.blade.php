<div class="tw-px-5 tw-mt-5">
    <div class="tw-bg-white tw-rounded-xl tw-shadow-sm tw-ring-1 tw-ring-gray-200 tw-overflow-hidden">
        <div class="tw-p-4 sm:tw-p-5">
            <div class="tw-flex tw-flex-col sm:tw-flex-row sm:tw-items-center sm:tw-justify-between tw-gap-3">
                <div>
                    <h2 class="tw-text-lg tw-font-semibold tw-text-gray-900 tw-m-0">Finish setting up {{ session('business.name') }}</h2>
                    <p class="tw-text-sm tw-text-gray-500 tw-mt-1 tw-mb-0">
                        {{ $onboarding['industry'] }} workspace - every result below is calculated from your company data.
                    </p>
                </div>
                <div class="tw-text-left sm:tw-text-right">
                    <span class="tw-text-sm tw-font-semibold tw-text-gray-700">{{ $onboarding['percentage'] }}% complete</span>
                </div>
            </div>

            <div class="tw-w-full tw-bg-gray-200 tw-rounded-full tw-h-2 tw-mt-4" role="progressbar"
                aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $onboarding['percentage'] }}">
                <div class="tw-bg-primary-600 tw-h-2 tw-rounded-full tw-transition-all tw-duration-300"
                    style="width: {{ $onboarding['percentage'] }}%"></div>
            </div>

            @if(!empty($onboarding['profile']))
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-mt-4" aria-label="Company operating profile">
                    @foreach($onboarding['profile'] as $profileItem)
                        <span class="tw-inline-flex tw-items-center tw-rounded-full tw-bg-gray-100 tw-px-3 tw-py-1 tw-text-xs tw-text-gray-700">
                            <strong>{{ $profileItem['label'] }}:</strong>&nbsp;{{ $profileItem['value'] }}
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 xl:tw-grid-cols-4 tw-gap-3 tw-mt-4">
                @foreach($onboarding['steps'] as $step)
                    <a href="{{ $step['url'] }}"
                        class="tw-block tw-p-3 tw-rounded-lg tw-border tw-transition-all tw-duration-200 hover:tw-shadow-sm hover:tw-border-primary-300 {{ $step['complete'] ? 'tw-bg-green-50 tw-border-green-200' : 'tw-bg-white tw-border-gray-200' }}">
                        <div class="tw-flex tw-items-start tw-gap-2">
                            <i class="fa {{ $step['complete'] ? 'fa-check-circle tw-text-green-600' : 'fa-circle-o tw-text-gray-400' }} tw-mt-1" aria-hidden="true"></i>
                            <div>
                                <p class="tw-text-sm tw-font-semibold tw-text-gray-900 tw-m-0">{{ $step['label'] }}</p>
                                <p class="tw-text-xs tw-text-gray-500 tw-mt-1 tw-mb-0">{{ $step['help'] }}</p>
                                @if(!$step['required'])
                                    <span class="tw-text-xs tw-text-gray-400">Optional</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
