@if (! empty($publicNav) && count($publicNav) > 0)
    <nav class="border-t border-gray-100 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <ul class="flex flex-wrap items-center gap-2 py-2 text-sm">
                <li>
                    <a href="{{ route('public.home') }}" class="px-2 py-1 hover:text-rzvg-600">Home</a>
                </li>
                @foreach ($publicNav as $rootPage)
                    <li class="relative" x-data="{ open: false }">
                        @if ($rootPage->children->isEmpty())
                            <a href="{{ $rootPage->publicUrl() }}" class="px-2 py-1 hover:text-rzvg-600">{{ $rootPage->title }}</a>
                        @else
                            <button type="button" @click="open = !open" class="px-2 py-1 hover:text-rzvg-600 inline-flex items-center gap-1">
                                {{ $rootPage->title }}
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                            </button>
                            <ul x-show="open" @click.outside="open = false" x-transition class="absolute z-10 left-0 mt-1 bg-white border border-gray-200 rounded-md shadow-lg min-w-[12rem] py-1">
                                <li><a href="{{ $rootPage->publicUrl() }}" class="block px-3 py-1.5 hover:bg-gray-50">{{ $rootPage->title }}</a></li>
                                @foreach ($rootPage->children as $child)
                                    <li><a href="{{ $child->publicUrl() }}" class="block px-3 py-1.5 hover:bg-gray-50">{{ $child->title }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>
@endif
