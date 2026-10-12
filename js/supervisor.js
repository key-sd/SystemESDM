document.addEventListener('DOMContentLoaded', () => {
    configurarFechaConsolidado();
    configurarTotalesConsolidado();
    iniciarModalCelulas();
});

function configurarFechaConsolidado() {
    const fecha = document.querySelector('[data-consolidated-date]');

    if (!fecha) {
        return;
    }

    fecha.addEventListener('change', () => {
        if (!fecha.value) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('vista', 'reporte');
        url.searchParams.set('fecha', fecha.value);
        window.location.assign(url.toString());
    });
}

function configurarTotalesConsolidado() {
    const filas = document.querySelectorAll('[data-consolidated-row]');

    if (!filas.length) {
        return;
    }

    const campos = ['aa', 'ah', 'ja', 'jh', 'ninos', 'conv', 'visitas', 'baut', 'oi', 'om', 'ob'];
    const totales = Object.fromEntries(campos.map((campo) => [campo, 0]));

    const actualizarTotales = () => {
        campos.forEach((campo) => {
            totales[campo] = 0;
        });

        let sumaTotalCelulas = 0;

        filas.forEach((fila) => {
            const valoresFila = {};
            const tipoInput = fila.querySelector('input[name="tipo[]"]');
            const tipoCelula = (tipoInput?.value || 'adultos').toLowerCase();

            campos.forEach((campo) => {
                const input = fila.querySelector(`[data-consolidated-input="${campo}"]`);
                const valor = Number(input?.value) || 0;
                valoresFila[campo] = valor;
                totales[campo] += valor;
            });

            const adTot = valoresFila.aa + valoresFila.ah;
            const jovTot = valoresFila.ja + valoresFila.jh;
            const ninTot = valoresFila.ninos;
            const personas = adTot + jovTot + ninTot;

            let totalCelula = adTot;
            let labelCelula = 'Adultos';
            if (tipoCelula === 'ninos') {
                totalCelula = ninTot;
                labelCelula = 'Niños';
            } else if (tipoCelula === 'jovenes') {
                totalCelula = jovTot;
                labelCelula = 'Jóvenes';
            }

            sumaTotalCelulas += totalCelula;

            const targetEl = fila.querySelector('[data-row-target-total]');
            if (targetEl) targetEl.textContent = formatearNumero(totalCelula);

            const targetLabel = fila.querySelector('[data-row-target-label]');
            if (targetLabel) targetLabel.textContent = labelCelula;

            const rowTotalEl = fila.querySelector('[data-row-total]');
            if (rowTotalEl) rowTotalEl.textContent = formatearNumero(personas);
        });

        const personas = totales.aa + totales.ah + totales.ja + totales.jh + totales.ninos;

        campos.forEach((campo) => {
            const totalColumna = document.querySelector(`[data-column-total="${campo}"]`);

            if (totalColumna) {
                totalColumna.textContent = esOfrenda(campo)
                    ? formatearDinero(totales[campo])
                    : formatearNumero(totales[campo]);
            }
        });

        const totalCelulasCol = document.querySelector('[data-column-total="celulas_total"]');
        if (totalCelulasCol) {
            totalCelulasCol.textContent = formatearNumero(sumaTotalCelulas);
        }

        const totalPersonas = document.querySelector('[data-column-total="personas"]');

        if (totalPersonas) {
            totalPersonas.textContent = formatearNumero(personas);
        }

        asignarResumen('adultos', totales.aa + totales.ah);
        asignarResumen('jovenes', totales.ja + totales.jh);
        asignarResumen('ninos', totales.ninos);
        asignarResumen('personas', personas);
        asignarResumen('ofrenda', totales.oi + totales.om + totales.ob, true);
    };

    document.querySelectorAll('[data-consolidated-input]').forEach((input) => {
        input.addEventListener('input', actualizarTotales);
    });

    actualizarTotales();
}

function asignarResumen(nombre, valor, esDinero = false) {
    const elemento = document.querySelector(`[data-sector-total="${nombre}"]`);

    if (elemento) {
        elemento.textContent = esDinero ? formatearDinero(valor) : formatearNumero(valor);
    }
}

function esOfrenda(campo) {
    return ['oi', 'om', 'ob'].includes(campo);
}

function formatearNumero(valor) {
    return new Intl.NumberFormat('es-SV', { maximumFractionDigits: 0 }).format(valor);
}

function formatearDinero(valor) {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}

function iniciarModalCelulas() {
    const modalBackdrop = document.getElementById('modalCelulaDetalle');
    const modalContent = document.getElementById('modalCelulaContenido');
    if (!modalBackdrop || !modalContent) {
        return;
    }

    const abrirModal = (celulaId) => {
        const template = document.getElementById('tmpl-celula-' + celulaId);
        if (!template) {
            return;
        }

        modalContent.innerHTML = template.innerHTML;
        modalBackdrop.style.display = 'flex';
        modalBackdrop.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        const closeBtn = modalContent.querySelector('.cell-modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', cerrarModal);
        }
    };

    const cerrarModal = () => {
        modalBackdrop.style.display = 'none';
        modalBackdrop.setAttribute('aria-hidden', 'true');
        modalContent.innerHTML = '';
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-open-cell-modal]').forEach((elem) => {
        elem.addEventListener('click', (e) => {
            if (e.target.closest('a')) {
                return;
            }
            const celulaId = elem.dataset.openCellModal;
            if (celulaId) {
                abrirModal(celulaId);
            }
        });
    });

    modalBackdrop.addEventListener('click', (e) => {
        if (e.target === modalBackdrop) {
            cerrarModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalBackdrop.style.display === 'flex') {
            cerrarModal();
        }
    });
}
