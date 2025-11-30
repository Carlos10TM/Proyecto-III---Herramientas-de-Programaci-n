// ========================================
// INICIALIZACION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎮 Social Kingdooms - Juego iniciado');
    
    // Inicializar el grid de la base
    inicializarGrid();
    
    // Cargar edificios en el grid (solo los terminados)
    cargarMisEdificiosParaGrid();
});

// ========================================
// FUNCION PARA CAMBIAR ENTRE SECCIONES
// ========================================
function mostrarSeccion(seccion) {
    console.log('Intentando mostrar sección:', seccion);
    
    // Ocultar todas las secciones
    const secciones = document.querySelectorAll('.seccion-juego');
    secciones.forEach(s => {
        s.style.display = 'none';
    });
    
    // Mostrar la seccion seleccionada
    const seccionMostrar = document.getElementById('seccion-' + seccion);
    if (seccionMostrar) {
        seccionMostrar.style.display = 'block';
        console.log('Sección mostrada:', seccion);
        
        // Si es la sección de edificios, cargar los edificios
        if (seccion === 'edificios') {
            cargarEdificios();
        }
        
        // Si es la seccion de inicio, cargar el grid
        if (seccion === 'bienvenida') {
            cargarMisEdificiosParaGrid();
        }
    } else {
        console.error('No se encontró la sección:', 'seccion-' + seccion);
    }
}

