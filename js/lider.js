document.addEventListener('DOMContentLoaded', () => {
    iniciarMapaLider();

    const canvas = document.getElementById('chartLider');

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const etiquetas = JSON.parse(canvas.dataset.labels || '[]');
    const valores = JSON.parse(canvas.dataset.values || '[]');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: etiquetas,
            datasets: [{
                label: 'Asistencia',
                data: valores,
                borderColor: '#3061f2',
                backgroundColor: '#edf2ff',
                fill: true,
                tension: 0.35
            }]
        },
        options: {
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
});

function iniciarMapaLider() {
    const contenedor = document.getElementById('mapaLider');

    if (!contenedor || typeof L === 'undefined') {
        return;
    }

    const latitud = Number(contenedor.dataset.lat);
    const longitud = Number(contenedor.dataset.lng);

    if (!Number.isFinite(latitud) || !Number.isFinite(longitud)) {
        return;
    }

    const mapa = L.map(contenedor, { scrollWheelZoom: false })
        .setView([latitud, longitud], 16);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(mapa);

    L.marker([latitud, longitud])
        .addTo(mapa)
        .bindPopup(contenedor.dataset.name || 'Mi célula')
        .openPopup();
}
