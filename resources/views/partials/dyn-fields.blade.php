{{--
  Phase 4 — per-type dynamic fields, rendered by the server for the
  transaction create/edit forms. forms.js toggles the group matching the
  selected type and posts values as JSON (form_data); the server revalidates
  everything through TypeConfigService.
--}}
@foreach($itqan['trxTypes'] ?? [] as $t)
  @if($t['fields'] !== [])
  <div class="form-section-title dyn-fields-title" data-type="{{ $t['slug'] }}" hidden>{{ __('بيانات إضافية') }} — {{ $t['label'] }}</div>
  <div class="form-grid dyn-fields" data-type="{{ $t['slug'] }}" hidden>
    @foreach($t['fields'] as $f)
    <div class="field {{ in_array($f['type'], ['textarea', 'file'], true) ? 'field-full' : '' }}">
      <label for="df-{{ $f['id'] }}">{{ $f['label'] }}@if($f['required']) <span class="req-star">*</span>@endif</label>
      @switch($f['type'])
        @case('textarea')
          <textarea id="df-{{ $f['id'] }}" data-fid="{{ $f['id'] }}" @if($f['required']) data-req="1" @endif></textarea>
          @break
        @case('number')
          <input type="number" id="df-{{ $f['id'] }}" data-fid="{{ $f['id'] }}" @if($f['required']) data-req="1" @endif @if($f['min'] !== null) min="{{ $f['min'] }}" @endif @if($f['max'] !== null) max="{{ $f['max'] }}" @endif>
          @break
        @case('date')
          <input type="date" id="df-{{ $f['id'] }}" data-fid="{{ $f['id'] }}" @if($f['required']) data-req="1" @endif>
          @break
        @case('select')
          <select id="df-{{ $f['id'] }}" data-fid="{{ $f['id'] }}" @if($f['required']) data-req="1" @endif>
            <option value="">{{ __('اختر...') }}</option>
            @foreach($f['options'] as $opt)
            <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
          </select>
          @break
        @case('checkbox')
          <label class="chk-wrap"><input type="checkbox" id="df-{{ $f['id'] }}" data-fid="{{ $f['id'] }}" @if($f['required']) data-req="1" @endif> <span>{{ $f['label'] }}</span></label>
          @break
        @case('file')
          {{-- file-type fields are satisfied through the main dropzone --}}
          <p class="dyn-file-hint">{{ __('يُرفق ضمن ملفات المعاملة') }}</p>
          @break
        @default
          <input type="text" id="df-{{ $f['id'] }}" data-fid="{{ $f['id'] }}" @if($f['required']) data-req="1" @endif>
      @endswitch
    </div>
    @endforeach
  </div>
  @endif
@endforeach
