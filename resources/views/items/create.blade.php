@extends('layouts.app')

@section('title', 'New Post')

@section('konten')
<div style="max-width: 1120px; margin: 0 auto;">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('dashboard') }}">Posts</a>
        <span>/</span>
        <span>New Post</span>
    </nav>

    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px;">
        <div>
            <h1 class="page-title">Create New Post</h1>
            <p class="caption" style="margin: 4px 0 0;">Provide clear details so campus members and security can identify and match the item.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline btn-sm">&larr; Back to Posts</a>
    </div>

    <div class="alert alert-info" id="found-custody-notice">
        <svg class="alert-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <div class="alert-body">
            Found items must be handed over to a <strong>Security Post or Lobby Desk</strong> before posting.
        </div>
    </div>

    <div class="post-progress-card" data-post-progress>
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 12.5px;">
            <strong style="color: var(--text-dark);">Post Completion</strong>
            <span class="caption" data-post-progress-text style="font-weight: 600; color: var(--primary-navy);">0% ready</span>
        </div>
        <div class="post-progress-track">
            <div class="post-progress-fill" data-post-progress-fill></div>
        </div>
    </div>

    <div class="create-split-grid">
    <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" data-unsaved-guard data-post-create-form style="display: flex; flex-direction: column; gap: 18px;">
        @csrf

        {{-- Section 1: Post Type --}}
        <div class="card-surface">
            <h2 class="section-title" style="margin-bottom: 14px;">1. Post Type</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                <label style="position: relative; cursor: pointer; margin: 0;">
                    <input type="radio" name="type" value="found" class="type-radio" {{ old('type', 'found') === 'found' ? 'checked' : '' }} style="position: absolute; opacity: 0; pointer-events: none;">
                    <div class="type-option-card" data-type="found">
                        <div>
                            <div style="font-weight: 700; color: var(--emerald-found); margin-bottom: 4px;">
                                {{ auth()->user()->isSecurity() ? 'Found Post (On Behalf of Finder)' : 'I Found an Item (Found)' }}
                            </div>
                            <div class="caption">Physical item has been deposited at a UPH Security Post or Lobby.</div>
                        </div>
                    </div>
                </label>

                @if(!auth()->user()->isSecurity())
                <label style="position: relative; cursor: pointer; margin: 0;">
                    <input type="radio" name="type" value="lost" class="type-radio" {{ old('type') === 'lost' ? 'checked' : '' }} style="position: absolute; opacity: 0; pointer-events: none;">
                    <div class="type-option-card" data-type="lost">
                        <div>
                            <div style="font-weight: 700; color: var(--rose-lost); margin-bottom: 4px;">
                                I Lost an Item (Lost)
                            </div>
                            <div class="caption">Publish a public lost post so finders or security can coordinate.</div>
                        </div>
                    </div>
                </label>
                @endif
            </div>
        </div>

        {{-- Section 2: Item Details & Location --}}
        <div class="card-surface">
            <h2 class="section-title" style="margin-bottom: 16px;">2. Item Information &amp; Location</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                @include('items.partials.category-field', [
                    'categories' => $categories,
                    'includePlaceholder' => true,
                    'selectedCategoryId' => '',
                    'customCategory' => '',
                ])

                <div class="form-group">
                    <label for="event_date">Event Date</label>
                    <input id="event_date" type="date" name="event_date" value="{{ old('event_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="title">Item Title</label>
                <input id="title" type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Brown Leather Wallet / Blue Corkcicle Tumbler" required>
            </div>

            <div class="form-group">
                <label for="description">Public Description</label>
                <textarea id="description" name="description" rows="4" maxlength="1500" data-char-counter placeholder="Describe color, brand, and general traits. Never include phone numbers, personal emails, or ID numbers." required>{{ old('description') }}</textarea>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="building">Campus Building</label>
                    <select id="building" name="building" required>
                        <option value="">-- Select Building --</option>
                        @foreach($buildings as $building)
                            <option value="{{ $building }}" {{ old('building') === $building ? 'selected' : '' }}>
                                {{ $building }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label for="floor_detail">Floor / Room Detail</label>
                    <input id="floor_detail" type="text" name="floor_detail" value="{{ old('floor_detail') }}" placeholder="e.g. Floor 3, Room 302 or Near Elevator" required>
                </div>
            </div>
        </div>

        {{-- Section 3: Custody & Hidden Details (Found Only) --}}
        <div class="card-surface collapsible-section" id="found-only-fields" style="border-left: 4px solid var(--emerald-found);">
            <h2 class="section-title" style="margin-bottom: 6px;">3. Custody Deposit &amp; Verification Notes</h2>
            <p class="caption" style="margin: 0 0 16px;">Records where the physical item is held by security before claims are reviewed.</p>

            <div class="form-group">
                <label for="custody_location">Custody Point (Security Post / Lobby)</label>
                <select id="custody_location" name="custody_location">
                    <option value="">-- Select Custody Point --</option>
                    @foreach($depositLocations as $loc)
                        <option value="{{ $loc }}" {{ old('custody_location') === $loc ? 'selected' : '' }}>
                            {{ $loc }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="deposit_note">Deposit Note (Officer Name / Shift)</label>
                <textarea id="deposit_note" name="deposit_note" rows="2" placeholder="e.g. Handed to Officer Andi (Afternoon Shift) at 13:30.">{{ old('deposit_note') }}</textarea>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
                <label for="hidden_detail">Hidden Distinguishing Detail (Optional &mdash; Security Only)</label>
                <textarea id="hidden_detail" name="hidden_detail" rows="2" placeholder="Private distinguishing mark hidden from public view to help officers verify ownership (e.g. interior sticker, card contents).">{{ old('hidden_detail') }}</textarea>
                <span class="field-hint">Strictly visible to security officers only.</span>
            </div>
        </div>

        {{-- Section 3/4: Photo, Hashtags & Privacy --}}
        <div class="card-surface">
            <h2 class="section-title" style="margin-bottom: 16px;"><span id="media-step-number">4</span>. Photo, Hashtags &amp; Privacy</h2>

            <div class="form-group">
                <label for="photo">
                    Item Photo <span id="photo-requirement-label" style="color: var(--rose-lost); font-weight: 600;">(Required for Found Posts)</span>
                </label>
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
                <span class="field-hint">Drag &amp; drop or choose an image up to 2MB.</span>
            </div>

            <div class="form-group">
                <label for="tags">Hashtags (1 to 5 tags, comma-separated)</label>
                <input id="tags" type="text" name="tags" value="{{ old('tags') }}" data-tags-input data-tags-preview="#tag-preview" placeholder="e.g. tumbler, blue, corkcicle" required>
                <div class="suggested-tags-row" aria-label="Quick hashtag suggestions">
                    <span class="caption" style="font-size: 11.5px;">Quick add:</span>
                    @foreach(['charger', 'laptop', 'tumbler', 'wallet', 'ktm', 'keys', 'jacket', 'earbuds'] as $quickTag)
                        <button type="button" class="suggested-tag" data-suggest-tag="{{ $quickTag }}" data-tags-target="#tags">+ #{{ $quickTag }}</button>
                    @endforeach
                </div>
                <div id="tag-preview" style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px;"></div>
            </div>

            <div class="form-group callout-info" style="margin-bottom: 0; padding: 14px; border-radius: 10px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer; font-weight: 400; margin: 0;">
                    <input type="checkbox" name="is_sensitive" value="1" {{ old('is_sensitive') ? 'checked' : '' }} style="width: auto; margin-top: 3px;">
                    <span>
                        <strong>Mark as Sensitive Item (KTM / KTP / SIM / Bank Card):</strong><br>
                        <span class="caption">Stores the original photo in private storage and blurs it publicly; only security officers can view the unblurred original.</span>
                    </span>
                </label>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 12px;">
            <a href="{{ route('dashboard') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary" style="padding: 11px 24px;">Publish Post</button>
        </div>
    </form>

    {{-- Live Interactive Card Preview --}}
    <aside class="create-preview-col" aria-label="Live Feed Card Preview">
        <div class="create-preview-sticky">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span class="caption" style="font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">Live Feed Preview</span>
                <span class="caption">Updates as you type</span>
            </div>

            <article class="post-card" id="create-live-preview">
                <div class="post-media" style="pointer-events: none;">
                    <img id="lp-img" src="#" alt="Preview photo" style="display: none;">
                    <div id="lp-placeholder" class="post-media-placeholder is-found">
                        <span class="placeholder-icon" id="lp-placeholder-icon" style="color: var(--primary-navy);">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>
                            </svg>
                        </span>
                        Photo preview appears here
                    </div>

                    <div class="media-overlay-top">
                        <span class="glass-pill">
                            <span class="dot" id="lp-type-dot" style="background: var(--emerald-found);"></span>
                            <span id="lp-type-label">Found</span>
                        </span>
                        <div style="display: flex; gap: 6px;">
                            <span id="lp-sensitive-pill" class="glass-pill is-dark" hidden>Sensitive</span>
                            <span id="lp-status-pill" class="badge badge-found-open">Open</span>
                        </div>
                    </div>

                    <div id="lp-custody-overlay" class="media-overlay-bottom" hidden>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6EE7B7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        <span id="lp-custody-text"></span>
                    </div>
                </div>

                <div class="post-body">
                    <div class="post-category" id="lp-category">Category</div>
                    <h3 class="post-title" id="lp-title" style="color: var(--text-dark);">Your item title...</h3>
                    <p class="post-desc" id="lp-desc">Item traits, brand, color, and helpful identifying notes will show here.</p>
                    <div class="post-meta">
                        <span id="lp-building">Building</span>
                        <span id="lp-date">{{ date('d M Y') }}</span>
                    </div>
                    <div class="post-tags" id="lp-tags" style="margin-top: 4px;"></div>
                </div>

                <div class="post-footer">
                    <div class="post-author">
                        <span class="ig-avatar is-sm">{{ strtoupper(mb_substr(trim(auth()->user()->name), 0, 1)) ?: 'U' }}</span>
                        <div style="min-width: 0;">
                            <div class="post-author-name"><span>{{ auth()->user()->name }}</span></div>
                            <div class="caption" style="font-size: 11.5px;">Just now</div>
                        </div>
                    </div>
                    <span class="btn btn-primary btn-sm">View</span>
                </div>
            </article>
        </div>
    </aside>
    </div>
</div>
@endsection
