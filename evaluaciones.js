/**
 * ==============================================================
 * MODULO CLIENTE DE EVALUACIONES DE PRODUCTOS - LYM
 * ARCHIVO: evaluaciones.js
 * ==============================================================
 */

(function () {
    let selectedRating = 5;
    let currentProductoNombre = '';
    let currentProductoId = 0;

    document.addEventListener('DOMContentLoaded', () => {
        initModalHTML();
        cargarPromediosProductos();
    });

    function initModalHTML() {
        if (document.getElementById('modalEvaluacionProducto')) return;

        const modalDiv = document.createElement('div');
        modalDiv.id = 'modalEvaluacionProducto';
        modalDiv.className = 'eval-modal-overlay';
        modalDiv.innerHTML = `
            <div class="eval-modal-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px; margin-bottom: 18px;">
                    <div>
                        <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; tracking: 0.05em; color: #6B46C1; background: rgba(107, 70, 193, 0.1); padding: 4px 10px; border-radius: 20px;">Evaluación de Producto</span>
                        <h3 style="margin: 8px 0 0 0; font-size: 1.35rem; font-weight: 800; color: #0f172a;" id="evalModalTitle">Evaluar Producto</h3>
                        <div id="evalModalProductoSub" style="font-size: 0.88rem; color: #64748b; font-weight: 500; margin-top: 4px;">Selecciona tus estrellas y deja una reseña</div>
                    </div>
                    <button type="button" onclick="cerrarModalEvaluacion()" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; font-size: 1.2rem; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">&times;</button>
                </div>

                <form id="formEvalProducto" onsubmit="enviarEvaluacionProducto(event)">
                    <div style="text-align: center; margin-bottom: 18px; background: #f8fafc; padding: 16px; border-radius: 14px; border: 1px solid #f1f5f9;">
                        <label style="font-size: 0.85rem; font-weight: 700; color: #475569; display: block; text-transform: uppercase; letter-spacing: 0.03em;">¿Qué calificación le das a este producto?</label>
                        <div class="star-picker" id="starPickerContainer">
                            <i class="fas fa-star selected" data-value="1"></i>
                            <i class="fas fa-star selected" data-value="2"></i>
                            <i class="fas fa-star selected" data-value="3"></i>
                            <i class="fas fa-star selected" data-value="4"></i>
                            <i class="fas fa-star selected" data-value="5"></i>
                        </div>
                        <div id="starRatingLabel" style="font-weight: 800; color: #f59e0b; font-size: 1.05rem;">5.0 / 5.0 (Excelente)</div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 5px;">Tu Nombre o Razón Social</label>
                            <input type="text" id="evalClienteNombre" required class="crm-input" style="width:100%; box-sizing:border-box; padding:10px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size: 0.92rem; transition: border-color 0.2s;" placeholder="Ej. Ana García / Imprenta León">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 5px;">Correo Electrónico de Contacto</label>
                            <input type="email" id="evalClienteCorreo" required class="crm-input" style="width:100%; box-sizing:border-box; padding:10px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size: 0.92rem; transition: border-color 0.2s;" placeholder="Ej. contacto@cliente.com">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; display: block; margin-bottom: 5px;">Comentario Opcional sobre el producto <span style="font-weight: 400; color: #94a3b8;">(opcional)</span></label>
                            <textarea id="evalComentarios" rows="3" class="crm-input" style="width:100%; box-sizing:border-box; padding:10px 14px; border-radius:10px; border:1px solid #cbd5e1; font-size: 0.92rem; resize:vertical; transition: border-color 0.2s;" placeholder="Escribe tu opinión sobre el acabado, durabilidad o atención..."></textarea>
                        </div>
                    </div>

                    <div id="evalModalMsg" style="display: none; padding: 12px; border-radius: 10px; font-size: 0.9rem; margin-top: 14px; text-align: center; font-weight: 600;"></div>

                    <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 22px; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                        <button type="button" onclick="cerrarModalEvaluacion()" class="btn btn-outline" style="padding: 10px 20px; border-radius: 10px; font-size: 0.9rem; font-weight: 600;">Cancelar</button>
                        <button type="submit" id="btnSubmitEval" class="btn btn-primary" style="padding: 10px 24px; border-radius: 10px; font-size: 0.9rem; font-weight: 700; background: linear-gradient(135deg, #6B46C1, #8B5CF6); border: none; box-shadow: 0 4px 14px rgba(107, 70, 193, 0.35);">Enviar Evaluación</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modalDiv);

        setupStarPicker();
    }

    function setupStarPicker() {
        const container = document.getElementById('starPickerContainer');
        if (!container) return;

        const stars = container.querySelectorAll('i');
        const labels = {
            1: '1.0 / 5.0 (Deficiente)',
            2: '2.0 / 5.0 (Regular)',
            3: '3.0 / 5.0 (Bueno)',
            4: '4.0 / 5.0 (Muy Bueno)',
            5: '5.0 / 5.0 (Excelente)'
        };

        stars.forEach(star => {
            star.addEventListener('mouseover', function () {
                const val = parseInt(this.dataset.value);
                highlightStars(val);
            });

            star.addEventListener('mouseout', function () {
                highlightStars(selectedRating);
            });

            star.addEventListener('click', function () {
                selectedRating = parseInt(this.dataset.value);
                highlightStars(selectedRating);
                document.getElementById('starRatingLabel').innerText = labels[selectedRating];
            });
        });
    }

    function highlightStars(count) {
        const container = document.getElementById('starPickerContainer');
        if (!container) return;
        const stars = container.querySelectorAll('i');
        stars.forEach(star => {
            const val = parseInt(star.dataset.value);
            if (val <= count) {
                star.classList.add('selected');
            } else {
                star.classList.remove('selected');
            }
        });
    }

    window.abrirModalEvaluacion = async function (productoNombre, productoId = 0) {
        initModalHTML();
        currentProductoNombre = productoNombre;
        currentProductoId = productoId;

        document.getElementById('evalModalTitle').innerText = 'Evaluar ' + productoNombre;
        document.getElementById('evalModalProductoSub').innerText = 'Publica tu experiencia y calificación';
        document.getElementById('formEvalProducto').reset();

        selectedRating = 5;
        highlightStars(5);
        document.getElementById('starRatingLabel').innerText = '5.0 / 5.0 (Excelente)';
        document.getElementById('evalModalMsg').style.display = 'none';

        const nombreInput = document.getElementById('evalClienteNombre');
        const correoInput = document.getElementById('evalClienteCorreo');

        // Consultar la sesión real activa de la cuenta en PHP
        try {
            const resp = await fetch('php/check_session.php');
            const data = await resp.json();

            if (data.authenticated && data.user) {
                nombreInput.value = data.user.username;
                correoInput.value = data.user.email;
                nombreInput.readOnly = true;
                correoInput.readOnly = true;
                nombreInput.style.backgroundColor = '#f1f5f9';
                correoInput.style.backgroundColor = '#f1f5f9';
                document.getElementById('evalModalProductoSub').innerText = '👤 Conectado como: ' + data.user.username + ' (' + data.user.email + ')';
            } else {
                nombreInput.readOnly = false;
                correoInput.readOnly = false;
                nombreInput.style.backgroundColor = '#ffffff';
                correoInput.style.backgroundColor = '#ffffff';
            }
        } catch (e) {
            console.error('Error al verificar sesión en modal de evaluación:', e);
        }

        document.getElementById('modalEvaluacionProducto').style.display = 'flex';
    };

    window.cerrarModalEvaluacion = function () {
        const m = document.getElementById('modalEvaluacionProducto');
        if (m) m.style.display = 'none';
    };

    window.enviarEvaluacionProducto = async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitEval');
        const msg = document.getElementById('evalModalMsg');
        btn.disabled = true;
        btn.innerText = 'Enviando a MySQL...';

        const nombre = document.getElementById('evalClienteNombre').value.trim();
        const correo = document.getElementById('evalClienteCorreo').value.trim();
        const comentarios = document.getElementById('evalComentarios').value.trim();

        try {
            const response = await fetch('php/api_evaluaciones.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    producto_nombre: currentProductoNombre,
                    producto_id: currentProductoId,
                    puntuacion: selectedRating,
                    comentarios: comentarios,
                    cliente_nombre: nombre,
                    cliente_correo: correo
                })
            });

            const data = await response.json();

            if (data.success) {
                msg.style.cssText = 'display: block; background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;';
                msg.innerText = '✔ ' + data.message;

                setTimeout(() => {
                    cerrarModalEvaluacion();
                    cargarPromediosProductos();
                }, 1600);
            } else {
                msg.style.cssText = 'display: block; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
                msg.innerText = '❌ Error: ' + (data.error || 'No se pudo guardar la evaluación');
            }
        } catch (err) {
            msg.style.cssText = 'display: block; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
            msg.innerText = '❌ Error de conexión: ' + err.message;
        } finally {
            btn.disabled = false;
            btn.innerText = 'Enviar Evaluación';
        }
    };

    window.cargarPromediosProductos = async function () {
        try {
            const resp = await fetch('php/api_evaluaciones.php');
            const data = await resp.json();

            if (data.success && data.promedios) {
                const targets = document.querySelectorAll('[data-eval-producto]');
                targets.forEach(el => {
                    const prodName = el.getAttribute('data-eval-producto');
                    const info = data.promedios[prodName] || { promedio: 5.0, total: 1 };
                    el.innerHTML = `
                        <div class="producto-eval-bar">
                            <span class="producto-eval-stars">
                                <i class="fas fa-star"></i> ${info.promedio.toFixed(1)} 
                                <span class="producto-eval-count">${info.total} res.</span>
                            </span>
                            <button type="button" class="btn-evaluar-trigger" onclick="abrirModalEvaluacion('${escapeQuotes(prodName)}')">
                                <i class="fas fa-star"></i> Evaluar
                            </button>
                        </div>
                    `;
                });
            }
        } catch (e) {
            console.error('Error al cargar promedios de evaluaciones:', e);
        }
    };

    window.renderEvaluacionesProductoDetalle = async function (productoNombre, containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        try {
            const resp = await fetch(`php/api_evaluaciones.php?producto_nombre=${encodeURIComponent(productoNombre)}`);
            const data = await resp.json();

            if (data.success && Array.isArray(data.evaluaciones)) {
                if (data.evaluaciones.length === 0) {
                    container.innerHTML = `
                        <div style="padding: 20px; background: linear-gradient(135deg, #f8fafc, #f1f5f9); border-radius: 14px; border: 1px dashed #cbd5e1; text-align: center; color: #64748b;">
                            <i class="far fa-star fa-2x" style="color: #cbd5e1; margin-bottom: 8px; display: block;"></i>
                            Aún no hay evaluaciones para <strong>${escapeHtml(productoNombre)}</strong>.
                            <br><button type="button" class="btn-evaluar-trigger" style="margin-top: 12px;" onclick="abrirModalEvaluacion('${escapeQuotes(productoNombre)}')">¡Sé el primero en dejar tu reseña!</button>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 16px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <h4 style="margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a;">Opiniones de Clientes (${data.evaluaciones.length})</h4>
                            <button type="button" class="btn-evaluar-trigger" onclick="abrirModalEvaluacion('${escapeQuotes(productoNombre)}')">⭐ Evaluar este Producto</button>
                        </div>
                        ${data.evaluaciones.map(ev => `
                            <div style="padding: 14px 18px; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); font-size: 0.9rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 28px; height: 28px; border-radius: 50%; background: rgba(107, 70, 193, 0.1); color: #6B46C1; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                            ${(ev.cliente || 'C').charAt(0).toUpperCase()}
                                        </div>
                                        <strong style="color: #0f172a;">${escapeHtml(ev.cliente)}</strong>
                                    </div>
                                    <span style="color: #f59e0b; font-weight: 800; background: #fffbebf5; padding: 2px 8px; border-radius: 12px; border: 1px solid #fef3c7;">⭐ ${ev.puntuacion_satisfaccion}/5</span>
                                </div>
                                <p style="margin: 4px 0 0 0; color: #475569; font-style: ${ev.comentarios ? 'normal' : 'italic'}; line-height: 1.4;">
                                    "${escapeHtml(ev.comentarios || 'El cliente no dejó comentarios opcionales.')}"
                                </p>
                            </div>
                        `).join('')}
                    </div>
                `;
            }
        } catch (e) {
            console.error('Error al cargar detalle de evaluaciones:', e);
        }
    };

    function escapeQuotes(str) {
        return String(str || '').replace(/'/g, "\\'");
    }

    function escapeHtml(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
})();
