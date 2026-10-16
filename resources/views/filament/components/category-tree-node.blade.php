@foreach($categories as $category)
    <div class="my-1 sortable-item" data-id="{{ $category->id }}">
        <!-- Linha da Categoria (com ícone de arrastar) -->
        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:border-amber-500/50 transition cursor-grab active:cursor-grabbing">
            <div class="flex items-center gap-3">
                <!-- Ícone de Arrastar (Grip) -->
                <x-heroicon-m-bars-3 class="w-5 h-5 text-gray-400 drag-handle hover:text-amber-500 transition" />

                @if($category->children->isNotEmpty())
                    <x-heroicon-o-folder-open class="w-5 h-5 text-amber-500" />
                @else
                    <x-heroicon-o-tag class="w-4 h-4 text-gray-400" />
                @endif

                <span class="font-medium text-gray-900 dark:text-gray-100 select-none">
                    {{ $category->name }}
                </span>

                @if(!$category->is_active)
                    <span class="text-xs bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400 px-2 py-0.5 rounded-full font-medium">
                        Inativo
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <span class="text-xs text-gray-400 font-mono">
                    Ordem: {{ $category->sort_order }}
                </span>

                <a href="{{ \App\Filament\Resources\Categories\CategoryResource::getUrl('edit', ['record' => $category]) }}"
                   class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                    Editar
                </a>
            </div>
        </div>

        <!-- Renderização Recursiva dos Filhos (Subcategorias) -->
        <div class="ml-6 pl-4 border-l-2 border-gray-200 dark:border-gray-700 space-y-1 mt-1 sortable-list min-h-[5px]" data-parent-id="{{ $category->id }}">
            @if($category->children->isNotEmpty())
                @include('filament.components.category-tree-node', ['categories' => $category->children])
            @endif
        </div>
    </div>
@endforeach
