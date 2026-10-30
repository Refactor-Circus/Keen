@props([
    // The package key whose history to show, such as `showroom`.
    'source' => null,
    // One record's history; omit for the whole package's.
    'subject' => null,
    // Everything scoped to this model, such as an organization.
    'scope' => null,
    // Only this action, or a prefix ending in a dot: `feature.`.
    'action' => null,
    'limit' => 20,
    'title' => null,
])

{{--
    A package's own history, read through refactor-circus/keystone's
    AuditTrail. Renders nothing while the package turns its auditing off, or
    for those its history endpoint would refuse, so views can place it
    unconditionally:

        <x-keen::audit-trail source="showroom" :subject="$product" />
--}}

@php
    $trail = app(\RefactorCircus\Keystone\Audit\Contracts\AuditTrail::class);
    $package = $source === null ? null : app(\RefactorCircus\Keystone\Packages\PackageRegistry::class)->find($source);

    // Shown only to those the package's history endpoint would answer.
    $allowed = $package === null || app(\RefactorCircus\Keystone\Audit\History::class)->allows($package, auth()->user(), array_filter([
        'subject_type' => $subject?->getMorphClass(),
        'subject_id' => $subject === null ? null : (string) $subject->getKey(),
    ]));

    // Only while the package audits (its `audit.enabled` on).
    $page = $allowed && ($package === null ? $trail->available() : $package->audits())
        ? $trail->entries(\RefactorCircus\Keystone\Audit\Data\AuditFilter::make()->source($source)->subject($subject)->scope($scope)->action($action)->limit((int) $limit))
        : null;

    // The package's history page, while Keen's dashboard serves it.
    $history = \Illuminate\Support\Facades\Route::has('atrium.keen.history.show');
@endphp

@if ($page !== null)
    <x-atrium::card :title="$title ?? __('keen::keen.history')" :padded="false" {{ $attributes->merge(['data-testid' => 'audit-trail']) }}>
        <x-keen::audit.entries :entries="$page->entries" :without-subject="$subject !== null" />

        {{-- The package's own audit log, filtered to what this panel shows. --}}
        @if ($page->nextCursor !== null && $package !== null && $history)
            <x-slot:footer>
                <a class="text-sm underline-offset-2 hover:underline" data-testid="audit-trail-all" href="{{ route('atrium.keen.history.show', [$package->key, ...array_filter(['action' => $action, 'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey()])]) }}">{{ __('keen::keen.view_all') }}</a>
            </x-slot:footer>
        @endif
    </x-atrium::card>
@endif
