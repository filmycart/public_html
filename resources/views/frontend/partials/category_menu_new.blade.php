    <div style="border:0px solid red;width:1400px;float:left;margin:0px;">
    @foreach (\App\Models\Category::where('level', 0)->orderBy('order_level', 'desc')->get()->take(7) as $key => $category)
        <div style="border:0px solid red;width:180px;float:left;" class="categorymenudropdown">
         <a href="{{ route('products.category', $category->slug) }}">
            <div style="border:0px solid red;width:170px;float:left;text-align:center;">
                 <img
                        class="cat-image lazyload mr-2 opacity-100"
                        src="{{ static_asset('assets/img/placeholder.jpg') }}"
                        data-src="{{ uploaded_asset($category->icon) }}"
                        width="65"
                        alt="{{ $category->getTranslation('name') }}"
                        onerror="this.onerror=null;this.src='{{ static_asset('assets/img/placeholder.jpg') }}';"
                    >
            </div>    
            <div style="border:0px solid red;width:170px;float:left;text-align:center;">
                <span class="cat-name">{{ $category->getTranslation('name') }}</span>
            </div>
        </a>
            @if(count(\App\Utility\CategoryUtility::get_immediate_children_ids($category->id))>0)
                <div class="categorymenudropdown-content">
                    @foreach (\App\Models\Category::where('parent_id', $category->id)->orderBy('order_level', 'desc')->get() as $key => $sub_category)
                            <a href="{{ route('products.category', $sub_category->slug) }}">
                                <span class="cat-name">{{ $sub_category->getTranslation('name') }}</span>
                            </a>     
                        
                    @endforeach
                </div>
            @endif
    </div>
     @endforeach
 </div>


