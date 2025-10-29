// admin.js - Funcionalidades del panel de administración

// Función para cambiar cantidad de filas en tablas
function cambiarFilas(cantidad) {
  const url = new URL(window.location);
  url.searchParams.set('filas', cantidad);
  window.location.href = url.toString();
}

// Función para actualizar gráficos
function refreshCharts() {
  location.reload();
}

// Función para filtrar actividades
function filterActivities() {
  const filterTipoEl = document.getElementById('filterTipo');
  const filterEstadoEl = document.getElementById('filterEstado');
  const filterPrioridadEl = document.getElementById('filterPrioridad');
  
  if (!filterTipoEl || !filterEstadoEl || !filterPrioridadEl) {
    console.warn('Elementos de filtro no encontrados');
    return;
  }
  
  const filterTipo = filterTipoEl.value;
  const filterEstado = filterEstadoEl.value;
  const filterPrioridad = filterPrioridadEl.value;
  
  const table = document.getElementById('activitiesTable');
  if (!table) {
    console.warn('Tabla de actividades no encontrada');
    return;
  }
  
  const tbody = table.getElementsByTagName('tbody')[0];
  if (!tbody) {
    console.warn('Tbody de actividades no encontrado');
    return;
  }
  
  const rows = tbody.getElementsByTagName('tr');
  let visibleCount = 0;
  
  for (let i = 0; i < rows.length; i++) {
    const row = rows[i];
    const tipo = row.getAttribute('data-tipo');
    const estado = row.getAttribute('data-estado');
    const prioridad = row.getAttribute('data-prioridad');
    
    let showRow = true;
    
    if (filterTipo && tipo !== filterTipo) showRow = false;
    if (filterEstado && estado !== filterEstado) showRow = false;
    if (filterPrioridad && prioridad !== filterPrioridad) showRow = false;
    
    if (showRow) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  }
  
  // Mostrar mensaje si no hay resultados
  let noResultsMsg = document.getElementById('noResultsMessage');
  if (visibleCount === 0) {
    if (!noResultsMsg) {
      noResultsMsg = document.createElement('tr');
      noResultsMsg.id = 'noResultsMessage';
      noResultsMsg.innerHTML = '<td colspan="10" style="text-align:center;padding:20px;color:#666;font-style:italic;">No se encontraron actividades con los filtros seleccionados</td>';
      tbody.appendChild(noResultsMsg);
    }
    noResultsMsg.style.display = '';
  } else {
    if (noResultsMsg) {
      noResultsMsg.style.display = 'none';
    }
  }
}

// Función para limpiar filtros
function clearFilters() {
  const filterTipo = document.getElementById('filterTipo');
  const filterEstado = document.getElementById('filterEstado');
  const filterPrioridad = document.getElementById('filterPrioridad');
  
  if (filterTipo) filterTipo.value = '';
  if (filterEstado) filterEstado.value = '';
  if (filterPrioridad) filterPrioridad.value = '';
  
  filterActivities();
}

// Inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
  // Configurar filtros de actividades
  const filterTipo = document.getElementById('filterTipo');
  const filterEstado = document.getElementById('filterEstado');
  const filterPrioridad = document.getElementById('filterPrioridad');
  
  if (filterTipo) filterTipo.addEventListener('change', filterActivities);
  if (filterEstado) filterEstado.addEventListener('change', filterActivities);
  if (filterPrioridad) filterPrioridad.addEventListener('change', filterActivities);
  
  // Configurar expansión de descripciones
  document.querySelectorAll('.activity-description').forEach(desc => {
    desc.addEventListener('click', function() {
      if (this.style.maxWidth === 'none') {
        this.style.maxWidth = '200px';
        this.style.whiteSpace = 'nowrap';
        this.style.overflow = 'hidden';
        this.style.textOverflow = 'ellipsis';
      } else {
        this.style.maxWidth = 'none';
        this.style.whiteSpace = 'normal';
        this.style.overflow = 'visible';
        this.style.textOverflow = 'initial';
      }
    });
    desc.style.cursor = 'pointer';
    desc.title = 'Click para expandir/contraer';
  });
  
  // Mostrar mensaje de bienvenida
  console.log('🎉 Panel CRM cargado correctamente');
});

// Función para mostrar notificaciones
function showNotification(message, type = 'info') {
  const notification = document.createElement('div');
  notification.className = `notification notification-${type}`;
  notification.textContent = message;
  notification.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 600;
    z-index: 9999;
    animation: slideIn 0.3s ease;
  `;
  
  switch(type) {
    case 'success':
      notification.style.background = 'linear-gradient(135deg, #10b981, #34d399)';
      break;
    case 'error':
      notification.style.background = 'linear-gradient(135deg, #ef4444, #f87171)';
      break;
    case 'warning':
      notification.style.background = 'linear-gradient(135deg, #f59e0b, #fbbf24)';
      break;
    default:
      notification.style.background = 'linear-gradient(135deg, #3b82f6, #60a5fa)';
  }
  
  document.body.appendChild(notification);
  
  setTimeout(() => {
    notification.remove();
  }, 3000);
}

// Función para confirmar eliminaciones
function confirmDelete(message = '¿Estás seguro de que quieres eliminar este elemento?') {
  return confirm(message);
}