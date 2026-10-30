{{-- A test tab or section: one marker per render, naming the slot it was
     registered in and the record it received. --}}
<span data-testid="probe-{{ $probe }}" data-record="{{ $record?->getKey() }}" data-record-class="{{ $record === null ? '' : $record::class }}"></span>