// ========================================
// FUNCION PARA CARGAR EDIFICIOS
// ========================================
function cargarEdificios() {
    console.log('Cargando edificios desde el servidor...');
    
    // Cargar mis edificios construidos
    fetch('api/buildings/get_my_buildings.php')
        .then(response => response.json())
        .then(data => {
            console.log('Mis edificios:', data);
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
            console.log('Edificios disponibles:', edificios);
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

// ========================================
// FUNCION PARA MOSTRAR MIS EDIFICIOS
// ========================================
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
        // Determinar el icono segun el tipo
        let icono = '';
        let colorHeader = 'bg-success';
        
        switch(edificio.tipo) {
            case 'aserradero': 
                icono = 'fa-tree'; 
                colorHeader = 'bg-success';
                break;
            case 'cantera': 
                icono = 'fa-mountain'; 
                colorHeader = 'bg-secondary';
                break;
            case 'granja': 
                icono = 'fa-wheat-awn'; 
                colorHeader = 'bg-warning';
                break;
            case 'mina_oro': 
                icono = 'fa-gem'; 
                colorHeader = 'bg-warning';
                break;
            case 'ayuntamiento': 
                icono = 'fa-landmark'; 
                colorHeader = 'bg-danger';
                break;
            case 'cuartel': 
                icono = 'fa-shield-halved'; 
                colorHeader = 'bg-primary';
                break;
            case 'torre': 
                icono = 'fa-tower-observation'; 
                colorHeader = 'bg-dark';
                break;
            default: 
                icono = 'fa-building';
        }
        
        // Crear la tarjeta HTML
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header ${colorHeader} text-white">
                        <h6>
                            <i class="fas ${icono}"></i> ${edificio.nombre}
                            <span class="badge bg-light text-dark float-end">Nv. ${edificio.nivel}</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">${edificio.descripcion}</p>
                        
                        ${edificio.generacion_por_minuto > 0 ? `
                            <div class="alert alert-info py-2 mb-2">
                                <i class="fas fa-clock"></i> Generando: <strong>${edificio.generacion_por_minuto}/min</strong>
                            </div>
                        ` : ''}
                        
                        ${edificio.bonus_tropas > 0 ? `
                            <div class="alert alert-success py-2 mb-2">
                                <i class="fas fa-users"></i> Tropas: <strong>+${edificio.bonus_tropas}</strong>
                            </div>
                        ` : ''}
                        
                        ${edificio.costo_mejora_madera ? `
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
    console.log('Edificios en construcción recibidos:', edificios);
    const container = document.getElementById('mis-edificios');
    
    if (edificios.length === 0) {
        return; // No hay nada en construccion
    }
    
    let html = '';
    
    edificios.forEach(edificio => {
        // Determinar el icono
        let icono = '';
        switch(edificio.tipo) {
            case 'aserradero': icono = 'fa-tree'; break;
            case 'cantera': icono = 'fa-mountain'; break;
            case 'granja': icono = 'fa-wheat-awn'; break;
            case 'mina_oro': icono = 'fa-gem'; break;
            case 'ayuntamiento': icono = 'fa-landmark'; break;
            case 'cuartel': icono = 'fa-shield-halved'; break;
            case 'torre': icono = 'fa-tower-observation'; break;
            default: icono = 'fa-building';
        }
        
        // Calcular porcentaje de progreso
        const tiempoTotal = edificio.tiempo_construccion;
        const tiempoRestante = Math.max(0, edificio.segundos_restantes);
        const porcentaje = Math.max(0, ((tiempoTotal - tiempoRestante) / tiempoTotal) * 100);
        
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100 border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6>
                            <i class="fas ${icono}"></i> ${edificio.nombre}
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

// ========================================
// FUNCION PARA MOSTRAR EDIFICIOS
// ========================================
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
        // Determinar el icono segun el tipo
        let icono = '';
        switch(edificio.tipo) {
            case 'aserradero': icono = 'fa-tree'; break;
            case 'cantera': icono = 'fa-mountain'; break;
            case 'granja': icono = 'fa-wheat-awn'; break;
            case 'mina_oro': icono = 'fa-gem'; break;
            case 'ayuntamiento': icono = 'fa-landmark'; break;
            case 'cuartel': icono = 'fa-shield-halved'; break;
            case 'torre': icono = 'fa-tower-observation'; break;
            default: icono = 'fa-building';
        }
        
        // Crear la tarjeta HTML
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h6><i class="fas ${icono}"></i> ${edificio.nombre}</h6>
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

// ========================================
// FUNCION PARA CONSTRUIR EDIFICIO
// ========================================
function construirEdificio(edificioId) {
    console.log('Intentando construir edificio ID:', edificioId);
    
    // Confirmar construccion
    if (!confirm('¿Estás seguro de que quieres construir este edificio?')) {
        return;
    }
    
    // Hacer peticion a la API
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
        console.log('Respuesta del servidor:', data);
        
        if (data.success) {
            // Mostrar mensaje de exito
            alert('¡Edificio en construcción! Tiempo: ' + data.tiempo_construccion + ' segundos');
            
            // Actualizar recursos en pantalla
            actualizarRecursos(data.recursos);
            
            // Recargar edificios
            cargarEdificios();
        } else {
            // Mostrar error
            alert('Error: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al construir el edificio');
    });
}

// =============================================
// FUNCION PARA ACTUALIZAR RECURSOS EN PANTALLA
// =============================================
function actualizarRecursos(recursos) {
    document.getElementById('oro').textContent = Number(recursos.oro).toLocaleString();
    document.getElementById('madera').textContent = Number(recursos.madera).toLocaleString();
    document.getElementById('piedra').textContent = Number(recursos.piedra).toLocaleString();
    document.getElementById('comida').textContent = Number(recursos.comida).toLocaleString();
    
    console.log('Recursos actualizados:', recursos);
}

// ========================================
// FUNCIONES DE CONTADOR REGRESIVO
// ========================================
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
        return; // Ya se proceso (no hace nada)
    }
    
    construccionesFinalizadas.add(edificioId);
    
    console.log('Construcción finalizada:', edificioId);
    
    // Llamar al API para finalizar en la base de datos
    fetch('api/buildings/finish_construction.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('Construcción finalizada en BD:', data);
        
        if (data.success && data.finalizados > 0) {
            alert('¡Construcción completada!');
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

// ========================================
// SISTEMA DE GRID PARA LA BASE
// ========================================

// Mapeo de tipos de edificios a emojis (TEMPORAL)
const edificioEmojis = {
    'ayuntamiento': '🏰',
    'aserradero': '🪵',
    'cantera': '⛰️',
    'granja': '🌾',
    'mina_oro': '💎',
    'cuartel': '⚔️',
    'torre': '🗼'
};

// Inicializar el grid
function inicializarGrid() {
    const grid = document.getElementById('base-grid');
    grid.innerHTML = '';
    
    // Crear 10x10 = 100 casillas
    for (let i = 0; i < 121; i++) {
        const cell = document.createElement('div');
        cell.className = 'grid-cell';
        cell.dataset.position = i;
        
        // Marcar la casilla central (posición 60 en un grid de 11x11, fila 5 columna 5)
        if (i === 60) {
            cell.classList.add('center');
        }
        
        grid.appendChild(cell);
    }
    
    console.log('Grid inicializado: 11x11 casillas');
}

// Cargar edificios en el grid
function cargarEdificiosEnGrid(edificios) {
    console.log('Cargando edificios en el grid:', edificios);
    
    // Limpiar edificios actuales
    document.querySelectorAll('.grid-cell').forEach(cell => {
        cell.classList.remove('occupied');
        cell.innerHTML = '';
    });
    
    edificios.forEach(edificio => {
        let posicion = edificio.posicion_x || null;
        
        // Si es ayuntamiento y no tiene posicion, ponerlo en el centro
        if (edificio.tipo === 'ayuntamiento' && !posicion) {
            posicion = 60; // Centro del grid 11x11
            actualizarPosicionEdificio(edificio.id, posicion);
        }
        
        // Si no tiene posicion, asignar una automaticamente
        if (!posicion) {
            posicion = encontrarPosicionLibre();
            actualizarPosicionEdificio(edificio.id, posicion);
        }
        
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
function encontrarPosicionLibre() {
    const casillasOcupadas = Array.from(document.querySelectorAll('.grid-cell.occupied'))
        .map(cell => parseInt(cell.dataset.position));
    
    const centro = 44;
    
    // Posiciones alrededor del centro en orden de prioridad
    const posicionesPrioritarias = [
        59, 61, 49, 71, // Lados directos (izquierda, derecha, arriba, abajo)
        48, 50, 70, 72, // Diagonales
        47, 51, 58, 62, 69, 73, // Segunda capa
        36, 37, 38, 39, 40, 80, 81, 82, 83, 84 // Tercera capa
    ];
    
    // Buscar en posiciones prioritarias
    for (let pos of posicionesPrioritarias) {
        if (!casillasOcupadas.includes(pos) && pos !== centro) {
            return pos;
        }
    }
    
    // Si no hay espacio cerca, buscar cualquier posición libre
    for (let i = 0; i < 121; i++) {
        if (!casillasOcupadas.includes(i) && i !== centro) {
            return i;
        }
    }
    
    return 0; // Fallback
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
    alert(`${emoji} ${edificio.nombre}\nNivel: ${edificio.nivel}\n${edificio.descripcion}`);
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