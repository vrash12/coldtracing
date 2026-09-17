<style>
    .ct-truck-marker {
        position: relative;
        width: 48px;
        height: 58px;
        cursor: pointer;
        filter: drop-shadow(0 2px 3px rgba(15, 23, 42, .3));
    }

    .ct-truck-marker svg {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 48px;
        height: 36px;
    }

    .ct-truck-marker-number {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        z-index: 1;
        box-sizing: border-box;
        min-width: 27px;
        height: 27px;
        padding: 0 5px;
        border: 2px solid white;
        border-radius: 999px;
        background: #166534;
        color: white;
        font: 800 14px/23px Arial, sans-serif;
        text-align: center;
    }
</style>
<script>
    window.createColdTraceTruckMarker = function (truckNumber) {
        const marker = document.createElement('div');
        marker.className = 'ct-truck-marker';
        const number = truckNumber ?? '?';
        marker.setAttribute('role', 'img');
        marker.setAttribute('aria-label', `Truck ${number}`);
        marker.innerHTML = `<svg viewBox="0 0 64 46" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M5 6h32v29H5zM37 17h12l10 11v7H37z" fill="#f8fafc" stroke="#426575" stroke-width="2.5" stroke-linejoin="round"/>
            <path d="M42 20h6l7 8H42z" fill="#9cd4e5" stroke="#426575" stroke-width="2" stroke-linejoin="round"/>
            <path d="M9 11h24M9 16h24M5 30h32" stroke="#c5d6dd" stroke-width="2"/>
            <path d="M3 35h57" stroke="#426575" stroke-width="3" stroke-linecap="round"/>
            <circle cx="16" cy="36" r="6" fill="#334d5b" stroke="white" stroke-width="2"/>
            <circle cx="49" cy="36" r="6" fill="#334d5b" stroke="white" stroke-width="2"/>
            <circle cx="16" cy="36" r="2" fill="#dce7ec"/><circle cx="49" cy="36" r="2" fill="#dce7ec"/>
        </svg>`;
        const badge = document.createElement('span');
        badge.className = 'ct-truck-marker-number';
        badge.textContent = String(number);
        marker.appendChild(badge);
        return marker;
    };
</script>
