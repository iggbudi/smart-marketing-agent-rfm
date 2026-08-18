// assets/table-cards.js — ubah tabel menjadi kartu di layar ≤575px (mobile-first).
(function () {
    'use strict';

    function isMobile() { return window.innerWidth <= 575; }

    function buildCards(table) {
        var thead = table.querySelector('thead');
        var tbody = table.querySelector('tbody');
        if (!thead || !tbody) { return null; }

        var labels = Array.prototype.map.call(thead.querySelectorAll('th'), function (th) {
            return th.textContent.trim();
        });

        var container = document.createElement('div');
        container.className = 'table-cards';

        Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (row) {
            var cells = row.querySelectorAll('td');
            if (!cells.length) { return; }

            var card = document.createElement('div');
            card.className = 'table-card';

            var title = document.createElement('div');
            title.className = 'table-card-title';
            title.innerHTML = cells[0].innerHTML; // kolom pertama (nama) jadi judul
            card.appendChild(title);

            var body = document.createElement('div');
            body.className = 'table-card-body';
            for (var i = 1; i < cells.length; i++) {
                var item = document.createElement('div');
                item.className = 'table-card-item';

                var label = document.createElement('span');
                label.className = 'table-card-label';
                label.textContent = labels[i] || '';

                var value = document.createElement('span');
                value.className = 'table-card-value';
                value.innerHTML = cells[i].innerHTML;

                item.appendChild(label);
                item.appendChild(value);
                body.appendChild(item);
            }
            card.appendChild(body);
            container.appendChild(card);
        });

        table.parentNode.insertBefore(container, table);
        table.style.display = 'none';
        return container;
    }

    function apply() {
        document.querySelectorAll('.table-cards-target').forEach(function (table) {
            if (isMobile() && !table.dataset.cardsBuilt) {
                buildCards(table);
                table.dataset.cardsBuilt = '1';
            } else if (!isMobile() && table.dataset.cardsBuilt) {
                var container = table.parentNode.querySelector('.table-cards');
                if (container) { container.remove(); }
                table.style.display = '';
                delete table.dataset.cardsBuilt;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', apply);
    window.addEventListener('resize', apply);
})();
