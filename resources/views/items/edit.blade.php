@extends('layouts.app')

@section('title', 'Edit Post')

@section('konten')
<div style="max-width: 760px; margin: 0 auto;">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}">Posts</a>
        <span>/</span>
        <a href="{{ route('items.show', $item) }}">{{ $item->title }}</a>
        <span>/</span>
        <span>Edit</span>
    </nav>

    <div class="card-surface">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px;">
            <div>
                <h1 class="page-title" style="font-size: 22px;">Edit Post</h1>
                <p class="caption" style="margin: 4px 0 0;">Update post details before any claim is submitted.</p>
            </div>
            <a href="{{ route('items.show', $item) }}" class="btn btn-outline btn-sm">&larr; Back</a>
        </div>

        <form action="{{ route('items.update', $item) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" required>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) old('category_id', $item->category_id) === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="event_date">Event Date</label>
                    <input id="event_date" type="date" name="event_date" value="{{ old('event_date', $item->event_date->format('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Item Title</label>
                <input id="title" type="text" name="title" value="{{ old('title', $item->title) }}" required>
            </div>

            <div class="form-group">
                <label for="description">Public Description</label>
                <textarea id="description" name="description" rows="4" maxlength="1500" data-char-counter required>{{ old('description', $item->description) }}</textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label for="building">Campus Building</label>
                    <select id="building" name="building" required>
                        @foreach($buildings as $building)
                            <option value="{{ $building }}" {{ old('building', $item->building) === $building ? 'selected' : '' }}>
                                {{ $building }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label for="floor_detail">Floor / Room Detail</label>
                    <input id="floor_detail" type="text" name="floor_detail" value="{{ old('floor_detail', $item->floor_detail) }}" required>
                </div>
            </div>

            @if($item->type === 'found')
                <div class="form-group">
                    <label for="deposit_note">Deposit Note</label>
                    <textarea id="deposit_note" name="deposit_note" rows="2">{{ old('deposit_note', $item->deposit_note) }}</textarea>
                </div>

                <div class="form-group">
                    <label for="hidden_detail">Hidden Distinguishing Detail (Security Only)</label>
                    <textarea id="hidden_detail" name="hidden_detail" rows="2">{{ old('hidden_detail', $item->hidden_detail) }}</textarea>
                </div>
            @endif

            <div class="form-group">
                <label for="photo">Replace Photo (Optional, Max 2MB)</label>
                <div class="file-dropzone" data-file-dropzone>
                    <input id="photo" type="file" name="photo" accept="image/*" data-max-kb="2048">
                    <div class="file-meta-row" data-file-meta>
                        <span data-file-name></span>
                        <button type="button" class="btn btn-outline btn-sm" data-file-clear>Remove</button>
                    </div>
                    <div data-file-preview style="display: none; margin-top: 12px;">
                        <img data-file-img src="#" alt="Photo preview" style="max-height: 200px; border-radius: 8px; border: 1px solid var(--border-color);">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="tags">Hashtags (1 to 5 tags, comma-separated)</label>
                <input id="tags" type="text" name="tags" value="{{ old('tags', $tagsString) }}" data-tags-input data-tags-preview="#tag-preview" required>
                <div id="tag-preview" style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px;"></div>
            </div>

            <div class="form-group callout-info" style="padding: 12px 14px; border-radius: 10px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-weight: 400; margin: 0;">
                    <input type="checkbox" name="is_sensitive" value="1" {{ old('is_sensitive', $item->is_sensitive) ? 'checked' : '' }} style="width: auto; margin-top: 3px;">
                    <span><strong>Sensitive Item:</strong> Blur photo in public feeds to protect identity details.</span>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <a href="{{ route('items.show', $item) }}" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
