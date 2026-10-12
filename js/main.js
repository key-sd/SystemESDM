// Forzar HTTPS en producción para que el navegador habilite la PWA y Service Worker
if (location.protocol === 'http:' && !['localhost', '127.0.0.1'].includes(location.hostname)) {
    location.replace(location.href.replace(/^http:/, 'https:'));
}

document.addEventListener('DOMContentLoaded', () => {
    configurarVisibilidadPassword();
    configurarSidebarMovil();
    configurarTablasResponsivas();
    configurarFormulariosAutomaticos();
    configurarInstalacionApp();
});

function configurarInstalacionApp() {
    // Si ya estamos dentro de la app instalada (modo standalone), no mostrar el botón
    const esModoStandalone = window.matchMedia('(display-mode: standalone)').matches 
                          || window.navigator.standalone === true;
    if (esModoStandalone) return;

    // No mostrar el botón de instalación en la pantalla de login
    if (document.querySelector('.pagina-login')) return;

    // Registrar service worker si está soportado
    if ('serviceWorker' in navigator) {
        const base = window.APP_BASE_URL || '';
        navigator.serviceWorker.register(`${base}/sw.js`).catch(() => {});
    }

    let eventoInstalacion = null;

    // Escuchar el evento nativo de instalación si el navegador lo dispara
    window.addEventListener('beforeinstallprompt', (evento) => {
        evento.preventDefault();
        eventoInstalacion = evento;
    });

    // Crear el botón visible de instalación si aún no existe
    if (!document.getElementById('instalarApp')) {
        const aviso = document.createElement('button');
        aviso.id = 'instalarApp';
        aviso.className = 'install-app';
        aviso.type = 'button';
        aviso.setAttribute('title', 'Instalar aplicación ELIM SDM');
        aviso.setAttribute('aria-label', 'Instalar aplicación ELIM SDM');
        aviso.innerHTML = '<i class="ri-download-cloud-2-line"></i><span class="install-app-text">Instalar app</span>';

        let colapsarTimer = null;

        aviso.addEventListener('click', async (e) => {
            e.stopPropagation();

            const estaExpandido = aviso.classList.contains('is-expanded');
            const esTactil = window.matchMedia('(hover: none)').matches;

            // En dispositivos móviles (táctil), el primer toque expande el texto informativo
            if (!estaExpandido && esTactil) {
                aviso.classList.add('is-expanded');
                clearTimeout(colapsarTimer);
                colapsarTimer = setTimeout(() => {
                    aviso.classList.remove('is-expanded');
                }, 4000);
                return;
            }

            // Si el navegador ya tiene listo el evento de instalación nativo
            if (eventoInstalacion) {
                eventoInstalacion.prompt();
                const resultado = await eventoInstalacion.userChoice;
                if (resultado && resultado.outcome === 'accepted') {
                    aviso.remove();
                } else {
                    aviso.classList.remove('is-expanded');
                }
                return;
            }

            // Si estamos en HTTP (no seguro), redirigir a HTTPS
            if (location.protocol === 'http:' && !['localhost', '127.0.0.1'].includes(location.hostname)) {
                location.replace(location.href.replace(/^http:/, 'https:'));
                return;
            }

            // Si es iOS o el navegador requiere instalación manual:
            const esIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
            if (esIOS) {
                alert("Para instalar la app en tu iPhone:\n1. Toca el botón Compartir (cuadro con flecha arriba).\n2. Selecciona 'Agregar a pantalla de inicio'.");
            } else {
                alert("Para instalar la app:\n1. Toca el menú de 3 puntos (⋮) de tu navegador.\n2. Selecciona 'Instalar aplicación' o 'Agregar a la pantalla principal'.");
            }
        });

        // Colapsar suavemente al tocar fuera del botón
        document.addEventListener('click', (e) => {
            if (!aviso.contains(e.target)) {
                aviso.classList.remove('is-expanded');
                clearTimeout(colapsarTimer);
            }
        });

        document.body.append(aviso);
    }

    window.addEventListener('appinstalled', () => {
        document.getElementById('instalarApp')?.remove();
        eventoInstalacion = null;
    });
}

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
    document.querySelectorAll('table:not(.attendance-form-table)').forEach((tabla) => {
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
