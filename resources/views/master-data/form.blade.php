@extends('layouts.app')
@php $isEdit = $record !== null; @endphp
@section('title', ($isEdit ? 'Sửa ' : 'Thêm ') . $meta['singular'])
@section('page-title', 'Dữ liệu gốc — ' . $meta['title'])

@section('content')
    @php $hideErrorSummary = true; @endphp
    <div style="max-width:760px">
        <div class="page-header">
            <div>
                <div class="breadcrumb">
                    <span>Dữ liệu gốc</span> {!! icon('chevron-right', '', ['style' => 'width:12px;height:12px']) !!}
                    <a href="{{ route($meta['route'] . '.index') }}">{{ $meta['title'] }}</a>
                </div>
                <h2>{{ $isEdit ? 'Sửa ' . $meta['singular'] . ': ' . $record->name : 'Thêm ' . $meta['singular'] }}</h2>
            </div>
            <a href="{{ route($meta['route'] . '.index') }}" class="btn btn-secondary">← Quay lại</a>
        </div>

        <form method="POST" action="{{ $isEdit ? route($meta['route'] . '.update', $record->id) : route($meta['route'] . '.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="card mb-16">
                <div class="card-body">
                    <div class="form-row cols-3">
                        @foreach ($fields as $name => $field)
                            @php
                                $value = old($name, $record->{$name} ?? ($field['default'] ?? ($field['type'] === 'checkbox' ? true : null)));
                                $span  = $field['span'] ?? 3;
                            @endphp
                            <div class="form-group mb-0" style="grid-column: span {{ $span }}">
                                @if ($field['type'] === 'checkbox')
                                    <label class="form-check">
                                        <input type="hidden" name="{{ $name }}" value="0">
                                        <input type="checkbox" name="{{ $name }}" value="1" @checked($value)>
                                        <span class="fw-600">{{ $field['label'] }}</span>
                                    </label>
                                @else
                                    <label class="form-label {{ ! empty($field['required']) ? 'required' : '' }}" for="f-{{ $name }}">{{ $field['label'] }}</label>
                                    @switch($field['type'])
                                        @case('textarea')
                                            <textarea id="f-{{ $name }}" name="{{ $name }}" class="form-textarea @error($name) is-invalid @enderror" rows="3">{{ $value }}</textarea>
                                        @break
                                        @case('select')
                                            <select id="f-{{ $name }}" name="{{ $name }}" class="form-select @error($name) is-invalid @enderror">
                                                <option value="">— Không chọn —</option>
                                                @foreach ($field['options'] as $optValue => $optLabel)
                                                    <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                                                @endforeach
                                            </select>
                                        @break
                                        @default
                                            <input type="{{ $field['type'] }}" id="f-{{ $name }}" name="{{ $name }}"
                                                class="form-control @error($name) is-invalid @enderror" value="{{ $value }}"
                                                placeholder="{{ $field['placeholder'] ?? '' }}" @required(! empty($field['required']))
                                                @if ($loop->first) autofocus @endif>
                                    @endswitch
                                @endif
                                @if (! empty($field['hint']))
                                    <div class="form-hint">{{ $field['hint'] }}</div>
                                @endif
                                @error($name)<div class="form-error">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">{!! icon('check') !!} Lưu</button>
                <a href="{{ route($meta['route'] . '.index') }}" class="btn btn-secondary">Hủy</a>
            </div>
        </form>
    </div>
@endsection
