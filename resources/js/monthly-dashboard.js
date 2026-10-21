import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';
import '../css/monthly-dashboard.css';

/**
 * Wrap the monthly Filament widgets in a personal, explicitly saved GridStack layout.
 * Keep the grid outside Alpine's reactive proxy so library state remains private.
 */
window.monthlyDashboard = (initialLayout, messages) => {
    let grid;
    let savedLayout = structuredClone(initialLayout);
    let media;
    let onBreakpoint;

    return {
        editing: false,
        saving: false,
        mobile: false,
        status: '',
        failed: false,

        /** Initialize the existing widget elements after Alpine has mounted them. */
        init() {
            this.$nextTick(() => {
                grid = GridStack.init({
                    column: 12,
                    cellHeight: 64,
                    margin: 8,
                    minRow: 1,
                    maxRow: 120,
                    animate: true,
                    mode: 'float',
                    staticGrid: true,
                    handle: '.hh-widget-drag',
                    resizable: { handles: 'se' },
                    alwaysShowResizeHandle: true,
                    columnOpts: { breakpointForWindow: true, breakpoints: [{ w: 767, c: 1 }] },
                }, this.$refs.grid);

                media = window.matchMedia('(max-width: 767px)');
                onBreakpoint = () => {
                    this.mobile = media.matches;
                    grid.setStatic(!this.editing || this.mobile);
                    this.resizeCharts();
                };
                media.addEventListener('change', onBreakpoint);
                onBreakpoint();
                grid.on('resizestop dragstop', () => this.resizeCharts());
            });
        },

        /** Remove library listeners when Livewire navigation destroys this dashboard. */
        destroy() {
            media?.removeEventListener('change', onBreakpoint);
            grid?.destroy(false);
        },

        /** Enable deliberate layout editing without interfering with normal widget interactions. */
        edit() {
            if (!grid || this.mobile) return;
            this.status = '';
            this.failed = false;
            this.editing = true;
            grid.setStatic(false);
        },

        /** Restore the last saved layout locally, including changes pushed onto neighboring widgets. */
        cancel() {
            if (this.saving) return;
            grid.load(structuredClone(savedLayout), false);
            grid.setStatic(true);
            this.editing = false;
            this.status = '';
            this.failed = false;
            this.resizeCharts();
        },

        /** Persist only geometry, never widget HTML, component names or another account's ID. */
        async save() {
            if (!grid || this.saving || this.mobile) return;
            const layout = grid.save(false, false, undefined, 12)
                .map(({ id, x = 0, y = 0, w, h, minW, minH }) => ({ id, x, y, w: w ?? minW ?? 1, h: h ?? minH ?? 1 }));
            await this.persist(() => this.$wire.saveDashboardLayout(layout));
        },

        /** Restore and persist this user's default layout without affecting any other account. */
        async reset() {
            if (this.saving) return;
            await this.persist(() => this.$wire.resetDashboardLayout());
        },

        /** Keep unsaved changes available when validation, authentication or the network fails. */
        async persist(request) {
            this.saving = true;
            this.failed = false;
            grid.setStatic(true);
            try {
                const result = await request();
                if (!Array.isArray(result) || result.length !== savedLayout.length) throw new Error('Layout not saved');
                savedLayout = structuredClone(result);
                grid.load(structuredClone(savedLayout), false);
                this.editing = false;
                this.status = messages.saved;
            } catch {
                this.failed = true;
                this.status = messages.error;
            } finally {
                this.saving = false;
                grid.setStatic(!this.editing || this.mobile);
                this.resizeCharts();
            }
        },

        /** Support arrow-key movement and Shift+arrow resizing from each widget's drag button. */
        keyboard(event, id) {
            if (!this.editing || this.saving || this.mobile) return;
            const directions = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
            const delta = directions[event.key];
            if (!delta) return;
            event.preventDefault();
            event.stopPropagation();
            const element = grid.getGridItems().find((item) => item.gridstackNode.id === id);
            const node = element.gridstackNode;
            const [dx, dy] = delta;
            const changes = event.shiftKey
                ? { w: Math.max(node.minW, Math.min(12 - node.x, node.w + dx)), h: Math.max(node.minH, Math.min(20, 120 - node.y, node.h + dy)) }
                : { x: Math.max(0, Math.min(12 - node.w, node.x + dx)), y: Math.max(0, Math.min(120 - node.h, node.y + dy)) };
            grid.update(element, changes);
            this.resizeCharts();
        },

        /** Allow Filament's Chart.js canvases to refit after a widget or viewport changes size. */
        resizeCharts() {
            requestAnimationFrame(() => window.dispatchEvent(new Event('resize')));
        },
    };
};
