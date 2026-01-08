// ========================================
// INICIALIZACION
// ========================================
document.addEventListener('DOMContentLoaded', function() {

    // Inicializar el grid de la base
    inicializarGrid();
    
    // Cargar edificios construidos en el grid
    cargarMisEdificiosParaGrid();

    // Iniciar generacion automatica de recursos
    iniciarGeneracionAutomatica();

    // Iniciar verificacion de oleadas
    iniciarVerificacionOleadas();
    
    // Iniciar visualizacion de enemigos
    iniciarVisualizacionEnemigos();

    // Iniciar movimiento de enemigos
    iniciarMovimientoEnemigos();
});

// ========================================
// FUNCION PARA CAMBIAR ENTRE SECCIONES
// ========================================
function mostrarSeccion(seccion) {
    
    // Ocultar todas las secciones
    const secciones = document.querySelectorAll('.seccion-juego');
    secciones.forEach(s => {
        s.style.display = 'none';
    });
    
    // Mostrar la seccion seleccionada
    const seccionMostrar = document.getElementById('seccion-' + seccion);
    if (seccionMostrar) {
        seccionMostrar.style.display = 'block';
        
        // Si es la sección de edificios, cargar los edificios
        if (seccion === 'edificios') {
            cargarEdificios();
        }
        
        // Si es la seccion de inicio, cargar el grid
        if (seccion === 'bienvenida') {
            cargarMisEdificiosParaGrid();
        }

        // Si es la seccion de unidades, cargar las unidades
        if (seccion === 'unidades') {
            cargarUnidades();
        }

    } else {
        console.error('No se encontró la sección:', 'seccion-' + seccion);
    }
}

