<script>
window.PedestriRouteLocationSearch = {
    attach({ input, list, kind, token, places, contains, onChoose, onEdit }) {
        let timer = null;
        let controller = null;
        let version = 0;
        let active = -1;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-controls', list.id);
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');
        list.setAttribute('role', 'listbox');

        function cancel() {
            clearTimeout(timer);
            if (controller) controller.abort();
            version++;
        }
        function close() {
            cancel();
            list.classList.add('hidden');
            input.setAttribute('aria-expanded', 'false');
        }
        function option(text, choose) {
            const row = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('role', 'option');
            button.className = 'w-full text-left px-3 py-2.5 text-xs font-semibold text-slate-700 hover:bg-brand-50 focus:bg-brand-50';
            button.textContent = text;
            button.addEventListener('mousedown', event => event.preventDefault());
            button.addEventListener('click', () => { close(); choose(); });
            row.appendChild(button);
            list.appendChild(row);
        }
        function render(results, message = '') {
            active = -1;
            list.replaceChildren();
            results.forEach(place => option(place.label || place.name, () => onChoose(kind, place)));
            if (message) {
                const row = document.createElement('li');
                row.className = 'px-3 py-2 text-xs text-slate-500';
                row.setAttribute('role', 'status');
                row.textContent = message;
                list.appendChild(row);
            }
            list.classList.remove('hidden');
            input.setAttribute('aria-expanded', 'true');
        }
        function search() {
            cancel();
            const query = input.value.trim();
            const local = places.filter(place => !query || `${place.name} ${place.type}`.toLowerCase().includes(query.toLowerCase()));
            render(local, query.length >= 3 ? 'Mencari alamat…' : 'Ketik minimal 3 karakter untuk mencari alamat.');
            if (query.length < 3) return;
            const requestVersion = version;
            timer = setTimeout(async () => {
                controller = new AbortController();
                try {
                    const response = await fetch(`/api/v1/places/search?q=${encodeURIComponent(query)}`, {
                        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
                        signal: controller.signal,
                    });
                    const data = await response.json();
                    if (requestVersion !== version) return;
                    if (!response.ok) throw new Error(data.error?.message || 'Alamat belum dapat dicari. Pilih rekomendasi lokasi di Mulyorejo.');
                    const seen = new Set(local.map(place => `${Number(place.lat).toFixed(5)},${Number(place.lng).toFixed(5)}`));
                    const results = [...local];
                    (data.places || []).forEach(place => {
                        if (!Number.isFinite(place.lat) || !Number.isFinite(place.lng) || !contains(place.lat, place.lng)) return;
                        const key = `${place.lat.toFixed(5)},${place.lng.toFixed(5)}`;
                        if (seen.has(key)) return;
                        seen.add(key);
                        results.push(place);
                    });
                    render(results.length ? results : places, results.length ? '' : 'Alamat belum ditemukan. Berikut rekomendasi lokasi di sekitar Mulyorejo.');
                } catch (error) {
                    if (error.name !== 'AbortError' && requestVersion === version) render(local.length ? local : places, error.message);
                }
            }, 500);
        }
        input.addEventListener('focus', search);
        input.addEventListener('input', () => { onEdit(kind); search(); });
        input.addEventListener('blur', () => setTimeout(() => {
            if (!list.contains(document.activeElement)) close();
        }, 150));
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { close(); return; }
            if (!['ArrowDown', 'ArrowUp', 'Enter'].includes(event.key) || list.classList.contains('hidden')) return;
            event.preventDefault();
            const buttons = [...list.querySelectorAll('button')];
            if (event.key === 'Enter') { buttons[active >= 0 ? active : 0]?.click(); return; }
            if (!buttons.length) return;
            active = (active + (event.key === 'ArrowDown' ? 1 : -1) + buttons.length) % buttons.length;
            buttons.forEach((button, index) => button.setAttribute('aria-selected', index === active ? 'true' : 'false'));
            buttons[active]?.scrollIntoView({ block: 'nearest' });
        });
        return { close };
    },
};
</script>
