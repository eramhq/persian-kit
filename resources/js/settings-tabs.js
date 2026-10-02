/**
 * Alpine component for the settings page tabs. The page works without it:
 * each tab is a link to ?tab=<name> and the server marks the active one.
 * This switches panels in place, keeps the address and the form's
 * redirect target on the current tab, and notices unsaved edits.
 */
export default function settingsTabs() {
    return {
        dirty: false,

        init() {
            const tabs = this.tabs();
            const active = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0];

            // Roving focus: Tab reaches the active tab, arrows reach the rest.
            tabs.forEach((tab) => {
                tab.tabIndex = tab === active ? 0 : -1;
                tab.addEventListener('click', (event) => this.onClick(event, tab));
                tab.addEventListener('keydown', (event) => this.onKeydown(event, tab));
            });

            // The Plugins screen's compatibility notice links to these cards.
            if (window.location.hash === '#persian-kit-compatibility') {
                this.$el.querySelectorAll('#persian-kit-compatibility details').forEach((details) => {
                    details.open = true;
                });
            }

            const form = this.form();
            if (form) {
                const markDirty = () => {
                    this.dirty = true;
                };
                form.addEventListener('change', markDirty);
                form.addEventListener('input', markDirty);
            }
        },

        tabs() {
            return Array.from(this.$el.querySelectorAll('[role="tab"][data-tab]'));
        },

        form() {
            return document.getElementById('persian-kit-settings-form');
        },

        onClick(event, tab) {
            // Let Ctrl/Cmd/Shift-click open the tab in a new window.
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            this.select(tab);
        },

        onKeydown(event, tab) {
            const tabs = this.tabs();
            const index = tabs.indexOf(tab);
            const rtl = document.documentElement.dir === 'rtl';
            let next = null;

            switch (event.key) {
                case 'ArrowRight':
                    next = tabs[(index + (rtl ? -1 : 1) + tabs.length) % tabs.length];
                    break;
                case 'ArrowLeft':
                    next = tabs[(index + (rtl ? 1 : -1) + tabs.length) % tabs.length];
                    break;
                case 'Home':
                    next = tabs[0];
                    break;
                case 'End':
                    next = tabs[tabs.length - 1];
                    break;
                default:
                    return;
            }

            event.preventDefault();
            this.select(next);
            next.focus();
        },

        select(tab) {
            const name = tab.dataset.tab;

            this.tabs().forEach((other) => {
                const selected = other === tab;
                other.setAttribute('aria-selected', selected ? 'true' : 'false');
                other.classList.toggle('nav-tab-active', selected);
                other.tabIndex = selected ? 0 : -1;

                const panel = document.getElementById(other.getAttribute('aria-controls'));
                if (panel) {
                    panel.hidden = !selected;
                }
            });

            // The Tools tab is outside the form and saves nothing.
            const saveBar = this.$el.querySelector('.persian-kit-savebar');
            if (saveBar) {
                saveBar.hidden = name === 'tools';
            }

            const url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            // The notice belongs to the save that just happened, not to a reload.
            url.searchParams.delete('settings-updated');
            window.history.replaceState(window.history.state, '', url);

            // options.php sends the browser back to this address after Save.
            const referer = this.form()?.querySelector('input[name="_wp_http_referer"]');
            if (referer) {
                referer.value = url.pathname + url.search;
            }
        },
    };
}