// ==============================
// FUNCION PARA CARGAR EDIFICIOS
// ==============================
function cargarEdificios() {
    
    // Cargar mis edificios construidos
    fetch('api/buildings/get_my_buildings.php')
        .then(response => response.json())
        .then(data => {
            mostrarMisEdificios(data.terminados);
            mostrarEdificiosEnConstruccion(data.en_construccion);
        })
        .catch(error => {
            console.error('Error al cargar mis edificios:', error);
        });
    
    // Cargar edificios disponibles para construir
    fetch('api/buildings/get_available.php')
        .then(response => response.json())
        .then(edificios => {
            mostrarEdificiosDisponibles(edificios);
        })
        .catch(error => {
            console.error('Error al cargar edificios disponibles:', error);
            document.getElementById('edificios-disponibles').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error al cargar edificios.
                    </div>
                </div>
            `;
        });
}

// ===================================
// FUNCION PARA MOSTRAR MIS EDIFICIOS
// ===================================
function mostrarMisEdificios(edificios) {
    const container = document.getElementById('mis-edificios');
    
    // Si no hay edificios construidos
    if (edificios.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> Aún no tienes edificios construidos.
                </div>
            </div>
        `;
        return;
    }
    
    // Crear las tarjetas de MIS edificios
    let html = '';
    
    edificios.forEach(edificio => {
        // Determinar el emoji segun el edificio
        let emoji = '';
        let colorHeader = 'bg-success';

        switch(edificio.tipo) {
            case 'aserradero':
                emoji = '🪵';
                colorHeader = 'bg-success bg-gradient';
                break;
            case 'cantera':
                emoji = '🪨';
                colorHeader = 'bg-secondary bg-gradient';
                break;
            case 'granja':
                emoji = '🐄';
                colorHeader = 'bg-danger bg-gradient';
                break;
            case 'mina_oro':
                emoji = '⛏️';
                colorHeader = 'bg-warning bg-gradient';
                break;
            case 'ayuntamiento':
                emoji = '🏰';
                colorHeader = 'bg-info bg-gradient';
                break;
            case 'cuartel':
                emoji = '⚔️';
                colorHeader = 'bg-primary bg-gradient';
                break;
            case 'torre':
                emoji = '🏯';
                colorHeader = 'bg-dark bg-gradient';
                break;
            default:
                emoji = '🏗️';
        }
        
        // Crear la tarjeta HTML
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header ${colorHeader} text-white">
                        <h6>
                            <span style="font-size: 1.3rem;">${emoji}</span> ${edificio.nombre}
                            <span class="badge bg-light text-dark float-end">Nv. ${edificio.nivel}</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">${edificio.descripcion}</p>
                        
                        <!-- ESTADISTICAS DEL NIVEL ACTUAL -->
                        ${edificio.generacion_actual > 0 ? `
                            <div class="alert alert-info py-2 mb-2">
                                <i class="fas fa-clock"></i> Generando: <strong>${edificio.generacion_actual}/min</strong>
                            </div>
                        ` : ''}
                        
                        ${edificio.bonus_tropas_actual > 0 ? `
                            <div class="alert alert-success py-2 mb-2">
                                <i class="fas fa-users"></i> Tropas: <strong>${edificio.bonus_tropas_actual}</strong>
                            </div>
                        ` : ''}
                        
                        <!-- ESTADISTICAS DEL CUARTEL -->
                        ${edificio.tipo === 'cuartel' && edificio.colas_entrenamiento > 0 ? `
                            <div class="alert alert-primary py-2 mb-2">
                                <i class="fas fa-list"></i> Colas: <strong>${edificio.colas_entrenamiento}</strong>
                            </div>
                        ` : ''}

                        ${edificio.tipo === 'cuartel' && edificio.reduccion_tiempo_entrenamiento > 0 ? `
                            <div class="alert alert-primary py-2 mb-2">
                                <i class="fas fa-tachometer-alt"></i> Velocidad de entrenamiento: <strong>${edificio.reduccion_tiempo_entrenamiento}%</strong>
                            </div>
                        ` : ''}

                        <!-- SI PUEDE MEJORAR -->
                        ${edificio.costo_mejora_madera ? `
                            <hr>
                            <div class="mb-2">
                                <strong>Costo de mejora:</strong>
                                <div class="d-flex justify-content-around mt-2">
                                    ${edificio.costo_mejora_madera > 0 ? `<span><i class="fas fa-tree text-success"></i> ${edificio.costo_mejora_madera}</span>` : ''}
                                    ${edificio.costo_mejora_piedra > 0 ? `<span><i class="fas fa-mountain text-secondary"></i> ${edificio.costo_mejora_piedra}</span>` : ''}
                                    ${edificio.costo_mejora_comida > 0 ? `<span><i class="fas fa-bread-slice text-danger"></i> ${edificio.costo_mejora_comida}</span>` : ''}
                                </div>
                                <div class="text-center mt-2">
                                    <small><i class="fas fa-hourglass"></i> ${edificio.tiempo_mejora}s</small>
                                </div>
                            </div>
                            
                            <!-- MEJORAS DE ESTADISTICAS -->
                            ${edificio.generacion_siguiente > 0 ? `
                                <div class="alert alert-warning py-2 mb-2">
                                    <i class="fas fa-arrow-up"></i> Generará: <strong>${edificio.generacion_siguiente}/min</strong>
                                    <small class="text-muted">(+${edificio.generacion_siguiente - edificio.generacion_actual})</small>
                                </div>
                            ` : ''}
                            
                            ${edificio.bonus_tropas_siguiente > 0 ? `
                                <div class="alert alert-warning py-2 mb-2">
                                    <i class="fas fa-arrow-up"></i> Tropas: <strong>${edificio.bonus_tropas_siguiente}</strong>
                                    <small class="text-muted">(+${edificio.bonus_tropas_siguiente - edificio.bonus_tropas_actual})</small>
                                </div>
                            ` : ''}

                            <!-- MEJORAS DEL CUARTEL -->
                            ${edificio.tipo === 'cuartel' && edificio.colas_entrenamiento_siguiente > edificio.colas_entrenamiento ? `
                                <div class="alert alert-warning py-2 mb-2">
                                    <i class="fas fa-arrow-up"></i> Colas: <strong>${edificio.colas_entrenamiento_siguiente}</strong>
                                    <small class="text-muted">(+${edificio.colas_entrenamiento_siguiente - edificio.colas_entrenamiento})</small>
                                </div>
                            ` : ''}

                            ${edificio.tipo === 'cuartel' && edificio.reduccion_tiempo_entrenamiento_siguiente > edificio.reduccion_tiempo_entrenamiento ? `
                                <div class="alert alert-warning py-2 mb-2">
                                    <i class="fas fa-arrow-up"></i> Velocidad: <strong>+${edificio.reduccion_tiempo_entrenamiento_siguiente}%</strong>
                                    <small class="text-muted">(+${edificio.reduccion_tiempo_entrenamiento_siguiente - edificio.reduccion_tiempo_entrenamiento}%)</small>
                                </div>
                            ` : ''}
                            
                            <button class="btn btn-warning btn-sm w-100" onclick="mejorarEdificio(${edificio.id})">
                                <i class="fas fa-arrow-up"></i> Mejorar a Nivel ${edificio.nivel + 1}
                            </button>
                        ` : `
                            <div class="alert alert-success text-center mb-0">
                                <i class="fas fa-star"></i> ¡Nivel Máximo!
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ===============================================
// FUNCION PARA MOSTRAR EDIFICIOS EN CONSTRUCCION
// ===============================================
function mostrarEdificiosEnConstruccion(edificios) {

    const container = document.getElementById('mis-edificios');
    
    if (edificios.length === 0) {
        return; // No hay nada en construccion
    }
    
    let html = '';
    
    edificios.forEach(edificio => {
        // Emoji segun el edificio
        let emoji = edificioEmojis[edificio.tipo] || '🏗️';
        
        // Calcular porcentaje de progreso
        const tiempoTotal = edificio.tiempo_construccion;
        const tiempoRestante = Math.max(0, edificio.segundos_restantes);
        const porcentaje = Math.max(0, ((tiempoTotal - tiempoRestante) / tiempoTotal) * 100);
        
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6>
                            <span style="font-size: 1.3rem;">${emoji}</span> ${edificio.nombre}
                            <span class="badge bg-dark float-end">
                                <i class="fas fa-hammer"></i> Construyendo
                            </span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">${edificio.descripcion}</p>
                        
                        <div class="alert alert-warning py-2">
                            <strong>Tiempo restante:</strong>
                            <div class="text-center mt-2">
                                <h4 id="timer-${edificio.id}" class="mb-0">
                                    ${formatearTiempo(tiempoRestante)}
                                </h4>
                            </div>
                        </div>
                        
                        <div class="progress" style="height: 25px;">
                            <div id="progress-${edificio.id}" 
                                 class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                 role="progressbar" 
                                 style="width: ${porcentaje}%">
                                ${Math.round(porcentaje)}%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    // Agregar al principio del container (antes de los edificios terminados)
    container.insertAdjacentHTML('afterbegin', html);
    
    // Iniciar el contador regresivo
    edificios.forEach(edificio => {
        iniciarContador(edificio.id, edificio.segundos_restantes, edificio.tiempo_construccion);
    });
}

// ===============================
// FUNCION PARA MOSTRAR EDIFICIOS
// ===============================
function mostrarEdificiosDisponibles(edificios) {
    const container = document.getElementById('edificios-disponibles');
    
    // Si no hay edificios
    if (edificios.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No hay edificios disponibles.
                </div>
            </div>
        `;
        return;
    }
    
    // Crear las tarjetas de edificios
    let html = '';
    
    edificios.forEach(edificio => {
        // Determinar el emoji segun el edificio
        let emoji = edificioEmojis[edificio.tipo] || '🏗️';
        
        // Crear la tarjeta HTML
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6><span style="font-size: 1.3rem;">${emoji}</span> ${edificio.nombre}</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">${edificio.descripcion}</p>
                        
                        <div class="mb-2">
                            <strong>Costos:</strong>
                            <div class="d-flex justify-content-around mt-2">
                                ${edificio.costos.madera > 0 ? `<span><i class="fas fa-tree text-success"></i> ${edificio.costos.madera}</span>` : ''}
                                ${edificio.costos.piedra > 0 ? `<span><i class="fas fa-mountain text-secondary"></i> ${edificio.costos.piedra}</span>` : ''}
                                ${edificio.costos.comida > 0 ? `<span><i class="fas fa-bread-slice text-danger"></i> ${edificio.costos.comida}</span>` : ''}
                            </div>
                        </div>
                        
                        ${edificio.generacion > 0 ? `
                            <div class="mb-2">
                                <small><i class="fas fa-clock"></i> Genera: <strong>${edificio.generacion}/min</strong></small>
                            </div>
                        ` : ''}
                        
                        <div class="mb-2">
                            <small><i class="fas fa-hourglass"></i> Tiempo: <strong>${edificio.tiempo}s</strong></small>
                        </div>
                        
                        <div class="mb-3">
                            <small>Construidos: <strong>${edificio.construidos}/${edificio.limite}</strong></small>
                        </div>
                        
                        ${edificio.puede_construir ? `
                            <button class="btn btn-success btn-sm w-100" onclick="construirEdificio(${edificio.id})">
                                <i class="fas fa-hammer"></i> Construir
                            </button>
                        ` : `
                            <button class="btn btn-secondary btn-sm w-100" disabled>
                                <i class="fas fa-lock"></i> Límite alcanzado
                            </button>
                        `}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ================================
// FUNCION PARA CONSTRUIR EDIFICIO
// ================================
function construirEdificio(edificioId) {
    // Mostrar confirmacion de construccion
    mostrarConfirmacion(
        '¿Estás seguro de que quieres construir este edificio?',
        () => {
            // Si confirma, construir
            fetch('api/buildings/build.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    edificio_id: edificioId
                })
            })
            .then(response => response.json())
            .then(data => {        
                if (data.success) {
                    // Mostrar mensaje de exito
                    mostrarNotificacion(
                        `¡Edificio en construcción! Tiempo: ${data.tiempo_construccion}s`,
                        'success'
                    );
                    
                    // Actualizar recursos en pantalla
                    actualizarRecursos(data.recursos);
                    
                    // Recargar edificios
                    cargarEdificios();
                } else {
                    // Mostrar error
                    mostrarNotificacion(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al construir el edificio', 'error');
            });
        }
    );
}

// ==============================
// FUNCION PARA MEJORAR EDIFICIO
// ==============================
function mejorarEdificio(edificioId) {  
    // Confirmar mejora
    mostrarConfirmacion(
        '¿Estás seguro de que quieres mejorar este edificio?',
        () => {
            // Si confirma, mejorar
            fetch('api/buildings/upgrade.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    edificio_id: edificioId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostrar mensaje de éxito
                    mostrarNotificacion(
                        `¡Edificio mejorando! Tiempo: ${data.tiempo_construccion}s`,
                        'success'
                    );
                    
                    // Actualizar recursos en pantalla
                    actualizarRecursos(data.recursos);
                    
                    // Recargar edificios
                    cargarEdificios();
                } else {
                    // Mostrar error
                    mostrarNotificacion(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al mejorar el edificio', 'error');
            });
        }
    );
}

// =============================================
// FUNCION PARA ACTUALIZAR RECURSOS EN PANTALLA
// =============================================
function actualizarRecursos(recursos) {
    document.getElementById('oro').textContent = Number(recursos.oro).toLocaleString();
    document.getElementById('madera').textContent = Number(recursos.madera).toLocaleString();
    document.getElementById('piedra').textContent = Number(recursos.piedra).toLocaleString();
    document.getElementById('comida').textContent = Number(recursos.comida).toLocaleString();
}

// ================================
// FUNCIONES DE CONTADOR REGRESIVO
// ================================
function formatearTiempo(segundos) {  // Formatear segundos a mm:ss
    if (segundos <= 0) return '00:00';
    const minutos = Math.floor(segundos / 60);
    const segs = segundos % 60;
    return `${String(minutos).padStart(2, '0')}:${String(segs).padStart(2, '0')}`;
}

// Iniciar contador regresivo
function iniciarContador(edificioId, segundosRestantes, tiempoTotal) {
    let tiempoRestante = segundosRestantes;
    
    const intervalo = setInterval(() => {
        tiempoRestante--;
        
        const timerElement = document.getElementById(`timer-${edificioId}`);
        const progressElement = document.getElementById(`progress-${edificioId}`);
        
        if (timerElement) {
            timerElement.textContent = formatearTiempo(tiempoRestante);
        }
        
        // Actualizar barra de progreso
        if (progressElement && tiempoTotal > 0) {
            const porcentaje = Math.max(0, Math.min(100, ((tiempoTotal - tiempoRestante) / tiempoTotal) * 100));
            progressElement.style.width = porcentaje + '%';
            progressElement.textContent = Math.round(porcentaje) + '%';
        }
        
        if (tiempoRestante <= 0) {
            clearInterval(intervalo); // Detener el intervalo
            finalizarConstruccion(edificioId);
        }
    }, 1000);
}

// Finalizar construccion
let construccionesFinalizadas = new Set(); // Para evitar duplicados

function finalizarConstruccion(edificioId) {
    // Verificar si ya se finalizo este edificio
    if (construccionesFinalizadas.has(edificioId)) {
        return;
    }
    
    construccionesFinalizadas.add(edificioId);
    
    // Llamar al API para finalizar en la base de datos
    fetch('api/buildings/finish_construction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        
        if (data.success && data.finalizados > 0) {
            mostrarNotificacion('¡Construcción completada!', 'success');
            // Recargar todos los edificios
            cargarEdificios();
        }
    })
    .catch(error => {
        console.error('Error al finalizar construcción:', error);
    });
    
    // Limpiar despues de 5 segundos
    setTimeout(() => {
        construccionesFinalizadas.delete(edificioId);
    }, 5000);
}

// ==============================
// SISTEMA DE GRID PARA LA BASE
// ==============================

// Mapeo de tipos de edificios a emojis (TEMPORAL)
const edificioEmojis = {
    'ayuntamiento': '🏰',
    'aserradero': '🪵',
    'cantera': '🪨',
    'granja': '🐄',
    'mina_oro': '⛏️',
    'cuartel': '⚔️',
    'torre': '🏯'
};

// Mapeo de tipos de enemigos a emojis
const enemigoEmojis = {
    'goblin': '👺',
    'orco': '👹',
    'troll': '🧌',
    'esqueleto': '🩻',
    'dragon': '🐉'
};

// Inicializar el grid
function inicializarGrid() {
    const grid = document.getElementById('base-grid');
    grid.innerHTML = '';
    
    // Crear 9x9 = 81 casillas
    for (let i = 0; i < 81; i++) {
        const cell = document.createElement('div');
        cell.className = 'grid-cell';
        cell.dataset.position = i;
        
        // Marcar la casilla central (posicion 40 en un grid de 9x9, fila 4 columna 4)
        if (i === 40) {
            cell.classList.add('center');
        }
        
        grid.appendChild(cell);
    }
}

// Cargar edificios en el grid
function cargarEdificiosEnGrid(edificios) {
    console.log('Cargando edificios en el grid:', edificios);
    
    // Limpiar edificios actuales
    document.querySelectorAll('.grid-cell').forEach(cell => {
        cell.classList.remove('occupied');
        cell.innerHTML = '';
    });
    
    // Guardar las posiciones ya ocupadas en esta carga
    const posicionesOcupadas = new Set();
    
    edificios.forEach(edificio => {
        let posicion = edificio.posicion_x || null;
        
        // Si es ayuntamiento y no tiene posicion, ponerlo en el centro
        if (edificio.tipo === 'ayuntamiento' && !posicion) {
            posicion = 40; // Centro del grid 9x9
            actualizarPosicionEdificio(edificio.id, posicion);
        }
        
        // Si no tiene posicion o la posicion ya esta ocupada, asignar una nueva
        if (!posicion || posicionesOcupadas.has(posicion)) {
            posicion = encontrarPosicionLibre(posicionesOcupadas);
            actualizarPosicionEdificio(edificio.id, posicion);
        }
        
        // Marcar la posicion como ocupada
        posicionesOcupadas.add(posicion);
        
        // Colocar el edificio en el grid
        const cell = document.querySelector(`[data-position="${posicion}"]`);
        if (cell) {
            cell.classList.add('occupied');
            
            const emoji = edificioEmojis[edificio.tipo] || '🏗️';
            cell.innerHTML = `
                <span class="building-emoji">${emoji}</span>
                <span class="building-level">Nv.${edificio.nivel}</span>
            `;
            
            // Agregar click para mostrar info
            cell.onclick = () => mostrarInfoEdificio(edificio);
        }
    });
}

// Encontrar una posicion libre en el grid, priorizando cercania al centro
function encontrarPosicionLibre(posicionesYaOcupadas = new Set()) {
    // Obtener casillas ocupadas
    const casillasOcupadas = Array.from(document.querySelectorAll('.grid-cell.occupied'))
        .map(cell => parseInt(cell.dataset.position));
    
    // Combinar con las posiciones ya asignadas en esta carga
    const todasOcupadas = new Set([...casillasOcupadas, ...posicionesYaOcupadas]);
    
    const centro = 40;
    
    // Posiciones alrededor del centro (9x9, centro = 40)
    const posicionesPrioritarias = [
        39, 41, 31, 49,         // Lados directos (izq, der, arriba, abajo)
        30, 32, 48, 50,         // Diagonales
        29, 33, 38, 42, 47, 51, // Segunda capa
        21, 22, 23, 57, 58, 59  // Tercera capa
    ];
    
    // Buscar en posiciones prioritarias
    for (let pos of posicionesPrioritarias) {
        if (!todasOcupadas.has(pos) && pos !== centro) {
            return pos;
        }
    }
    
    // Si no hay espacio cerca, buscar cualquier posicion libre
    for (let i = 0; i < 81; i++) {
        if (!todasOcupadas.has(i) && i !== centro) {
            return i;
        }
    }
    
    return 0;
}

// Actualizar posicion del edificio en la BD
function actualizarPosicionEdificio(edificioId, posicion) {
    fetch('api/buildings/update_position.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            edificio_id: edificioId,
            posicion: posicion
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Posición actualizada:', data);
    })
    .catch(error => {
        console.error('Error al actualizar posición:', error);
    });
}

// Mostrar info del edificio al hacer click
function mostrarInfoEdificio(edificio) {
    const emoji = edificioEmojis[edificio.tipo] || '🏗️';
    
    // Construir el mensaje con stats
    let mensaje = `
        <strong>${emoji} ${edificio.nombre}</strong><br>
        <span class="badge bg-primary">Nivel ${edificio.nivel}</span><br>
        <small class="text-muted">${edificio.descripcion}</small>
    `;
    
    // Agregar estadisticas de generacion
    if (edificio.generacion_actual > 0) {
        mensaje += `<br><small><i class="fas fa-clock text-info"></i> Generando: <strong>${edificio.generacion_actual}/min</strong></small>`;
    }
    
    // Agregar estadisticas de bonus de tropas
    if (edificio.bonus_tropas_actual > 0) {
        mensaje += `<br><small><i class="fas fa-users text-success"></i> Tropas: <strong>+${edificio.bonus_tropas_actual}</strong></small>`;
    }
    
    // Agregar estadisticas del cuartel
    if (edificio.tipo === 'cuartel') {
        if (edificio.colas_entrenamiento > 0) {
            mensaje += `<br><small><i class="fas fa-list text-primary"></i> Colas de entrenamiento: <strong>${edificio.colas_entrenamiento}</strong></small>`;
        }
        if (edificio.reduccion_tiempo_entrenamiento > 0) {
            mensaje += `<br><small><i class="fas fa-tachometer-alt text-primary"></i> Velocidad de entrenamiento: <strong>+${edificio.reduccion_tiempo_entrenamiento}%</strong></small>`;
        }
        
        // Mostrar que unidades puede entrenar segun el nivel
        const unidadesDesbloqueadas = [];
        if (edificio.nivel >= 1) unidadesDesbloqueadas.push('Elfo');
        if (edificio.nivel >= 2) unidadesDesbloqueadas.push('Arquero');
        if (edificio.nivel >= 3) unidadesDesbloqueadas.push('Phoenix');
        if (edificio.nivel >= 4) unidadesDesbloqueadas.push('Mago');
        
        if (unidadesDesbloqueadas.length > 0) {
            mensaje += `<br><small><i class="fas fa-unlock text-warning"></i> Unidades disponibles: <strong>${unidadesDesbloqueadas.join(', ')}</strong></small>`;
        }
    }
    
    mostrarNotificacion(mensaje, 'info', 6000);
}

// Cargar mis edificios terminados para el grid
function cargarMisEdificiosParaGrid() {
    fetch('api/buildings/get_my_buildings.php')
        .then(response => response.json())
        .then(data => {
            // Solo cargar edificios terminados en el grid
            cargarEdificiosEnGrid(data.terminados);
        })
        .catch(error => {
            console.error('Error al cargar edificios para grid:', error);
        });
}

// =============================================
// SISTEMA DE GENERACION AUTOMATICA DE RECURSOS
// =============================================

// Iniciar generacion automatica
function iniciarGeneracionAutomatica() {
    console.log('Sistema de generación automática iniciado');

    // Generar recursos cada 1 minuto
    setInterval(() => {
        generarRecursos();
    }, 60000); // 60000 milisegundos = 1 minuto
}

// Llamar al API para generar recursos
function generarRecursos() {
    fetch('api/resources/generate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Recursos generados:', data.generados);
            
            // Actualizar recursos en pantalla
            actualizarRecursos(data.recursos_actuales);
            
            // Mostrar notificacion de recursos generados
            mostrarNotificacionRecursos(data.generados);
        }
    })
    .catch(error => {
        console.error('Error al generar recursos:', error);
    });
}

