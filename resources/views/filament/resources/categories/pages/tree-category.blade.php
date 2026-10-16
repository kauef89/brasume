<x-filament-panels::page>
    <!-- Script leve de Drag and Drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

    <div class="p-6 bg-gray-50/50 dark:bg-gray-900/50 rounded-xl border border-gray-200 dark:border-gray-800"
         x-data="treeDragAndDrop($wire)"
         x-init="initTree()">

        <div id="tree-root" class="sortable-list space-y-1" data-parent-id="">
            @if($this->getCategories()->isNotEmpty())
                @include('filament.components.category-tree-node', ['categories' => $this->getCategories()])
            @else
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    Nenhuma categoria cadastrada até o momento.
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('treeDragAndDrop', (wire) => ({
                initTree() {
                    this.$nextTick(() => {
                        this.bindSortable();
                    });
                },
                bindSortable() {
                    const lists = document.querySelectorAll('.sortable-list');
                    lists.forEach(list => {
                        new Sortable(list, {
                            group: 'nested-tree',
                            animation: 150,
                            handle: '.drag-handle',
                            fallbackOnBody: true,
                            swapThreshold: 0.65,
                            onEnd: (evt) => {
                                const treeData = this.serializeTree(document.getElementById('tree-root'));
                                wire.updateCategoryOrder(treeData);
                            }
                        });
                    });
                },
                serializeTree(container) {
                    const items = [];
                    const children = Array.from(container.children).filter(el => el.classList.contains('sortable-item'));

                    children.forEach(item => {
                        const id = item.getAttribute('data-id');
                        const subContainer = item.querySelector('.sortable-list');
                        const node = {
                            id: parseInt(id),
                            children: subContainer ? this.serializeTree(subContainer) : []
                        };
                        items.push(node);
                    });

                    return items;
                }
            }));
        });
    </script>
</x-filament-panels::page>
