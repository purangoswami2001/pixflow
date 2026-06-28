@php
    $isEdit = isset($campaign) && $campaign;
    $c = $campaign;
@endphp
<x-app-layout>
    <div class="pp-automation-page pp-campaign-form-page">
        <x-pp-page-header :title="$isEdit ? 'Edit campaign' : 'New campaign'" :description="$isEdit ? 'Update schedule, channels, image templates, and daily volume.' : 'Set category, channels, creative templates, and automation schedule.'">
            <x-slot name="breadcrumb">
                <a href="{{ route('admin.automation.campaigns') }}" class="hover:text-[#004ac6]">Campaigns</a>
                <span>/</span>
                <span class="text-[#004ac6]">{{ $isEdit ? 'Edit' : 'Create' }}</span>
            </x-slot>
        </x-pp-page-header>
        @include('admin.automation._nav')

        <form id="campaign-form"
              action="{{ $isEdit ? route('admin.automation.campaigns.update', $campaign) : route('admin.automation.campaigns.store') }}"
              method="POST"
              class="pp-campaign-form">
            @csrf
            @if($isEdit) @method('PUT') @endif

            <div class="pp-card pp-campaign-form-card">
                <div class="pp-campaign-form-card-head">
                    <div class="pp-campaign-form-card-icon">
                        <span class="material-symbols-outlined">campaign</span>
                    </div>
                    <div>
                        <h3 class="pp-campaign-form-card-title">{{ $isEdit ? 'Campaign settings' : 'Create campaign' }}</h3>
                        <p class="pp-campaign-form-card-desc">Posts are saved as pending approval — nothing publishes automatically.</p>
                    </div>
                </div>

                <div class="pp-campaign-form-card-body">
                    @include('admin.automation._campaign-fields')
                </div>

                <div class="pp-campaign-form-footer">
                    <div class="pp-campaign-form-footer-left">
                        <button type="submit" class="pp-btn-primary py-2.5 px-8">
                            {{ $isEdit ? 'Save changes' : 'Create campaign' }}
                        </button>
                        <a href="{{ route('admin.automation.campaigns') }}" class="pp-btn-secondary py-2.5 px-6">Cancel</a>
                    </div>
                    @if($isEdit)
                        <button type="submit"
                                form="campaign-run-form"
                                class="pp-btn-secondary text-sm py-2.5 px-5 inline-flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px]">play_arrow</span>
                            Run now — queue generation immediately
                        </button>
                    @endif
                </div>
            </div>
        </form>

        @if($isEdit)
            <form id="campaign-run-form" action="{{ route('admin.automation.campaigns.run', $campaign) }}" method="POST" class="hidden">
                @csrf
            </form>
        @endif
    </div>
</x-app-layout>
