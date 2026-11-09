/**
 * ==========================================================================
 * LYM NOTIFICATION & DIALOG SYSTEM
 * Archivo: notifications.js
 * Sistema unificado de Toasts y Diálogos interactivos para toda la plataforma
 * ==========================================================================
 */

(function (window, document) {
    'use strict';

    // Asegurar que el archivo CSS esté cargado o inyectarlo
    function ensureStylesheetLoaded() {
        const existingLink = document.querySelector('link[href*="notifications.css"]');
        if (!existingLink) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'notifications.css';
            document.head.appendChild(link);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureStylesheetLoaded);
    } else {
        ensureStylesheetLoaded();
    }

    // Contenedor principal de toats
    function getToastContainer() {
        let container = document.getElementById('lymToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'lymToastContainer';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('role', 'region');
            container.setAttribute('aria-label', 'Notificaciones del sistema');
            document.body.appendChild(container);
        }
        return container;
    }

    // Normalizar tipos de estado
    function normalizeType(type) {
        if (!type) return 'info';
        const t = String(type).toLowerCase().trim();
        if (t === 'exito' || t === 'éxito' || t === 'ok') return 'success';
        if (t === 'advertencia' || t === 'alerta' || t === 'warn') return 'warning';
        if (t === 'peligro' || t === 'err' || t === 'fallo') return 'error';
        if (['success', 'error', 'warning', 'info'].includes(t)) return t;
        return 'info';
    }

    // Iconos SVG autónomos de alta fidelidad (no dependen de FontAwesome)
    const ICONS = {
        success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>`,
        error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>`,
        warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>`,
        info: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>`
    };

    const TITLES = {
        success: 'Operación Exitosa',
        error: 'Atención / Error',
        warning: 'Advertencia',
        info: 'Información'
    };

    function escapeHtml(str) {
        if (typeof str !== 'string') return String(str || '');
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Muestra una notificación tipo Toast emergente en la pantalla
     * @param {string|object} mensaje - Texto o mensaje a mostrar
     * @param {string} tipo - 'success' | 'error' | 'warning' | 'info'
     * @param {string} [titulo] - Título opcional
     * @param {number} [duracion=4500] - Tiempo en ms antes de auto-cerrar
     * @returns {HTMLElement} El elemento toast creado
     */
    function mostrarNotificacion(mensaje, tipo = 'info', titulo = '', duracion = 4500) {
        const container = getToastContainer();
        const normType = normalizeType(tipo);

        // Extraer texto si pasaron un objeto de error
        let textoFinal = mensaje;
        if (typeof mensaje === 'object' && mensaje !== null) {
            textoFinal = mensaje.message || mensaje.error || JSON.stringify(mensaje);
        }
        if (textoFinal === undefined || textoFinal === null) {
            textoFinal = '';
        }

        // Limpiar emojis repetitivos al inicio si ya los incluimos en el icono
        let parsedText = String(textoFinal).trim();
        parsedText = parsedText.replace(/^([✅❌⚠️ℹ️✔]\s*)+/, '');

        // Formatear saltos de línea
        const formattedMsg = escapeHtml(parsedText).replace(/\n/g, '<br>');

        const tituloFinal = titulo ? escapeHtml(titulo) : TITLES[normType];

        const toast = document.createElement('div');
        toast.className = `lym-toast lym-toast-${normType}`;
        toast.setAttribute('role', 'alert');

        toast.innerHTML = `
            <div class="lym-toast-icon-wrap" aria-hidden="true">
                ${ICONS[normType]}
            </div>
            <div class="lym-toast-body">
                <div class="lym-toast-title">${tituloFinal}</div>
                <div class="lym-toast-message">${formattedMsg}</div>
            </div>
            <button type="button" class="lym-toast-close" aria-label="Cerrar notificación" title="Cerrar">&times;</button>
            <div class="lym-toast-progress-bar" style="animation: lymProgressBar ${duracion}ms linear forwards;"></div>
        `;

        let timerId = null;
        let startTime = Date.now();
        let remainingTime = duracion;
        let isPaused = false;

        function closeToast() {
            if (timerId) clearTimeout(timerId);
            toast.classList.add('lym-toast-hiding');
            toast.addEventListener('animationend', () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, { once: true });
            // Fallback por si la animación no dispara el evento
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 350);
        }

        function startTimer() {
            startTime = Date.now();
            timerId = setTimeout(closeToast, remainingTime);
        }

        function pauseTimer() {
            if (isPaused) return;
            isPaused = true;
            clearTimeout(timerId);
            remainingTime -= (Date.now() - startTime);
            if (remainingTime < 500) remainingTime = 500;
        }

        function resumeTimer() {
            if (!isPaused) return;
            isPaused = false;
            startTimer();
        }

        // Eventos de interacción
        const closeBtn = toast.querySelector('.lym-toast-close');
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeToast();
        });

        toast.addEventListener('mouseenter', pauseTimer);
        toast.addEventListener('mouseleave', resumeTimer);

        container.appendChild(toast);
        startTimer();

        return toast;
    }

    // Atajos de conveniencia
    mostrarNotificacion.success = (msg, tit, dur) => mostrarNotificacion(msg, 'success', tit, dur);
    mostrarNotificacion.error = (msg, tit, dur) => mostrarNotificacion(msg, 'error', tit, dur);
    mostrarNotificacion.warning = (msg, tit, dur) => mostrarNotificacion(msg, 'warning', tit, dur);
    mostrarNotificacion.info = (msg, tit, dur) => mostrarNotificacion(msg, 'info', tit, dur);

    /**
     * Modal interactivo moderno que reemplaza confirm()
     * @param {string} mensaje - Pregunta o texto de confirmación
     * @param {object} [opciones] - { titulo, tipo: 'warning'|'danger'|'info', textoConfirmar, textoCancelar }
     * @returns {Promise<boolean>} Resuelve true si el usuario confirma, false si cancela
     */
    function mostrarConfirmacion(mensaje, opciones = {}) {
        return new Promise((resolve) => {
            const {
                titulo = '¿Estás seguro?',
                tipo = 'warning',
                textoConfirmar = 'Confirmar',
                textoCancelar = 'Cancelar',
                esPeligroso = false
            } = opciones;

            const overlay = document.createElement('div');
            overlay.className = 'lym-dialog-overlay';

            const normTipo = tipo === 'danger' || esPeligroso ? 'danger' : (tipo === 'info' ? 'info' : 'warning');
            const iconSvg = normTipo === 'danger' ? ICONS.error : (normTipo === 'info' ? ICONS.info : ICONS.warning);
            const btnClass = normTipo === 'danger' ? 'lym-dialog-btn-danger' : 'lym-dialog-btn-confirm';

            overlay.innerHTML = `
                <div class="lym-dialog-card" role="dialog" aria-modal="true" aria-labelledby="lymDialogTitle">
                    <div class="lym-dialog-header">
                        <div class="lym-dialog-icon ${normTipo}">
                            ${iconSvg}
                        </div>
                        <h3 class="lym-dialog-title" id="lymDialogTitle">${escapeHtml(titulo)}</h3>
                    </div>
                    <div class="lym-dialog-body">
                        ${escapeHtml(mensaje).replace(/\n/g, '<br>')}
                    </div>
                    <div class="lym-dialog-actions">
                        <button type="button" class="lym-dialog-btn lym-dialog-btn-cancel" id="lymBtnCancel">${escapeHtml(textoCancelar)}</button>
                        <button type="button" class="lym-dialog-btn ${btnClass}" id="lymBtnConfirm">${escapeHtml(textoConfirmar)}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const btnConfirm = overlay.querySelector('#lymBtnConfirm');
            const btnCancel = overlay.querySelector('#lymBtnCancel');

            btnConfirm.focus();

            function cleanup(result) {
                document.removeEventListener('keydown', handleKeyDown);
                overlay.classList.add('lym-toast-hiding');
                setTimeout(() => {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                }, 200);
                resolve(result);
            }

            function handleKeyDown(e) {
                if (e.key === 'Escape') cleanup(false);
            }

            document.addEventListener('keydown', handleKeyDown);
            btnConfirm.addEventListener('click', () => cleanup(true));
            btnCancel.addEventListener('click', () => cleanup(false));
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) cleanup(false);
            });
        });
    }

    /**
     * Modal interactivo moderno que reemplaza prompt()
     * @param {string} mensaje - Instrucción o pregunta
     * @param {string} [valorInicial=''] - Valor por defecto en el input
     * @param {object} [opciones] - { titulo, placeholder, textoConfirmar, textoCancelar }
     * @returns {Promise<string|null>} Resuelve el texto ingresado o null si se cancela
     */
    function mostrarPrompt(mensaje, valorInicial = '', opciones = {}) {
        return new Promise((resolve) => {
            const {
                titulo = 'Ingresa la información requerida',
                placeholder = '',
                textoConfirmar = 'Aceptar',
                textoCancelar = 'Cancelar'
            } = opciones;

            const overlay = document.createElement('div');
            overlay.className = 'lym-dialog-overlay';

            overlay.innerHTML = `
                <div class="lym-dialog-card" role="dialog" aria-modal="true" aria-labelledby="lymPromptTitle">
                    <div class="lym-dialog-header">
                        <div class="lym-dialog-icon info">
                            ${ICONS.info}
                        </div>
                        <h3 class="lym-dialog-title" id="lymPromptTitle">${escapeHtml(titulo)}</h3>
                    </div>
                    <div class="lym-dialog-body">
                        ${escapeHtml(mensaje).replace(/\n/g, '<br>')}
                    </div>
                    <input type="text" class="lym-dialog-input" id="lymPromptInput" value="${escapeHtml(valorInicial)}" placeholder="${escapeHtml(placeholder)}" />
                    <div class="lym-dialog-actions">
                        <button type="button" class="lym-dialog-btn lym-dialog-btn-cancel" id="lymPromptCancel">${escapeHtml(textoCancelar)}</button>
                        <button type="button" class="lym-dialog-btn lym-dialog-btn-confirm" id="lymPromptConfirm">${escapeHtml(textoConfirmar)}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const input = overlay.querySelector('#lymPromptInput');
            const btnConfirm = overlay.querySelector('#lymPromptConfirm');
            const btnCancel = overlay.querySelector('#lymPromptCancel');

            input.focus();
            input.select();

            function cleanup(result) {
                document.removeEventListener('keydown', handleKeyDown);
                overlay.classList.add('lym-toast-hiding');
                setTimeout(() => {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                }, 200);
                resolve(result);
            }

            function handleKeyDown(e) {
                if (e.key === 'Escape') cleanup(null);
                if (e.key === 'Enter') cleanup(input.value.trim());
            }

            document.addEventListener('keydown', handleKeyDown);
            btnConfirm.addEventListener('click', () => cleanup(input.value.trim()));
            btnCancel.addEventListener('click', () => cleanup(null));
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) cleanup(null);
            });
        });
    }

    // ==========================================================================
    // REEMPLAZO Y SEGURIDAD PARA ALERT NATIVO
    // ==========================================================================
    const nativeAlert = window.alert;
    window._nativeAlert = nativeAlert;

    // Sobrescribir window.alert de forma global para interceptar llamadas nativas olvidadas
    window.alert = function (mensaje) {
        const text = String(mensaje || '');
        const lower = text.toLowerCase();
        let tipo = 'info';

        if (lower.includes('error') || lower.includes('falló') || lower.includes('incorrecto') || lower.includes('❌') || lower.includes('obligatori') || lower.includes('no se pudo')) {
            tipo = 'error';
        } else if (lower.includes('atención') || lower.includes('advertencia') || lower.includes('selecciona') || lower.includes('por favor') || lower.includes('completa') || lower.includes('aviso')) {
            tipo = 'warning';
        } else if (lower.includes('éxito') || lower.includes('exito') || lower.includes('correct') || lower.includes('guardad') || lower.includes('actualizad') || lower.includes('eliminad') || lower.includes('aprob') || lower.includes('registrad') || lower.includes('gracias') || lower.includes('✅') || lower.includes('✔')) {
            tipo = 'success';
        }

        return mostrarNotificacion(text, tipo);
    };

    // Publicar APIs en el objeto window
    window.mostrarNotificacion = mostrarNotificacion;
    window.mostrarToast = mostrarNotificacion;
    window.mostrarConfirmacion = mostrarConfirmacion;
    window.mostrarPrompt = mostrarPrompt;

    // Retrocompatibilidad con sistemas CRM que llamaban showNotification(msg)
    window.showNotification = function (message, type) {
        return mostrarNotificacion(message, type || 'success');
    };

})(window, document);