// Mostrar notificacion de recursos generados
function mostrarNotificacionRecursos(generados) {
    let recursos = [];
    
    if (generados.madera > 0) recursos.push(`🪵 +${generados.madera} Madera`);
    if (generados.piedra > 0) recursos.push(`🪨 +${generados.piedra} Piedra`);
    if (generados.comida > 0) recursos.push(`🍖 +${generados.comida} Comida`);
    if (generados.oro > 0) recursos.push(`🪙 +${generados.oro} Oro`);
    
    // Solo mostrar si se genero algo
    if (recursos.length > 0) {
        const container = document.getElementById('notificaciones-container');
        
        // Crear la notificacion
        const notificacion = document.createElement('div');
        notificacion.className = 'alert alert-success alert-dismissible fade show';
        notificacion.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
        notificacion.innerHTML = `
            <strong><i class="fas fa-coins"></i> Recursos generados!</strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <hr>
            <small>${recursos.join('<br>')}</small>
        `;
        
        // Agregar al contenedor
        container.appendChild(notificacion);
        
        // Desaparecer despues de 30 segundos
        setTimeout(() => {
            notificacion.remove();
        }, 30000);
    }
}

// ================================
// SISTEMA DE NOTIFICACIONES TOAST
// ================================

// Mostrar notificacion toast
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
    const container = document.getElementById('notificaciones-container');
    
    // Determinar el estilo segun el tipo
    let claseAlerta = 'alert-info';
    let icono = 'fa-info-circle';
    
    switch(tipo) {
        case 'success':
            claseAlerta = 'alert-success';
            icono = 'fa-check-circle';
            break;
        case 'error':
            claseAlerta = 'alert-danger';
            icono = 'fa-exclamation-circle';
            break;
        case 'warning':
            claseAlerta = 'alert-warning';
            icono = 'fa-exclamation-triangle';
            break;
        case 'info':
            claseAlerta = 'alert-info';
            icono = 'fa-info-circle';
            break;
    }
    
    // Crear la notificacion
    const notificacion = document.createElement('div');
    notificacion.className = `alert ${claseAlerta} alert-dismissible fade show mb-2`;
    notificacion.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
    notificacion.innerHTML = `
        <i class="fas ${icono}"></i> ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Agregar al contenedor
    container.appendChild(notificacion);
    
    // Desaparecer despues de la duracion
    setTimeout(() => {
        notificacion.classList.remove('show');
        setTimeout(() => notificacion.remove(), 150);
    }, duracion);
}

// Mostrar confirmacion con botones
function mostrarConfirmacion(mensaje, onConfirm, onCancel = null) {
    const container = document.getElementById('notificaciones-container');
    
    // Crear la notificacion con botones
    const notificacion = document.createElement('div');
    notificacion.className = 'alert alert-warning alert-dismissible fade show mb-2';
    notificacion.style.boxShadow = '0 4px 15px rgba(0,0,0,0.3)';
    notificacion.innerHTML = `
        <i class="fas fa-question-circle"></i> ${mensaje}
        <div class="mt-2">
            <button class="btn btn-success btn-sm me-2" id="btn-confirmar">
                <i class="fas fa-check"></i> Confirmar
            </button>
            <button class="btn btn-secondary btn-sm" id="btn-cancelar">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
    `;
    
    // Agregar al contenedor
    container.appendChild(notificacion);
    
    // Eventos de los botones
    notificacion.querySelector('#btn-confirmar').onclick = () => {
        notificacion.remove();
        if (onConfirm) onConfirm();
    };
    
    notificacion.querySelector('#btn-cancelar').onclick = () => {
        notificacion.remove();
        if (onCancel) onCancel();
    };
}

// ===================
// SISTEMA DE TROPAS
// ===================

// Cargar unidades disponibles
function cargarUnidades() {
    console.log('Cargando unidades desde el servidor...');
    
    fetch('api/units/get_available.php')
        .then(response => response.json())
        .then(data => {
            mostrarUnidades(data);
            mostrarUnidadesEnEntrenamiento();
            actualizarContadorTropas();
        })
        .catch(error => {
            console.error('Error al cargar unidades:', error);
        });
}

// Mostrar unidades en la interfaz
function mostrarUnidades(data) {
    const container = document.getElementById('seccion-unidades');
    
    // Si no tiene cuartel
    if (!data.tiene_cuartel) {
        container.innerHTML = `
            <h4><i class="fas fa-users"></i> Entrenamiento de Unidades</h4>
            <hr>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Necesitas construir un Cuartel</strong> para entrenar unidades.
                <br><small>El Cuartel se desbloquea al mejorar tu Ayuntamiento a Nivel 2.</small>
            </div>
        `;
        return;
    }
    
    // Si no hay unidades disponibles
    if (data.unidades.length === 0) {
        container.innerHTML = `
            <h4><i class="fas fa-users"></i> Entrenamiento de Unidades</h4>
            <hr>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> No hay unidades disponibles.
            </div>
        `;
        return;
    }
    
    // Crear el HTML
    let html = `
        <h4><i class="fas fa-users"></i> Entrenamiento de Unidades</h4>
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-landmark"></i> <strong>Cuartel Nivel ${data.nivel_cuartel}</strong>
                </div>
            </div>
            <div class="col-md-6">
                <div class="alert alert-success mb-0">
                    <i class="fas fa-users"></i> <strong>Límite de tropas: <span id="tropas-actuales">?</span> / <span id="tropas-limite">?</span></strong>
                </div>
            </div>
        </div>
        <hr>
        <div class="row">
    `;

    // Mapeo de tipos a iconos, emojis y colores
    const unidadConfig = {
        'elfo': { icono: 'fa-shield', emoji: '🧝🏻‍♂️', color: 'secondary' },
        'arquero': { icono: 'fa-crosshairs', emoji: '🏹', color: 'success' },
        'phoenix': { icono: 'fa-horse-head', emoji: '🐦‍🔥', color: 'danger' },
        'mago': { icono: 'fa-hat-wizard', emoji: '🧙‍♂', color: 'primary' }
    };
    
    data.unidades.forEach(unidad => {
        const config = unidadConfig[unidad.tipo] || { icono: 'fa-user', color: 'secondary' };
        
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header bg-${config.color} text-white text-center">
                        <div style="font-size: 3rem; margin: 10px 0;">${config.emoji}</div>
                        <h5 class="mb-0">${unidad.nombre}</h5>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">${unidad.descripcion}</p>
                        
                        <!-- Estadísticas -->
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="bg-danger bg-opacity-10 p-2 rounded">
                                    <div style="font-size: 1.5rem;">⚔️</div>
                                    <small class="text-muted d-block">Ataque</small>
                                    <strong class="text-danger fs-5">${unidad.ataque}</strong>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-success bg-opacity-10 p-2 rounded">
                                    <div style="font-size: 1.5rem;">❤️</div>
                                    <small class="text-muted d-block">Vida</small>
                                    <strong class="text-success fs-5">${unidad.vida}</strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Costos -->
                        <div class="mb-2">
                            <strong class="d-block mb-2">Costo por unidad:</strong>
                            <div class="row">
                                <div class="col-6">
                                    <div class="alert alert-warning py-2 mb-0 text-center">
                                        <span style="font-size: 1.2rem;">🪙</span>
                                        <strong class="d-block">${unidad.costo_oro}</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="alert alert-danger py-2 mb-0 text-center">
                                        <span style="font-size: 1.2rem;">🍖</span>
                                        <strong class="d-block">${unidad.costo_comida}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-2 text-center">
                            <small><i class="fas fa-hourglass"></i> Tiempo: <strong>${unidad.tiempo_entrenamiento}s</strong></small>
                        </div>
                        
                        <!-- Cantidad actual -->
                        <div class="alert alert-secondary text-center py-2 mb-2">
                            <small>Tienes: <strong>${unidad.cantidad_actual}</strong></small>
                        </div>
                        
                        <!-- Input de cantidad -->
                        <div class="input-group mb-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="cambiarCantidad(${unidad.id}, -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" 
                                   id="cantidad-${unidad.id}" 
                                   class="form-control form-control-sm text-center" 
                                   value="1" 
                                   min="1" 
                                   max="100">
                            <button class="btn btn-outline-secondary btn-sm" onclick="cambiarCantidad(${unidad.id}, 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        
                        <!-- Boton entrenar -->
                        <button class="btn btn-success btn-sm w-100" onclick="entrenarUnidad(${unidad.id})">
                            <i class="fas fa-plus-circle"></i> Entrenar
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += `</div>`;
    
    container.innerHTML = html;
}

// Cambiar cantidad a entrenar
function cambiarCantidad(unidadId, cambio) {
    const input = document.getElementById(`cantidad-${unidadId}`);
    let valor = parseInt(input.value) || 1;
    valor += cambio;
    
    if (valor < 1) valor = 1;
    if (valor > 100) valor = 100;
    
    input.value = valor;
}

// Entrenar unidad
function entrenarUnidad(unidadId) {
    const cantidadInput = document.getElementById(`cantidad-${unidadId}`);
    const cantidad = parseInt(cantidadInput.value) || 1;
    
    // Confirmar entrenamiento
    mostrarConfirmacion(
        `¿Entrenar ${cantidad} unidad(es)?`,
        () => {
            // Si confirma, entrenar
            fetch('api/units/train.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    unidad_id: unidadId,
                    cantidad: cantidad
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Respuesta del servidor:', data);
                
                if (data.success) {
                    // Mostrar mensaje de exito
                    mostrarNotificacion(
                        `¡Entrenamiento iniciado! Tiempo: ${data.tiempo_entrenamiento}s`,
                        'success'
                    );
                    
                    // Actualizar recursos en pantalla
                    actualizarRecursos(data.recursos);
                    
                    // Recargar unidades
                    cargarUnidades();
                } else {
                    // Mostrar error
                    mostrarNotificacion(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarNotificacion('Error al entrenar unidad', 'error');
            });
        }
    );
}

// Mostrar cola de entrenamiento
function mostrarUnidadesEnEntrenamiento() {
    fetch('api/units/get_queue.php')
        .then(response => response.json())
        .then(cola => {
            if (cola.length === 0) return;
            
            // Mapeo de emojis
            const unidadEmojis = {
                'elfo': '🧝🏻‍♂️',
                'arquero': '🏹',
                'phoenix': '🐦‍🔥',
                'mago': '🧙‍♂'
            };
            
            // Agregar seccion de cola si no existe
            let container = document.getElementById('cola-entrenamiento');
            if (!container) {
                const seccion = document.getElementById('seccion-unidades');
                const nuevoDiv = document.createElement('div');
                nuevoDiv.id = 'cola-entrenamiento';
                seccion.insertBefore(nuevoDiv, seccion.firstChild);
                container = nuevoDiv;
            }
            
            let html = '<h5 class="mb-3"><i class="fas fa-hourglass-half"></i> Cola de Entrenamiento:</h5><div class="row">';
            
            // Mostrar cada unidad en la cola
            cola.forEach((unidad, index) => {
                const segundos = Math.max(0, unidad.segundos_restantes);
                const emoji = unidadEmojis[unidad.tipo] || '🛡️';
                const enProceso = index === 0; // Solo la primera esta entrenandose
                
                html += `
                    <div class="col-md-6 col-lg-3 mb-3">
                        <div class="card ${enProceso ? 'border-warning' : 'border-secondary'}">
                            <div class="card-header ${enProceso ? 'bg-warning' : 'bg-secondary'} text-white text-center">
                                <div style="font-size: 2rem;">${emoji}</div>
                                <small class="d-block">${unidad.nombre}</small>
                                ${enProceso ? '<span class="badge bg-dark mt-1"><i class="fas fa-spinner fa-spin"></i> Entrenando</span>' : '<span class="badge bg-dark mt-1"><i class="fas fa-clock"></i> En cola</span>'}
                            </div>
                            <div class="card-body p-2">
                                ${enProceso ? `
                                    <div class="text-center mb-2">
                                        <small class="text-muted">Tiempo restante:</small>
                                        <h6 id="timer-queue-${unidad.id}" class="mb-0 text-primary">
                                            ${formatearTiempo(segundos)}
                                        </h6>
                                    </div>
                                    <div class="progress" style="height: 15px;">
                                        <div id="progress-queue-${unidad.id}" 
                                             class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                                             role="progressbar" 
                                             style="width: 5%">
                                        </div>
                                    </div>
                                ` : `
                                    <div class="text-center">
                                        <small class="text-muted">Posición: ${index + 1}</small>
                                    </div>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div><hr>';
            container.innerHTML = html;
            
            // Iniciar contador solo para la primera unidad (la que esta entrenandose)
            if (cola.length > 0) {
                const primeraUnidad = cola[0];
                const tiempoTotal = 30; // Obtener del catalogo o base de datos segun el tipo de unidad
                iniciarContadorCola(primeraUnidad.id, Math.max(0, primeraUnidad.segundos_restantes), tiempoTotal);
            }
        })
        .catch(error => {
            console.error('Error al cargar cola de entrenamiento:', error);
        });
}

// Contador para cola de entrenamiento (solo primera unidad)
const intervaloCola = {};

function iniciarContadorCola(colaId, segundosRestantes, tiempoTotal) {
    // Limpiar intervalo anterior si existe
    if (intervaloCola.actual) {
        clearInterval(intervaloCola.actual);
    }
    
    let tiempoRestante = segundosRestantes;
    
    const intervalo = setInterval(() => {
        tiempoRestante--;
        
        const timerElement = document.getElementById(`timer-queue-${colaId}`);
        const progressElement = document.getElementById(`progress-queue-${colaId}`);
        
        if (timerElement) {
            timerElement.textContent = formatearTiempo(tiempoRestante);
        }
        
        // Actualizar barra de progreso
        if (progressElement && tiempoTotal > 0) {
            const porcentaje = Math.max(0, Math.min(100, ((tiempoTotal - tiempoRestante) / tiempoTotal) * 100));
            progressElement.style.width = porcentaje + '%';
            progressElement.textContent = Math.round(porcentaje) + '%';
        }
        
        if (tiempoRestante <= 0) {
            clearInterval(intervalo);
            finalizarUnidadCola();
        }
    }, 1000);
    
    intervaloCola.actual = intervalo;
}

// Finalizar unidad de la cola
function finalizarUnidadCola() {    
    fetch('api/units/finish_queue.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {     
        if (data.success && data.finalizadas > 0) {
            mostrarNotificacion('¡Unidad lista para combatir!', 'success');
            cargarUnidades(); // Recargar todo
        }
    })
    .catch(error => {
        console.error('Error al finalizar unidad:', error);
    });
}

// Actualizar contador de tropas
function actualizarContadorTropas() {
    fetch('api/units/get_troop_count.php')
        .then(response => response.json())
        .then(data => {
            const actualElement = document.getElementById('tropas-actuales');
            const limiteElement = document.getElementById('tropas-limite');
                
            if (actualElement) actualElement.textContent = data.tropas_actuales;
            if (limiteElement) limiteElement.textContent = data.limite_tropas;
        });
}

// ===============================
// SISTEMA DE OLEADAS AUTOMATICAS
// ===============================

// Variable global para rastrear el estado de las oleadas
let estadoOleada = {
    verificandoOleadas: false,
    alertaMostrada: false,
    oleadaEnCurso: false
};

// Iniciar el sistema de verificacion de oleadas cuando carga la pagina
document.addEventListener('DOMContentLoaded', function() {
    iniciarVerificacionOleadas();
});

// Funcion que inicia el bucle de verificacion automatica
function iniciarVerificacionOleadas() {
    console.log('Sistema de verificación de oleadas iniciado');
    
    // Verificar inmediatamente al cargar
    verificarOleadas();
    
    // Luego verificar cada 10 segundos
    setInterval(() => {
        verificarOleadas();
    }, 10000); // 10000 milisegundos = 10 segundos
}

// Funcion que verifica si es momento de generar una oleada
function verificarOleadas() {
    // Evitar multiples verificaciones a la vez
    if (estadoOleada.verificandoOleadas) {
        return;
    }
    
    estadoOleada.verificandoOleadas = true;
    
    fetch('api/wave/check_wave.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Actualizar el estado global
                estadoOleada.oleadaEnCurso = data.oleada_en_curso;
                
                // Si debe generar la oleada, hacerlo inmediatamente
                if (data.debe_generar_oleada) {
                    generarOleada();
                }
                // Si debe mostrar alerta y no se ha mostrado aun
                else if (data.debe_mostrar_alerta && !estadoOleada.alertaMostrada) {
                    mostrarAlertaPreOleada(data.oleada_actual, data.segundos_restantes);
                    estadoOleada.alertaMostrada = true;
                }
                // Si hay tiempo restante, mostrar temporizador en consola para debug
                else if (data.segundos_restantes > 60) {
                    console.log(`Próxima oleada ${data.oleada_actual} en ${formatearTiempo(data.segundos_restantes)}`);
                }
            }
            
            estadoOleada.verificandoOleadas = false;
        })
        .catch(error => {
            console.error('Error al verificar oleadas:', error);
            estadoOleada.verificandoOleadas = false;
        });
}

// Funcion que genera la oleada llamando al API
function generarOleada() {
    fetch('api/wave/generate_wave.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Oleada generada exitosamente:', data);
            
            // Mostrar notificacion
            mostrarNotificacionOleada(data);
            
            // Resetear la bandera de alerta para la proxima oleada
            estadoOleada.alertaMostrada = false;
            estadoOleada.oleadaEnCurso = true;
            
            // TODO: Iniciar el sistema de movimiento y combate de enemigos
            // Esto lo implementaremos en el proximo paso
            
        } else {
            console.error('Error al generar oleada:', data.error);
        }
    })
    .catch(error => {
        console.error('Error en la petición de generación de oleada:', error);
    });
}

// Mostrar alerta de advertencia 1 minuto antes de la oleada
function mostrarAlertaPreOleada(numeroOleada, segundosRestantes) {
    const container = document.getElementById('notificaciones-container');
    
    // Crear una notificacion especial mas grande y llamativa
    const alerta = document.createElement('div');
    alerta.className = 'alert alert-danger alert-dismissible fade show mb-2';
    alerta.style.cssText = 'box-shadow: 0 8px 25px rgba(220, 53, 69, 0.5); border: 3px solid #dc3545; font-size: 1.1rem;';
    alerta.innerHTML = `
        <div class="d-flex align-items-center">
            <div style="font-size: 3rem; margin-right: 15px;">⚠️</div>
            <div class="flex-grow-1">
                <h5 class="mb-1"><strong>¡ALERTA DE INVASIÓN!</strong></h5>
                <p class="mb-1">Una oleada de enemigos se aproxima en <strong id="contador-alerta">${formatearTiempo(segundosRestantes)}</strong></p>
                <small>Oleada #${numeroOleada} - Prepara tus defensas inmediatamente</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    container.appendChild(alerta);
    
    // Actualizar el contador cada segundo
    let segundos = segundosRestantes;
    const intervalo = setInterval(() => {
        segundos--;
        const elemento = document.getElementById('contador-alerta');
        if (elemento) {
            elemento.textContent = formatearTiempo(segundos);
        }
        
        if (segundos <= 0) {
            clearInterval(intervalo);
            alerta.remove();
        }
    }, 1000);

    // Tambien emitir un sonido de alerta (se puede agregar un archivo de sonido mas adelante)
}

