document.addEventListener('DOMContentLoaded', () => {
    iniciarMapaLider();
    iniciarGraficaLider();
    iniciarReporteSubformularios();
    iniciarCalculosAsistenciaYOfrenda();
});

function iniciarGraficaLider() {
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
}

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

/**
 * Control interactivo de acordeones y filas dinámicas para Conversiones,
 * Bautismos en agua y Visitas programadas en el formulario de reporte semanal.
 */
function iniciarReporteSubformularios() {
    const formulario = document.getElementById('formReporteLider');
    if (!formulario) {
        return;
    }

    // Manejo de toggles (interruptores)
    const toggles = formulario.querySelectorAll('[data-subform-toggle]');
    toggles.forEach(toggle => {
        const tipo = toggle.dataset.subformToggle;
        const tarjeta = document.getElementById('subform-' + tipo);
        if (!tarjeta) return;

        toggle.addEventListener('change', () => {
            const activo = toggle.checked;
            tarjeta.classList.toggle('is-active', activo);

            if (activo) {
                const contenedor = tarjeta.querySelector('.dynamic-rows-container');
                if (contenedor && contenedor.children.length === 0) {
                    agregarFilaSubformulario(tipo);
                }
            }
            actualizarContadoresReporte();
        });
    });

    // Delegación de eventos para agregar filas
    formulario.addEventListener('click', (e) => {
        const botonAgregar = e.target.closest('[data-add-row]');
        if (botonAgregar) {
            e.preventDefault();
            const tipo = botonAgregar.dataset.addRow;
            agregarFilaSubformulario(tipo);
            return;
        }

        const botonEliminar = e.target.closest('.btn-remove-row');
        if (botonEliminar) {
            e.preventDefault();
            const item = botonEliminar.closest('.dynamic-card-item');
            if (item) {
                const contenedor = item.closest('.dynamic-rows-container');
                item.remove();
                actualizarNumerosFilas(contenedor);
                actualizarContadoresReporte();
            }
        }
    });

    // Autocompletado de miembros al seleccionar nombre
    formulario.addEventListener('change', (e) => {
        if (e.target.matches('[data-member-autocomplete]')) {
            sincronizarAutocompletadoMiembro(e.target);
        }
    });

    actualizarContadoresReporte();
}

function agregarFilaSubformulario(tipo) {
    const plantilla = document.getElementById('tpl-' + tipo);
    const contenedor = document.getElementById('container-' + tipo);
    if (!plantilla || !contenedor) return;

    const indiceUnico = Date.now() + Math.floor(Math.random() * 1000);
    let html = plantilla.innerHTML.replace(/__INDEX__/g, indiceUnico);

    const div = document.createElement('div');
    div.innerHTML = html.trim();
    const nuevaFila = div.firstElementChild;

    contenedor.appendChild(nuevaFila);
    actualizarNumerosFilas(contenedor);
    actualizarContadoresReporte();

    const primerInput = nuevaFila.querySelector('input:not([type="hidden"])');
    if (primerInput) {
        primerInput.focus();
    }
}

function actualizarNumerosFilas(contenedor) {
    if (!contenedor) return;
    const items = contenedor.querySelectorAll('.dynamic-card-item');
    items.forEach((item, i) => {
        const badgeNumero = item.querySelector('.row-number-badge');
        if (badgeNumero) {
            badgeNumero.textContent = '#' + (i + 1);
        }
    });
}

function actualizarContadoresReporte() {
    const totalConversiones = document.querySelectorAll('#container-conversiones .dynamic-card-item').length;
    const totalBautismos = document.querySelectorAll('#container-bautismos .dynamic-card-item').length;
    const totalVisitas = document.querySelectorAll('#container-visitas .dynamic-card-item').length;

    const inputConv = document.getElementById('inputConversionCount');
    if (inputConv) {
        const toggleConv = document.querySelector('[data-subform-toggle="conversiones"]');
        if (toggleConv && toggleConv.checked) {
            inputConv.value = totalConversiones;
        } else {
            inputConv.value = 0;
        }
    }

    const badgeConv = document.getElementById('badgeCountConversiones');
    if (badgeConv) badgeConv.textContent = totalConversiones;

    const badgeBaut = document.getElementById('badgeCountBautismos');
    if (badgeBaut) badgeBaut.textContent = totalBautismos;

    const badgeVis = document.getElementById('badgeCountVisitas');
    if (badgeVis) badgeVis.textContent = totalVisitas;
}

