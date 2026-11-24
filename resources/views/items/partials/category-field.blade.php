@php
    $selectedCategoryValue = (string) old('category_id', $selectedCategoryId ?? '');
    $selectedCategoryModel = $categories->firstWhere('id', (int) $selectedCategoryValue);
    $isOtherSelected = $selectedCategoryValue === 'others'
        || ($selectedCategoryModel && $selectedCategoryModel->isOthers());
    $customValue = old('custom_category', $customCategory ?? '');
@endphp

<div class="form-group">
    <label for="category_id">Category</label>
    <select id="category_id" name="category_id" required>
        @if(!empty($includePlaceholder))
            <option value="">-- Select Category --</option>
        @endif
        @foreach($categories as $category)
            @php
                $isOptionOther = $category->isOthers();
                $isSelected = $selectedCategoryValue === (string) $category->id
                    || ($isOptionOther && $selectedCategoryValue === 'others');
            @endphp
            <option value="{{ $category->id }}"
                    @if($isOptionOther) data-is-other="true" @endif
                    {{ $isSelected ? 'selected' : '' }}>
                {{ $category->name }}
            </option>
        @endforeach
        @unless($categories->contains(fn ($cat) => $cat->isOthers()))
            <option value="others" data-is-other="true" {{ $selectedCategoryValue === 'others' ? 'selected' : '' }}>
                Others
            </option>
        @endunless
    </select>

    <div id="custom-category-group" style="margin-top: 10px;" @unless($isOtherSelected) hidden @endunless>
        <label for="custom_category">Specify Category</label>
        <input id="custom_category"
               type="text"
               name="custom_category"
               value="{{ $customValue }}"
               maxlength="100"
               placeholder="e.g. Keys, Umbrella, Sports Gear"
               {{ $isOtherSelected ? 'required' : 'disabled' }}>
        <span class="field-hint">Enter the specific item category when selecting Others.</span>
    </div>
</div>
