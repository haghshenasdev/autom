<script>
(() => {
    const root = document.getElementById('newui-spa');
    const page = document.getElementById('newui-page');
    if (!root || !page || !window.Vue) return;

    const loadedSources = new Set(Array.from(document.querySelectorAll('script[src]')).map((script) => script.src));
    document.head.querySelectorAll('style').forEach((style) => { style.dataset.newuiRouteStyle = 'true'; });
    document.head.querySelectorAll('link[rel="stylesheet"]').forEach((link) => { link.dataset.newuiRouteLink = 'true'; });

    async function runPageScripts(scripts) {
        for (const definition of scripts) {
            if (definition.src) {
                if (loadedSources.has(definition.src)) continue;
                await new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = definition.src;
                    script.async = false;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.body.appendChild(script);
                });
                loadedSources.add(definition.src);
                continue;
            }
            if (!definition.text.trim()) continue;
            const script = document.createElement('script');
            if (definition.type) script.type = definition.type;
            script.textContent = definition.text;
            document.body.appendChild(script);
            script.remove();
        }
    }

    async function refreshTailwind(configText) {
        const runtime = Array.from(document.querySelectorAll('script[src]'))
            .find((script) => script.src.includes('cdn.tailwindcss.com'));
        if (!runtime) return;

        const runtimeUrl = runtime.src;
        document.querySelectorAll('script[src]').forEach((script) => {
            if (script.src.includes('cdn.tailwindcss.com')) script.remove();
        });

        if (configText) {
            const config = document.createElement('script');
            config.textContent = configText;
            document.head.appendChild(config);
            config.remove();
        }

        await new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = runtimeUrl;
            script.onload = resolve;
            script.onerror = resolve;
            document.head.appendChild(script);
        });
    }

    const app = Vue.createApp({
        data() {
            return { loading: false };
        },
        template: '<div v-if="loading" class="fixed inset-0 z-[100] grid place-items-center bg-slate-900/20 backdrop-blur-[2px]" role="status" aria-live="polite"><div class="rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-700 shadow-xl">در حال بارگذاری…</div></div>',
        mounted() {
            const vm = this;
            root.addEventListener('click', (event) => {
                const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
                if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                if (link.target === '_blank' || link.hasAttribute('download')) return;
                const url = new URL(link.href, window.location.href);
                if (url.origin !== window.location.origin || !/^\/newui(?:\/|$)/.test(url.pathname)) return;
                if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;
                event.preventDefault();
                vm.navigate(url.href);
            });

            root.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || (form.method || 'get').toLowerCase() !== 'get' || form.target === '_blank') return;
                const url = new URL(form.action || window.location.href, window.location.href);
                if (url.origin !== window.location.origin || !/^\/newui(?:\/|$)/.test(url.pathname)) return;
                event.preventDefault();
                const params = new URLSearchParams(new FormData(form));
                vm.navigate(url.pathname + (params.toString() ? '?' + params.toString() : ''));
            });

            window.addEventListener('popstate', () => vm.navigate(window.location.href, false));
            window.NewUiSpa = { navigate: (url) => vm.navigate(url) };
        },
        methods: {
            async navigate(target, push = true) {
                const url = new URL(target, window.location.href);
                this.loading = true;
                try {
                    const response = await fetch(url.href, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!response.ok) throw new Error('Page request failed');
                    const documentPage = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const nextPage = documentPage.getElementById('newui-page');
                    if (!nextPage) throw new Error('SPA page container missing');

                    const scripts = Array.from(nextPage.querySelectorAll('script')).map((script) => ({ src: script.src, type: script.type, text: script.textContent }));
                    const tailwindConfig = Array.from(documentPage.head.querySelectorAll('script:not([src])'))
                        .find((script) => script.textContent.includes('tailwind.config'))?.textContent || '';
                    document.head.querySelectorAll('[data-newui-route-style]').forEach((style) => style.remove());
                    document.head.querySelectorAll('[data-newui-route-link]').forEach((link) => link.remove());
                    documentPage.head.querySelectorAll('style').forEach((style) => {
                        const copy = document.createElement('style');
                        copy.dataset.newuiRouteStyle = 'true';
                        copy.textContent = style.textContent;
                        document.head.appendChild(copy);
                    });
                    documentPage.head.querySelectorAll('link[rel="stylesheet"]').forEach((link) => {
                        const copy = document.createElement('link');
                        copy.rel = 'stylesheet';
                        copy.href = link.href;
                        copy.dataset.newuiRouteLink = 'true';
                        document.head.appendChild(copy);
                    });
                    page.innerHTML = nextPage.innerHTML;
                    document.title = documentPage.title;
                    document.body.className = documentPage.body.className;
                    const nextTheme = documentPage.querySelector('meta[name="theme-color"]')?.content;
                    if (nextTheme) document.querySelector('meta[name="theme-color"]')?.setAttribute('content', nextTheme);
                    if (push) history.pushState({}, '', url.href);
                    await refreshTailwind(tailwindConfig);
                    await runPageScripts(scripts);
                    window.scrollTo(0, 0);
                } catch (error) {
                    window.location.assign(url.href);
                } finally {
                    this.loading = false;
                }
            },
        },
    });

    app.mount(document.getElementById('newui-vue-root'));
})();
</script>
