document.addEventListener('DOMContentLoaded', () => {
    configurarVisibilidadPassword();
    configurarSidebarMovil();
    configurarTablasResponsivas();
    configurarFormulariosAutomaticos();
});

function configurarVisibilidadPassword() {
    const boton = document.getElementById('togglePass');
    const input = document.getElementById('password');
    const icono = document.getElementById('toggleIcon');

    if (!boton || !input || !icono) {
        return;
    }

    boton.addEventListener('click', () => {
        const mostrando = input.type === 'text';
        input.type = mostrando ? 'password' : 'text';
        icono.className = mostrando ? 'ri-eye-line' : 'ri-eye-off-line';
    });
}

function configurarSidebarMovil() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const botonMenu = document.getElementById('mobileMenu');

    if (!sidebar || !backdrop || !botonMenu) {
        return;
    }

    const abrirMenu = () => {
        sidebar.classList.add('open');
        backdrop.classList.add('visible');
        document.body.classList.add('menu-open');
    };

    const cerrarMenu = () => {
        sidebar.classList.remove('open');
        backdrop.classList.remove('visible');
        document.body.classList.remove('menu-open');
    };

    botonMenu.addEventListener('click', abrirMenu);
    backdrop.addEventListener('click', cerrarMenu);

    sidebar.querySelectorAll('a').forEach((enlace) => {
        enlace.addEventListener('click', cerrarMenu);
    });

    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape') {
            cerrarMenu();
        }
    });
}

function configurarTablasResponsivas() {
    document.querySelectorAll('table').forEach((tabla) => {
        const encabezados = Array.from(tabla.querySelectorAll('thead th'))
            .map((encabezado) => encabezado.textContent.trim());

        tabla.querySelectorAll('tbody tr').forEach((fila) => {
            Array.from(fila.children).forEach((celda, indice) => {
                if (!celda.dataset.label) {
                    celda.dataset.label = encabezados[indice] || 'Dato';
                }
            });
        });
    });
}

function configurarFormulariosAutomaticos() {
    document.querySelectorAll('[data-auto-submit]').forEach((control) => {
        control.addEventListener('change', () => {
            control.form?.submit();
        });
    });
}
