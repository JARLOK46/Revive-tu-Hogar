// empleado-dashboard.js - Funcionalidades avanzadas del dashboard de empleados

// Configuración global
const DASHBOARD_CONFIG = {
  refreshInterval: 300000, // 5 minutos
  animationDelay: 100,
  toastDuration: 3000
};

// Clase para manejar notificaciones
class NotificationManager {
  constructor() {
    this.notifications = [];
    this.init();
  }

  init() {
    this.bindEvents();
    this.startAutoRefresh();
  }

  bindEvents() {
    // Hacer notificaciones clickeables
    document.querySelectorAll('.notification-card').forEach(card => {
      card.addEventListener('click', (e) => {
        this.handleNotificationClick(card);
      });
    });
  }

  handleNotificationClick(card) {
    const action = card.dataset.action;
    if (action) {
      const element = document.querySelector(action);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
      }
    }
    this.dismissNotification(card);
  }

  dismissNotification(element) {
    element.style.transform = 'translateX(100%)';
    element.style.opacity = '0';
    setTimeout(() => {
      element.remove();
      this.updateNotificationCount();
    }, 300);
  }

  updateNotificationCount() {
    const count = document.querySelectorAll('.notification-card').length;
    const countElement = document.querySelector('.notifications-count');
    if (countElement) {
      countElement.textContent = count;
      if (count === 0) {
        const panel = document.querySelector('.notifications-panel');
        if (panel) {
          panel.style.display = 'none';
        }
      }
    }
  }

  addNotification(notification) {
    // Crear nueva notificación dinámicamente
    const notificationHTML = `
      <div class="notification-card notification-${notification.tipo}">
        <div class="notification-icon">${notification.icono}</div>
        <div class="notification-content">
          <div class="notification-title">${notification.titulo}</div>
          <div class="notification-message">${notification.mensaje}</div>
        </div>
        <div class="notification-time">${notification.tiempo}</div>
      </div>
    `;
    
    const panel = document.querySelector('.notifications-grid');
    if (panel) {
      panel.insertAdjacentHTML('afterbegin', notificationHTML);
      this.updateNotificationCount();
    }
  }

  startAutoRefresh() {
    setInterval(() => {
      this.checkForNewNotifications();
    }, DASHBOARD_CONFIG.refreshInterval);
  }

  async checkForNewNotifications() {
    try {
      // Aquí se haría una llamada AJAX al servidor
      console.log('🔔 Verificando nuevas notificaciones...');
      
      // Simular nueva notificación (ejemplo)
      if (Math.random() > 0.9) {
        this.addNotification({
          tipo: 'info',
          icono: '📬',
          titulo: 'Nueva actualización',
          mensaje: 'Se ha actualizado un pedido',
          tiempo: 'Ahora'
        });
        ToastManager.show('Nueva notificación recibida', 'info');
      }
    } catch (error) {
      console.error('Error al verificar notificaciones:', error);
    }
  }
}

// Clase para manejar actividades y tareas
class ActivityManager {
  constructor() {
    this.init();
  }

  init() {
    this.bindEvents();
  }

  bindEvents() {
    // Hacer tareas clickeables para marcar como completadas
    document.querySelectorAll('.task-item').forEach(task => {
      task.addEventListener('click', (e) => {
        this.handleTaskClick(task);
      });
    });

    // Agregar botones de acción a las tareas
    this.addTaskActions();
  }

  addTaskActions() {
    document.querySelectorAll('.task-item').forEach(task => {
      if (!task.querySelector('.task-actions')) {
        const actionsHTML = `
          <div class="task-actions">
            <button class="task-btn complete-btn" title="Marcar como completada">
              <i class="fas fa-check"></i>
            </button>
            <button class="task-btn postpone-btn" title="Posponer">
              <i class="fas fa-clock"></i>
            </button>
          </div>
        `;
        task.insertAdjacentHTML('beforeend', actionsHTML);

        // Bind eventos a los botones
        const completeBtn = task.querySelector('.complete-btn');
        const postponeBtn = task.querySelector('.postpone-btn');

        completeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          this.completeTask(task);
        });

        postponeBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          this.postponeTask(task);
        });
      }
    });
  }

  handleTaskClick(task) {
    // Expandir detalles de la tarea
    const details = task.querySelector('.task-details');
    if (details) {
      details.style.display = details.style.display === 'none' ? 'block' : 'none';
    }
  }

  completeTask(taskElement) {
    if (confirm('¿Marcar esta tarea como completada?')) {
      taskElement.style.opacity = '0.5';
      taskElement.style.transform = 'scale(0.95)';
      
      setTimeout(() => {
        taskElement.remove();
        this.updateTaskCounts();
        ToastManager.show('Tarea marcada como completada', 'success');
      }, 500);
    }
  }

  postponeTask(taskElement) {
    const newTime = prompt('¿Para cuándo quieres reprogramar esta tarea? (formato: HH:MM)');
    if (newTime) {
      ToastManager.show(`Tarea reprogramada para las ${newTime}`, 'info');
      // Aquí se haría la actualización en el servidor
    }
  }

  updateTaskCounts() {
    const urgentCount = document.querySelectorAll('.task-item.urgent').length;
    const urgentCountElement = document.querySelector('.urgent-count');
    if (urgentCountElement) {
      urgentCountElement.textContent = urgentCount + ' urgentes';
      if (urgentCount === 0) {
        urgentCountElement.style.display = 'none';
      }
    }
  }
}

