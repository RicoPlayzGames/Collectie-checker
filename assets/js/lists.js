(function() {
    const els = {
        tabs: Array.from(document.querySelectorAll('.lists-tab')),
        search: document.getElementById('lists-search'),
        cards: document.getElementById('lists-cards'),
        empty: document.getElementById('lists-empty'),
        countFavorites: document.getElementById('count-favorites'),
        countPreordered: document.getElementById('count-preordered'),
        countSold: document.getElementById('count-sold')
    };

    const state = {
        cars: Array.isArray(window.LISTS_CARS) ? window.LISTS_CARS : [],
        activeList: 'favorites'
    };

    function getStatus(car) {
        const status = String(car.car_status || 'owned').trim().toLowerCase();
        if (status === 'preordered' || status === 'sold') return status;
        return 'owned';
    }

    function isFavorite(car) {
        return Number(car.is_favorite || 0) === 1;
    }

    function getCarBrand(car) {
        return car.car_brand || car.automerk || '';
    }

    function parseScales(scaleValue) {
        return String(scaleValue || '')
            .split(/[|,/]/)
            .map(s => s.trim())
            .filter(Boolean)
            .join(' / ');
    }

    function inActiveList(car) {
        if (state.activeList === 'favorites') return isFavorite(car);
        if (state.activeList === 'preordered') return getStatus(car) === 'preordered';
        return getStatus(car) === 'sold';
    }

    function matchesSearch(car, query) {
        if (!query) return true;
        const text = [
            car.brand,
            getCarBrand(car),
            car.model,
            car.details,
            car.model_year,
            car.car_condition,
            parseScales(car.scale)
        ].join(' ').toLowerCase();
        return text.includes(query);
    }

    function renderCounts() {
        els.countFavorites.textContent = String(state.cars.filter(isFavorite).length);
        els.countPreordered.textContent = String(state.cars.filter(c => getStatus(c) === 'preordered').length);
        els.countSold.textContent = String(state.cars.filter(c => getStatus(c) === 'sold').length);
    }

    function render() {
        const query = String(els.search.value || '').trim().toLowerCase();
        const filtered = state.cars.filter(c => inActiveList(c) && matchesSearch(c, query));

        els.cards.innerHTML = '';
        if (filtered.length === 0) {
            els.empty.textContent = 'No vehicles found in this list.';
            els.empty.classList.add('visible');
            return;
        }

        els.empty.classList.remove('visible');

        filtered.forEach(car => {
            const title = `${car.brand || ''} ${car.model || ''}`.trim();
            const condition = String(car.car_condition || '').trim();
            const year = Number(car.model_year || 0);
            const yearLabel = year >= 1886 && year <= 2100 ? ` (${year})` : '';

            const card = document.createElement('div');
            card.className = 'car-card car-card--clickable';
            card.innerHTML = `
                <div class="car-card__header">
                    <h3 title="${escapeHtml(title + yearLabel)}">${escapeHtml(title + yearLabel)}</h3>
                    <span class="tag">${escapeHtml(parseScales(car.scale) || '-')}</span>
                </div>
                <div class="car-card__details">
                    <div><strong>Car Brand:</strong> ${escapeHtml(getCarBrand(car))}</div>
                    ${condition ? `<div><strong>Condition:</strong> ${escapeHtml(condition)}</div>` : ''}
                    <div><strong>Purchase Price:</strong> EUR ${Number(car.bought_price || 0).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                    <div><strong>Estimated Value:</strong> EUR ${Number(car.estimated_value || 0).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
                </div>
            `;

            card.addEventListener('click', () => {
                window.location.href = `car-details.php?id=${encodeURIComponent(car.id)}`;
            });
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = `car-details.php?id=${encodeURIComponent(car.id)}`;
                }
            });
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');

            els.cards.appendChild(card);
        });
    }

    function setActiveTab(listName) {
        state.activeList = listName;
        els.tabs.forEach(tab => {
            tab.classList.toggle('is-active', tab.getAttribute('data-list') === listName);
        });
        render();
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    els.tabs.forEach(tab => {
        tab.addEventListener('click', () => setActiveTab(tab.getAttribute('data-list') || 'favorites'));
    });
    els.search.addEventListener('input', render);

    renderCounts();
    render();
})();
