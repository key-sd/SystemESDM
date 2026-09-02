document.addEventListener('DOMContentLoaded', () => {
    configurarFechaConsolidado();
    configurarTotalesConsolidado();
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

        filas.forEach((fila) => {
            const valoresFila = {};

            campos.forEach((campo) => {
                const input = fila.querySelector(`[data-consolidated-input="${campo}"]`);
                const valor = Number(input?.value) || 0;
                valoresFila[campo] = valor;
                totales[campo] += valor;
            });

            const personas = valoresFila.aa + valoresFila.ah + valoresFila.ja + valoresFila.jh + valoresFila.ninos;
            fila.querySelector('[data-row-total]').textContent = formatearNumero(personas);
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
