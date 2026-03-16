// Handles filtering and rendering the collection overview.
(function() {
    const els = {
        search: document.getElementById('search-input'),
        brandSelect: document.getElementById('filter-brand'),
        scaleSelect: document.getElementById('filter-scale'),
        carBrandSelect: document.getElementById('filter-car-brand'),
        statusSelect: document.getElementById('filter-status'),
        conditionSelect: document.getElementById('filter-condition'),
        addedSort: document.getElementById('filter-added-sort'),
        priceSort: document.getElementById('filter-price-sort'),
        resultsContainer: document.getElementById('cards-container'),
        statsBrands: document.getElementById('stat-brands'),
        statsSize: document.getElementById('stat-size'),
        statsValue: document.getElementById('stat-value'),
        statsPreordered: document.getElementById('stat-preordered'),
        statsSold: document.getElementById('stat-sold'),
        noResults: document.getElementById('no-results-message')
    };

    const state = {
        rawCars: Array.isArray(window.COLLATION_CARS) ? window.COLLATION_CARS : [],
        savedBrands: Array.isArray(window.COLLATION_BRANDS) ? window.COLLATION_BRANDS : [],
        activeView: String(window.COLLATION_VIEW || 'all').toLowerCase(),
        filtered: []
    };

    function getCarBrand(car) {
        return car.car_brand || car.automerk || '';
    }

    function getStatus(car) {
        const status = String(car.car_status || 'owned').trim().toLowerCase();
        if (status === 'preordered' || status === 'sold') {
            return status;
        }
        return 'owned';
    }

    function getCondition(car) {
        return String(car.car_condition || '').trim().toLowerCase();
    }

    function isFavorite(car) {
        return Number(car.is_favorite || 0) === 1;
    }

    function isRealCar(car) {
        return String(car.brand || '').trim().toLowerCase() === 'real car';
    }

    function getModelYear(car) {
        const year = Number(car.model_year || 0);
        if (year >= 1886 && year <= 2100) {
            return String(year);
        }
        return '';
    }

    function getAddedYear(car) {
        const date = new Date(car.created_at || 0);
        const year = date.getFullYear();
        if (!Number.isFinite(year) || year < 1970) {
            return 'Unknown Year';
        }
        return String(year);
    }

    function statusLabel(status) {
        if (status === 'preordered') return 'Preordered';
        if (status === 'sold') return 'Sold';
        return 'Owned';
    }

    function formatCurrency(amount) {
        return 'EUR ' + Number(amount).toLocaleString('nl-NL', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function parseScales(scaleValue) {
        const raw = String(scaleValue || '');
        const parts = raw.split(/[|,/]/).map(s => s.trim()).filter(Boolean);
        const unique = [];
        parts.forEach(v => {
            if (!unique.includes(v)) unique.push(v);
        });
        unique.sort((a, b) => {
            const aN = Number((a.split(':')[1] || 0));
            const bN = Number((b.split(':')[1] || 0));
            return bN - aN;
        });
        return unique;
    }

    function normalizeQuery(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
    }

    function levenshteinDistance(a, b) {
        const m = a.length;
        const n = b.length;
        if (!m) return n;
        if (!n) return m;

        const dp = Array.from({ length: m + 1 }, () => new Array(n + 1).fill(0));
        for (let i = 0; i <= m; i++) dp[i][0] = i;
        for (let j = 0; j <= n; j++) dp[0][j] = j;

        for (let i = 1; i <= m; i++) {
            for (let j = 1; j <= n; j++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                dp[i][j] = Math.min(
                    dp[i - 1][j] + 1,
                    dp[i][j - 1] + 1,
                    dp[i - 1][j - 1] + cost
                );
            }
        }

        return dp[m][n];
    }

    function fuzzyTokenMatch(queryNorm, candidateNorm) {
        if (!queryNorm || !candidateNorm) return false;
        if (candidateNorm.includes(queryNorm) || queryNorm.includes(candidateNorm)) {
            return true;
        }

        const maxDistance = queryNorm.length <= 5 ? 1 : 2;
        return levenshteinDistance(queryNorm, candidateNorm) <= maxDistance;
    }

    function applyQuickView(results) {
        if (state.activeView === 'favorites') {
            return results.filter(isFavorite);
        }
        if (state.activeView === 'preordered') {
            return results.filter(car => getStatus(car) === 'preordered');
        }
        if (state.activeView === 'sold') {
            return results.filter(car => getStatus(car) === 'sold');
        }
        return results.filter(car => getStatus(car) !== 'sold');
    }

    function applyFilters() {
        const search = (els.search.value || '').trim().toLowerCase();
        const brandFilter = els.brandSelect.value;
        const scaleFilter = els.scaleSelect.value;
        const carBrandFilter = els.carBrandSelect.value;
        const statusFilter = els.statusSelect.value;
        const conditionFilter = els.conditionSelect.value;
        const addedSort = els.addedSort.value;
        const priceSort = els.priceSort.value;

        let results = applyQuickView([...state.rawCars]);

        if (brandFilter) {
            results = results.filter(c => String(c.brand || '') === brandFilter);
        }
        if (scaleFilter) {
            results = results.filter(c => parseScales(c.scale).includes(scaleFilter));
        }
        if (carBrandFilter) {
            results = results.filter(c => getCarBrand(c) === carBrandFilter);
        }
        if (statusFilter) {
            results = results.filter(c => getStatus(c) === statusFilter);
        }
        if (conditionFilter) {
            results = results.filter(c => getCondition(c) === conditionFilter);
        }

        if (search) {
            const parsedNumber = Number(search.replace(',', '.'));
            const numericSearch = !Number.isNaN(parsedNumber) && search !== '';
            const searchNorm = normalizeQuery(search);

            results = results.filter(c => {
                const scaleText = parseScales(c.scale).join(' ');
                const combined = `${c.brand} ${getCarBrand(c)} ${c.model} ${scaleText} ${getModelYear(c)} ${getCondition(c)} ${getStatus(c)}`.toLowerCase();
                if (combined.includes(search)) {
                    return true;
                }

                const normCombined = normalizeQuery(combined);
                if (searchNorm && normCombined.includes(searchNorm)) {
                    return true;
                }

                if (searchNorm) {
                    const tokens = [
                        c.brand,
                        getCarBrand(c),
                        c.model,
                        getModelYear(c),
                        getCondition(c),
                        getStatus(c),
                        ...parseScales(c.scale)
                    ].map(normalizeQuery).filter(Boolean);
                    if (tokens.some(token => fuzzyTokenMatch(searchNorm, token))) {
                        return true;
                    }
                }

                if (numericSearch) {
                    const bought = Number(c.bought_price || 0);
                    return Math.abs(bought - parsedNumber) <= 5;
                }

                return false;
            });
        }

        if (addedSort === 'created_at:group_asc' || addedSort === 'created_at:group_desc') {
            const asc = addedSort === 'created_at:group_asc';
            results.sort((a, b) => {
                const aTime = new Date(a.created_at || 0).getTime();
                const bTime = new Date(b.created_at || 0).getTime();
                return asc ? aTime - bTime : bTime - aTime;
            });
        }

        if (priceSort) {
            const parts = priceSort.split(':');
            const field = parts[0];
            const direction = parts[1] || 'desc';
            results.sort((a, b) => {
                const aVal = Number(a[field] || 0);
                const bVal = Number(b[field] || 0);
                return direction === 'asc' ? aVal - bVal : bVal - aVal;
            });
        }

        state.filtered = results;
        renderResults();
        renderStats();
    }

    function renderStats() {
        const cars = state.filtered;
        const activeCars = cars.filter(c => getStatus(c) !== 'sold');
        const uniqueBrands = new Set(activeCars.map(c => String(c.brand || '').trim()).filter(Boolean));
        const totalValue = activeCars.reduce((sum, c) => sum + Number(c.estimated_value || 0), 0);
        const preordered = state.rawCars.filter(c => getStatus(c) === 'preordered').length;
        const sold = state.rawCars.filter(c => getStatus(c) === 'sold').length;

        if (els.statsBrands) els.statsBrands.textContent = String(uniqueBrands.size);
        if (els.statsSize) els.statsSize.textContent = String(activeCars.length);
        if (els.statsValue) els.statsValue.textContent = formatCurrency(totalValue);
        if (els.statsPreordered) els.statsPreordered.textContent = String(preordered);
        if (els.statsSold) els.statsSold.textContent = String(sold);
    }

    function renderResults() {
        const cars = state.filtered;
        els.resultsContainer.innerHTML = '';

        const currentlyHasCars = state.rawCars.length > 0;
        if (!currentlyHasCars) {
            els.noResults.textContent = 'No vehicles found yet. Add a brand first, then add your first vehicle.';
            els.noResults.classList.add('visible');
            return;
        }

        if (cars.length === 0) {
            const search = (els.search.value || '').trim();
            if (search) {
                els.noResults.textContent = 'No vehicles found for your search.';
            } else {
                els.noResults.textContent = 'No vehicles found for the selected filters.';
            }
            els.noResults.classList.add('visible');
            return;
        }

        els.noResults.classList.remove('visible');

        let currentYear = '';
        cars.forEach(car => {
            const year = getAddedYear(car);
            if (year !== currentYear) {
                currentYear = year;
                const yearTitle = document.createElement('div');
                yearTitle.className = 'cards-year-heading';
                yearTitle.textContent = currentYear + ' Added';
                els.resultsContainer.appendChild(yearTitle);
            }

            const displayScale = parseScales(car.scale).join(' / ') || '-';
            const modelYear = getModelYear(car);
            const status = getStatus(car);
            const condition = getCondition(car);
            const titleText = isRealCar(car)
                ? `${getCarBrand(car)} ${car.model}`.trim()
                : `${car.brand} ${car.model}`.trim();
            const titleRow = modelYear ? `${titleText} (${modelYear})` : titleText;
            const delta = Number(car.estimated_value || 0) - Number(car.bought_price || 0);
            const deltaLabel = (delta >= 0 ? '+ ' : '- ') + formatCurrency(Math.abs(delta));
            const favoriteBadge = isFavorite(car) ? '<span class="favorite-star" title="Favorite">★</span>' : '';

            const card = document.createElement('div');
            card.className = 'car-card car-card--clickable status-' + status;
            card.innerHTML = `
                <div class="car-card__header">
                    <h3 title="${escapeHtml(titleRow)}">${escapeHtml(titleRow)}</h3>
                    <span class="tag">${escapeHtml(displayScale)}</span>
                </div>
                <div class="car-card__details">
                    <div class="status-row">
                        <span class="status-chip status-chip--${escapeHtml(status)}">${escapeHtml(statusLabel(status))}</span>
                        ${favoriteBadge}
                    </div>
                    ${isRealCar(car) ? '' : `<div><strong>Car Brand:</strong> ${escapeHtml(getCarBrand(car))}</div>`}
                    ${condition ? `<div><strong>Condition:</strong> ${escapeHtml(condition)}</div>` : ''}
                    <div><strong>Purchase Price:</strong> ${formatCurrency(car.bought_price)}</div>
                    <div><strong>Estimated Value:</strong> ${formatCurrency(car.estimated_value)}</div>
                    <div><strong>Gain/Loss:</strong> ${escapeHtml(deltaLabel)}</div>
                    ${(car.details || '').trim() ? '<div><strong>Extras:</strong> ' + escapeHtml(String(car.details).trim()) + '</div>' : ''}
                </div>
            `;

            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'button');
            card.addEventListener('click', () => {
                window.location.href = `car-details.php?id=${encodeURIComponent(car.id)}`;
            });
            card.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    window.location.href = `car-details.php?id=${encodeURIComponent(car.id)}`;
                }
            });

            els.resultsContainer.appendChild(card);
        });
    }

    function escapeHtml(text) {
        if (typeof text !== 'string') return String(text || '');
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function attachListeners() {
        if (els.search) els.search.addEventListener('input', applyFilters);
        if (els.brandSelect) els.brandSelect.addEventListener('change', applyFilters);
        if (els.scaleSelect) els.scaleSelect.addEventListener('change', applyFilters);
        if (els.carBrandSelect) els.carBrandSelect.addEventListener('change', applyFilters);
        if (els.statusSelect) els.statusSelect.addEventListener('change', applyFilters);
        if (els.conditionSelect) els.conditionSelect.addEventListener('change', applyFilters);
        if (els.addedSort) els.addedSort.addEventListener('change', applyFilters);
        if (els.priceSort) els.priceSort.addEventListener('change', applyFilters);
    }

    function populateSelect(selectEl, values, placeholder) {
        if (!selectEl) return;
        const currentValue = selectEl.value;
        selectEl.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        selectEl.appendChild(placeholderOption);

        values.forEach(val => {
            const option = document.createElement('option');
            option.value = val;
            option.textContent = val;
            selectEl.appendChild(option);
        });

        if (currentValue && [...selectEl.options].some(o => o.value === currentValue)) {
            selectEl.value = currentValue;
        }
    }

    function init() {
        const brandsFromCars = state.rawCars.map(c => String(c.brand || '').trim()).filter(Boolean);
        const brandsFromSaved = state.savedBrands.map(b => String(b || '').trim()).filter(Boolean);
        const brands = [...new Set([...brandsFromCars, ...brandsFromSaved])].sort((a, b) => a.localeCompare(b, 'nl', { sensitivity: 'base' }));
        const carBrands = [...new Set(state.rawCars.map(c => getCarBrand(c)).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'nl', { sensitivity: 'base' }));
        const baseScales = ['1:1', '1:8', '1:10', '1:12', '1:14', '1:16', '1:18', '1:24', '1:32', '1:43', '1:64'];
        const dataScales = [...new Set(state.rawCars.flatMap(c => parseScales(c.scale)))];
        const scales = [...new Set([...baseScales, ...dataScales])].sort((a, b) => {
            const numA = parseFloat(a.replace(/[^0-9.]/g, '')) || 0;
            const numB = parseFloat(b.replace(/[^0-9.]/g, '')) || 0;
            return numA - numB;
        });
        const conditions = [...new Set(state.rawCars.map(c => getCondition(c)).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'nl', { sensitivity: 'base' }));

        populateSelect(els.brandSelect, brands, 'Brand');
        populateSelect(els.carBrandSelect, carBrands, 'Car Brand');
        populateSelect(els.scaleSelect, scales, 'Scale');
        populateSelect(els.conditionSelect, conditions, 'Condition');

        attachListeners();
        applyFilters();
    }

    document.addEventListener('DOMContentLoaded', init);
})();
