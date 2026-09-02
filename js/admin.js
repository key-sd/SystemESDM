document.addEventListener('DOMContentLoaded', () => {
    iniciarGraficaResumen();
    iniciarGraficaEstadisticas();
    iniciarMapasCelulas();
    iniciarFiltrosEstadisticas();
    iniciarFiltroPeriodoReportes();
    iniciarGestionMetas();
});

function leerJsonDataset(elemento, propiedad, valorPredeterminado = []) {
    if (!elemento?.dataset[propiedad]) {
        return valorPredeterminado;
    }

    try {
        return JSON.parse(elemento.dataset[propiedad]);
    } catch (error) {
        console.error(`No se pudo leer ${propiedad}.`, error);
        return valorPredeterminado;
    }
}

function iniciarFiltrosEstadisticas() {
    const formulario = document.getElementById('formFiltrosEstadisticas');
    const periodo = formulario?.querySelector('[data-stats-period]');
    const rangoPersonalizado = formulario?.querySelector('[data-custom-range]');

    if (!formulario || !periodo || !rangoPersonalizado) {
        return;
    }

    periodo.addEventListener('change', () => {
        const esPersonalizado = periodo.value === 'personalizado';
        rangoPersonalizado.classList.toggle('visible', esPersonalizado);

        if (!esPersonalizado) {
            formulario.submit();
        }
    });
}

function iniciarFiltroPeriodoReportes() {
    const formulario = document.querySelector('[data-period-filter-form]');
    const periodo = formulario?.querySelector('[data-period-select]');
    const rangoPersonalizado = formulario?.querySelector('[data-custom-range]');

    if (!formulario || !periodo || !rangoPersonalizado) {
        return;
    }

    periodo.addEventListener('change', () => {
        rangoPersonalizado.classList.toggle('visible', periodo.value === 'personalizado');
    });
}

function iniciarGraficaResumen() {
    const canvas = document.getElementById('chartResumen');

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: leerJsonDataset(canvas, 'labels'),
            datasets: leerJsonDataset(canvas, 'series')
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

function iniciarGraficaEstadisticas() {
    const canvas = document.getElementById('chartStats');

    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: leerJsonDataset(canvas, 'labels'),
            datasets: [{
                label: 'Asistencia',
                data: leerJsonDataset(canvas, 'values'),
                backgroundColor: '#3061f2',
                borderRadius: 8
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
}

function iniciarMapasCelulas() {
    const selector = document.getElementById('mapaSelector');
    const mapaGeneral = document.getElementById('mapaCelulas');

    if (!selector || !mapaGeneral || typeof L === 'undefined') {
        return;
    }

    iniciarSelectorUbicacion(selector);
    iniciarMapaGeneral(mapaGeneral);
}

function iniciarSelectorUbicacion(contenedor) {
    const centroPredeterminado = [13.4833, -88.4667];
    const latitudInicial = contenedor.dataset.lat
        ? Number(contenedor.dataset.lat)
        : null;
    const longitudInicial = contenedor.dataset.lng
        ? Number(contenedor.dataset.lng)
        : null;
    const tieneUbicacion = latitudInicial !== null && longitudInicial !== null;

    const mapa = L.map(contenedor).setView(
        tieneUbicacion
            ? [latitudInicial, longitudInicial]
            : centroPredeterminado,
        tieneUbicacion ? 16 : 13
    );

    agregarCapaMapa(mapa);

    let marcador = null;

    const seleccionarUbicacion = (latitud, longitud) => {
        document.getElementById('lat').value = latitud.toFixed(7);
        document.getElementById('lng').value = longitud.toFixed(7);

        if (marcador) {
            marcador.setLatLng([latitud, longitud]);
        } else {
            marcador = L.marker([latitud, longitud]).addTo(mapa);
        }

        const estado = document.getElementById('estadoUbicacion');
        estado.classList.add('selected');
        estado.querySelector('span').textContent = 'Ubicación seleccionada';
    };

    if (tieneUbicacion) {
        seleccionarUbicacion(latitudInicial, longitudInicial);
    }

    mapa.on('click', (evento) => {
        seleccionarUbicacion(evento.latlng.lat, evento.latlng.lng);
    });

    document.getElementById('formCelula').addEventListener('submit', (evento) => {
        const latitud = document.getElementById('lat').value;
        const longitud = document.getElementById('lng').value;

        if (latitud && longitud) {
            return;
        }

        evento.preventDefault();
        const estado = document.getElementById('estadoUbicacion');
        estado.classList.remove('selected');
        estado.querySelector('span').textContent =
            'Selecciona una ubicación antes de guardar';
        contenedor.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
}

function iniciarMapaGeneral(contenedor) {
    const puntos = leerJsonDataset(contenedor, 'points');
    const mapa = L.map(contenedor).setView([13.4833, -88.4667], 13);

    agregarCapaMapa(mapa);

    puntos.forEach((punto) => {
        const contenido = document.createElement('div');
        const nombre = document.createElement('strong');
        const sector = document.createElement('span');

        nombre.textContent = punto.nombre;
        sector.textContent = punto.sector;
        contenido.append(nombre, document.createElement('br'), sector);

        L.marker([punto.lat, punto.lng])
            .addTo(mapa)
            .bindPopup(contenido);
    });

    if (puntos.length > 0) {
        mapa.fitBounds(
            puntos.map((punto) => [punto.lat, punto.lng]),
            { padding: [30, 30] }
        );
    }
}

function agregarCapaMapa(mapa) {
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(mapa);
}

function iniciarGestionMetas() {
    const alcance = document.querySelector('[data-meta-scope]');
    const sectorDiv = document.querySelector('[data-meta-sector]');
    const sectorSelect = sectorDiv?.querySelector('select');

    if (!alcance || !sectorDiv) {
        return;
    }

    const actualizarVisibilidad = () => {
        const esSector = alcance.value === 'sector';
        sectorDiv.classList.toggle('sr-only', !esSector);
        if (sectorSelect) {
            sectorSelect.required = esSector;
        }
    };

    alcance.addEventListener('change', actualizarVisibilidad);
    // Ejecutar al inicio para asegurar el estado inicial
    actualizarVisibilidad();
}