// Clase para manejar toasts/mensajes temporales
class ToastManager {
  static show(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <div class="toast-content">
        <i class="fas fa-${this.getIcon(type)}-circle"></i>
        <span>${message}</span>
      </div>
      <button class="toast-close" onclick="this.parentElement.remove()">
        <i class="fas fa-times"></i>
      </button>
    `;
    
    this.styleToast(toast, type);
    document.body.appendChild(toast);
    
    // Animación de entrada
    setTimeout(() => {
      toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto-remove
    setTimeout(() => {
      if (toast.parentElement) {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
          toast.remove();
        }, 300);
      }
    }, DASHBOARD_CONFIG.toastDuration);
  }

  static getIcon(type) {
    const icons = {
      success: 'check',
      error: 'times',
      warning: 'exclamation',
      info: 'info'
    };
    return icons[type] || 'info';
  }

  static styleToast(toast, type) {
    const colors = {
      success: 'linear-gradient(135deg, #10b981, #34d399)',
      error: 'linear-gradient(135deg, #ef4444, #f87171)',
      warning: 'linear-gradient(135deg, #f59e0b, #fbbf24)',
      info: 'linear-gradient(135deg, #3b82f6, #60a5fa)'
    };

    toast.style.cssText = `
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 16px 20px;
      border-radius: 12px;
      color: white;
      font-weight: 600;
      z-index: 9999;
      transform: translateX(100%);
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      background: ${colors[type] || colors.info};
      display: flex;
      align-items: center;
      gap: 12px;
      max-width: 400px;
    `;
  }
}

// Clase para manejar animaciones
class AnimationManager {
  static initFadeInAnimations() {
    const elements = document.querySelectorAll('.fade-in');
    elements.forEach((el, index) => {
      setTimeout(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
      }, index * DASHBOARD_CONFIG.animationDelay);
    });
  }

  static addHoverEffects() {
    // Efectos hover para tarjetas
    document.querySelectorAll('.stat-card, .notification-card, .task-item').forEach(card => {
      card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-4px) scale(1.02)';
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0) scale(1)';
      });
    });
  }
}

// Clase principal del dashboard
class EmployeeDashboard {
  constructor() {
    this.notificationManager = new NotificationManager();
    this.activityManager = new ActivityManager();
    this.init();
  }

  init() {
    this.bindGlobalEvents();
    this.initializeComponents();
  }

  bindGlobalEvents() {
    document.addEventListener('DOMContentLoaded', () => {
      AnimationManager.initFadeInAnimations();
      AnimationManager.addHoverEffects();
      this.addKeyboardShortcuts();
    });
  }

  addKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // Ctrl + R para refrescar notificaciones
      if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        this.notificationManager.checkForNewNotifications();
        ToastManager.show('Verificando notificaciones...', 'info');
      }

      // Escape para cerrar modales
      if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal[style*="display: flex"]');
        modals.forEach(modal => {
          modal.style.display = 'none';
        });
      }
    });
  }

  initializeComponents() {
    // Inicializar tooltips
    this.initTooltips();
    
    // Inicializar contadores en tiempo real
    this.initRealTimeCounters();
  }

  initTooltips() {
    document.querySelectorAll('[title]').forEach(element => {
      element.addEventListener('mouseenter', (e) => {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = element.title;
        tooltip.style.cssText = `
          position: absolute;
          background: rgba(0, 0, 0, 0.8);
          color: white;
          padding: 8px 12px;
          border-radius: 6px;
          font-size: 12px;
          z-index: 10000;
          pointer-events: none;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        tooltip.style.left = rect.left + 'px';
        tooltip.style.top = (rect.top - tooltip.offsetHeight - 8) + 'px';
        
        element.addEventListener('mouseleave', () => {
          tooltip.remove();
        }, { once: true });
      });
    });
  }

  initRealTimeCounters() {
    // Actualizar contadores cada minuto
    setInterval(() => {
      this.updateTimeBasedElements();
    }, 60000);
  }

  updateTimeBasedElements() {
    // Actualizar timestamps relativos
    document.querySelectorAll('[data-timestamp]').forEach(element => {
      const timestamp = parseInt(element.dataset.timestamp);
      const now = Date.now();
      const diff = now - timestamp;
      
      if (diff < 60000) {
        element.textContent = 'Ahora';
      } else if (diff < 3600000) {
        element.textContent = Math.floor(diff / 60000) + ' min';
      } else {
        element.textContent = Math.floor(diff / 3600000) + ' h';
      }
    });
  }
}

// Inicializar dashboard cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
  window.employeeDashboard = new EmployeeDashboard();
  console.log('🚀 Dashboard de empleado inicializado correctamente');
});

// Exportar para uso global
window.ToastManager = ToastManager;
window.NotificationManager = NotificationManager;
window.ActivityManager = ActivityManager;