function sincronizarAutocompletadoMiembro(input) {
    const valor = input.value.trim();
    if (!valor) return;

    const datalist = document.getElementById('lista-miembros-celula');
    if (!datalist) return;

    const opcion = Array.from(datalist.options).find(opt => opt.value.toLowerCase() === valor.toLowerCase());
    if (!opcion) return;

    const card = input.closest('.dynamic-card-item');
    if (!card) return;

    const estadoCivil = opcion.dataset.estadoCivil;
    const nacimiento = opcion.dataset.nacimiento;
    const direccion = opcion.dataset.direccion;
    const telefono = opcion.dataset.telefono;
    const bautismoAgua = opcion.dataset.bautismoAgua === '1';
    const bautismoEspiritu = opcion.dataset.bautismoEspiritu === '1';

    const inputEstado = card.querySelector('[data-field="estado_civil"]');
    if (inputEstado && estadoCivil) inputEstado.value = estadoCivil;

    const inputNacimiento = card.querySelector('[data-field="fecha_nacimiento"]');
    if (inputNacimiento && nacimiento) inputNacimiento.value = nacimiento;

    const inputDireccion = card.querySelector('[data-field="direccion"]');
    if (inputDireccion && direccion) inputDireccion.value = direccion;

    const inputTelefono = card.querySelector('[data-field="telefono"]');
    if (inputTelefono && telefono) inputTelefono.value = telefono;

    const checkAgua = card.querySelector('[data-field="bautismo_agua"]');
    if (checkAgua) checkAgua.checked = bautismoAgua;

    const checkEsp = card.querySelector('[data-field="bautismo_espiritu"]');
    if (checkEsp) checkEsp.checked = bautismoEspiritu;
}

function iniciarCalculosAsistenciaYOfrenda() {
    const tableAsistencia = document.querySelector('.attendance-form-table');
    if (tableAsistencia) {
        const tipoCelula = tableAsistencia.dataset.tipoCelula || 'adultos';
        const inAdultosH = document.getElementById('adultos_hermanos');
        const inAdultosA = document.getElementById('adultos_amigos');
        const inJovenesH = document.getElementById('jovenes_hermanos');
        const inJovenesA = document.getElementById('jovenes_amigos');
        const inNinos = document.getElementById('ninos');
        const displayGrupo = document.getElementById('totalGrupoDisplay');
        const labelGrupo = document.getElementById('labelTotalGrupo');
        const displayGlobal = document.getElementById('totalGlobalDisplay');
        const captionTotal = document.getElementById('totalAsistenciaCaption');

        function recalcularAsistencia() {
            const adH = Math.max(0, parseInt(inAdultosH ? inAdultosH.value : 0, 10) || 0);
            const adA = Math.max(0, parseInt(inAdultosA ? inAdultosA.value : 0, 10) || 0);
            const jovH = Math.max(0, parseInt(inJovenesH ? inJovenesH.value : 0, 10) || 0);
            const jovA = Math.max(0, parseInt(inJovenesA ? inJovenesA.value : 0, 10) || 0);
            const nin = Math.max(0, parseInt(inNinos ? inNinos.value : 0, 10) || 0);

            const totAdultos = adH + adA;
            const totJovenes = jovH + jovA;
            const totNinos = nin;
            const totGlobal = totAdultos + totJovenes + totNinos;

            let totGrupo = 0;
            let nombreGrupo = '';

            if (tipoCelula === 'ninos') {
                totGrupo = totNinos;
                nombreGrupo = 'Solo Niños';
            } else if (tipoCelula === 'jovenes') {
                totGrupo = totJovenes;
                nombreGrupo = 'Solo Jóvenes';
            } else {
                // adultos
                totGrupo = totAdultos;
                nombreGrupo = 'Solo Adultos';
            }

            if (displayGrupo) {
                displayGrupo.textContent = totGrupo;
            }
            if (labelGrupo) {
                labelGrupo.textContent = nombreGrupo;
            }
            if (displayGlobal) {
                displayGlobal.textContent = totGlobal;
            }
            if (captionTotal) {
                let desglose = [];
                if (totAdultos > 0) desglose.push(`${totAdultos} ad.`);
                if (totJovenes > 0) desglose.push(`${totJovenes} jóv.`);
                if (totNinos > 0) desglose.push(`${totNinos} niñ.`);
                const textoDesglose = desglose.length > 0 ? ` (${desglose.join(' + ')})` : '';
                captionTotal.textContent = `Célula: ${totGrupo} | Global: ${totGlobal} personas${textoDesglose}`;
            }
        }

        [inAdultosH, inAdultosA, inJovenesH, inJovenesA, inNinos].forEach(input => {
            if (input) {
                input.addEventListener('input', recalcularAsistencia);
                input.addEventListener('change', recalcularAsistencia);
            }
        });

        recalcularAsistencia();
    }

    // Cálculo dinámico de ofrendas
    const inIgl = document.getElementById('ofrenda_iglesia');
    const inMis = document.getElementById('ofrenda_misiones');
    const inTra = document.getElementById('ofrenda_bus');
    const displayOfrendaTotal = document.getElementById('totalOfrendaDisplay');

    if (inIgl || inMis || inTra) {
        function recalcularOfrenda() {
            const igl = Math.max(0, parseFloat(inIgl ? inIgl.value : 0) || 0);
            const mis = Math.max(0, parseFloat(inMis ? inMis.value : 0) || 0);
            const tra = Math.max(0, parseFloat(inTra ? inTra.value : 0) || 0);
            const total = (igl + mis + tra).toFixed(2);

            if (displayOfrendaTotal) {
                displayOfrendaTotal.textContent = '$' + Number(total).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        [inIgl, inMis, inTra].forEach(input => {
            if (input) {
                input.addEventListener('input', recalcularOfrenda);
                input.addEventListener('change', recalcularOfrenda);
            }
        });

        recalcularOfrenda();
    }
}