// Mostrar notificacion cuando comienza la oleada
function mostrarNotificacionOleada(data) {
    const container = document.getElementById('notificaciones-container');
    
    const notificacion = document.createElement('div');
    notificacion.className = 'alert alert-warning alert-dismissible fade show mb-2';
    notificacion.style.cssText = 'box-shadow: 0 8px 25px rgba(255, 193, 7, 0.5); border: 3px solid #ffc107; font-size: 1.2rem; background: linear-gradient(135deg, #ffc107 0%, #ff6f00 100%); color: white;';
    notificacion.innerHTML = `
        <div class="d-flex align-items-center">
            <div style="font-size: 3.5rem; margin-right: 15px;">⚔️</div>
            <div class="flex-grow-1">
                <h4 class="mb-1"><strong>¡OLEADA ${data.numero_oleada} INICIADA!</strong></h4>
                <p class="mb-1">${data.enemigos_generados} enemigos están atacando tu reino</p>
                <small>¡Defiende tu base a toda costa!</small>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    container.appendChild(notificacion);
    
    // Hacer que la notificacion desaparezca despues de 8 segundos
    setTimeout(() => {
        notificacion.classList.remove('show');
        setTimeout(() => notificacion.remove(), 300);
    }, 8000);
}

// =====================================
// SISTEMA DE VISUALIZACIÓN DE ENEMIGOS
// =====================================

// Variable global para almacenar enemigos actuales
let enemigosActivos = [];

// Iniciar el sistema de visualizacion de enemigos
function iniciarVisualizacionEnemigos() {
    console.log('Sistema de visualización de enemigos iniciado');
    
    // Actualizar enemigos altiro al iniciar
    actualizarEnemigos();
    
    // Luego actualizar cada 2 segundos
    setInterval(() => {
        actualizarEnemigos();
    }, 2000);
}

// Funcion que obtiene y muestra los enemigos actuales
function actualizarEnemigos() {
    fetch('api/wave/get_active_enemies.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                enemigosActivos = data.enemigos;
                
                // Si hay enemigos, dibujarlos en el grid
                if (enemigosActivos.length > 0) {
                    dibujarEnemigosEnGrid(enemigosActivos);
                } else {
                    // Si no hay enemigos, limpiar cualquier enemigo que este dibujado
                    limpiarEnemigosDelGrid();
                }
            }
        })
        .catch(error => {
            console.error('Error al actualizar enemigos:', error);
        });
}

// Funcion que dibuja los enemigos en el grid
function dibujarEnemigosEnGrid(enemigos) {
    // Limpiar todos los enemigos dibujados previamente
    limpiarEnemigosDelGrid();
    
    // Dibujar cada enemigo en su posicion
    enemigos.forEach(enemigo => {
        // Solo dibujar enemigos que ya entraron al grid (posicion >= 0)
        if (enemigo.posicion >= 0 && enemigo.posicion <= 80) {
            const cell = document.querySelector(`[data-position="${enemigo.posicion}"]`);
            
            if (cell) {
                // Determinar si es una variante mejorada
                const esVarianteMejorada = enemigo.nombre.includes('Guerrero') || 
                                        enemigo.nombre.includes('Berserker') || 
                                        enemigo.nombre.includes('Gigante') ||
                                        enemigo.nombre.includes('Ancestral');

                // Obtener el emoji del tipo de enemigo
                const emoji = enemigoEmojis[enemigo.tipo] || '👾';

                // Crear el contenedor principal del enemigo
                const enemigoDiv = document.createElement('div');
                enemigoDiv.className = 'enemigo-container';
                enemigoDiv.dataset.enemigoId = enemigo.id;

                // Crear el contenedor del emoji (para la estrella)
                const enemigoEmojiContainer = document.createElement('div');
                enemigoEmojiContainer.className = 'enemigo-emoji-container';

                // Crear el emoji del enemigo
                const enemigoEmoji = document.createElement('div');
                enemigoEmoji.className = esVarianteMejorada ? 'enemigo-emoji mejorado' : 'enemigo-emoji basico';
                enemigoEmoji.textContent = emoji;

                // Agregar event listener para mostrar informacion del enemigo al hacer click
                enemigoEmoji.addEventListener('click', function(event) {
                    // Prevenir que el click se haga a elementos debajo
                    event.stopPropagation();
                    
                    // Mostrar la informacion detallada del enemigo
                    mostrarInfoEnemigo(enemigo, emoji, esVarianteMejorada);
                });

                enemigoEmojiContainer.appendChild(enemigoEmoji);

                // Si es variante mejorada, agregar estrella dorada
                if (esVarianteMejorada) {
                    const estrella = document.createElement('div');
                    estrella.className = 'enemigo-estrella';
                    estrella.textContent = '⭐';
                    enemigoEmojiContainer.appendChild(estrella);
                }

                // Crear la barra de vida
                const vidaContainer = document.createElement('div');
                vidaContainer.className = 'enemigo-vida-container';

                const vidaBarra = document.createElement('div');
                vidaBarra.className = 'enemigo-vida-barra';
                const porcentajeVida = (enemigo.vida_actual / enemigo.vida_maxima) * 100;
                vidaBarra.style.width = porcentajeVida + '%';

                vidaContainer.appendChild(vidaBarra);
                enemigoDiv.appendChild(enemigoEmojiContainer);
                enemigoDiv.appendChild(vidaContainer);
                
                // Hacer la celda relativa para posicionamiento absoluto
                cell.style.position = 'relative';
                cell.appendChild(enemigoDiv);
            }
        }
    });
}

// Funcion para limpiar todos los enemigos del grid
function limpiarEnemigosDelGrid() {
    const enemigosEnGrid = document.querySelectorAll('.enemigo-container');
    enemigosEnGrid.forEach(enemigo => enemigo.remove());
}

// Funcion para mostrar informacion detallada de un enemigo al hacer click
function mostrarInfoEnemigo(enemigo, emoji, esVarianteMejorada) {
    const container = document.getElementById('notificaciones-container');
    
    // Crear una notificacion especial para informacion del enemigo
    const notificacion = document.createElement('div');
    notificacion.className = 'alert alert-dark alert-dismissible fade show mb-2';
    notificacion.style.cssText = `
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.6); 
        border: 3px solid #6c757d; 
        font-size: 1rem;
        max-width: 400px;
    `;
    
    // Determinar el color de la barra de vida para visualizacion
    const porcentajeVida = (enemigo.vida_actual / enemigo.vida_maxima) * 100;
    let colorVida = 'success';
    if (porcentajeVida < 30) colorVida = 'danger';
    else if (porcentajeVida < 70) colorVida = 'warning';
    
    notificacion.innerHTML = `
        <div class="d-flex align-items-start">
            <div class="me-3 text-center">
                <div style="position: relative; display: inline-block; font-size: 4rem; line-height: 1;">
                    ${emoji}
                    ${esVarianteMejorada ? '<div style="position: absolute; top: -8px; right: -8px; font-size: 1.2rem; filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.8));">⭐</div>' : ''}
                </div>
            </div>
            <div class="flex-grow-1">
                <button type="button" class="btn-close" data-bs-dismiss="alert" style="position: absolute; top: 10px; right: 10px;"></button>
                
                <h5 class="mb-2">
                    <strong>${enemigo.nombre}</strong>
                    ${esVarianteMejorada ? '<span class="badge bg-warning text-dark ms-2">Elite</span>' : ''}
                </h5>
                
                <p class="mb-2 small text-muted">${enemigo.descripcion || 'Criatura hostil'}</p>
                
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small><strong>❤️ Vida:</strong></small>
                        <small><strong>${enemigo.vida_actual} / ${enemigo.vida_maxima}</strong></small>
                    </div>
                    <div class="progress" style="height: 15px;">
                        <div class="progress-bar bg-${colorVida}" 
                             style="width: ${porcentajeVida}%">
                            ${Math.round(porcentajeVida)}%
                        </div>
                    </div>
                </div>
                
                <div class="row text-center mt-3">
                    <div class="col-6">
                        <div class="bg-danger bg-opacity-10 p-2 rounded">
                            <div style="font-size: 1.5rem;">⚔️</div>
                            <small class="text-muted d-block">Ataque</small>
                            <strong class="text-danger">${enemigo.ataque}</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-info bg-opacity-10 p-2 rounded">
                            <div style="font-size: 1.5rem;">🌊</div>
                            <small class="text-muted d-block">Oleada</small>
                            <strong class="text-info">#${enemigo.oleada_numero}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(notificacion);
    
    // Hacer que la notificacion desaparezca despues de 10 segundos
    setTimeout(() => {
        notificacion.classList.remove('show');
        setTimeout(() => notificacion.remove(), 300);
    }, 10000);
}

// ===================================
// SISTEMA DE MOVIMIENTO DE ENEMIGOS
// ===================================

// Variable para rastrear si el sistema de movimiento esta activo
let sistemaMovimientoActivo = false;

// Iniciar el sistema de movimiento de enemigos
function iniciarMovimientoEnemigos() {
    console.log('Sistema de movimiento de enemigos iniciado');
    
    // Mover inmediatamente
    moverEnemigos();
    
    // Luego mover cada 2 segundos
    setInterval(() => {
        moverEnemigos();
    }, 2000);
}

// Funcion que llama al API para mover todos los enemigos
function moverEnemigos() {
    // Solo mover si hay una oleada en curso
    if (!estadoOleada.oleadaEnCurso) {
        return;
    }
    
    fetch('api/wave/move_enemies.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // El sistema de visualizacion automaticamente detectara los cambios de posicion en la proxima actualizacion
            console.log(`Movimiento: ${data.enemigos_movidos} enemigos se movieron`);
        }
    })
    .catch(error => {
        console.error('Error al mover enemigos:', error);
    });
}