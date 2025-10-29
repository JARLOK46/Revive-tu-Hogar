// cliente-dashboard.js - Funcionalidades CRM para dashboard de clientes

// Configuración del dashboard de cliente
const CLIENT_DASHBOARD_CONFIG = {
  animationDelay: 80,
  toastDuration: 4000,
  autoRefreshInterval: 300000 // 5 minutos
};

// Clase para manejar notificaciones del cliente
class ClientNotificationManager {
  constructor() {
    this.notifications = [];
    this.init();
  }

  init() {
    this.bindEvents();
    this.startPeriodicUpdates();
  }

  bindEvents() {
    document.querySelectorAll('.client-notification-card').forEach(card => {
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
    ClientToastManager.show('Navegando a la sección seleccionada', 'info');
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
    const count = document.querySelectorAll('.client-notification-card').length;
    const badge = document.querySelector('.notifications-badge');
    if (badge) {
      badge.textContent = count;
      if (count === 0) {
        const panel = document.querySelector('.client-notifications-panel');
        if (panel) {
          panel.style.display = 'none';
        }
      }
    }
  }

  addNotification(notification) {
    const notificationHTML = `
      <div class="client-notification-card notification-${notification.tipo}">
        <div class="notification-icon">${notification.icono}</div>
        <div class="notification-content">
          <div class="notification-title">${notification.titulo}</div>
          <div class="notification-message">${notification.mensaje}</div>
        </div>
        <div class="notification-time">${notification.tiempo}</div>
      </div>
    `;
    
    const grid = document.querySelector('.notifications-grid');
    if (grid) {
      grid.insertAdjacentHTML('afterbegin', notificationHTML);
      this.updateNotificationCount();
    }
  }

  startPeriodicUpdates() {
    setInterval(() => {
      this.checkForUpdates();
    }, CLIENT_DASHBOARD_CONFIG.autoRefreshInterval);
  }

  async checkForUpdates() {
    try {
      console.log('🔔 Verificando actualizaciones para el cliente...');
      
      // Simular nueva notificación ocasional
      if (Math.random() > 0.95) {
        this.addNotification({
          tipo: 'info',
          icono: '📬',
          titulo: 'Actualización de proyecto',
          mensaje: 'Tu proyecto ha sido actualizado',
          tiempo: 'Ahora'
        });
        ClientToastManager.show('Nueva actualización disponible', 'info');
      }
    } catch (error) {
      console.error('Error al verificar actualizaciones:', error);
    }
  }
}

// Clase para manejar comunicaciones del cliente
class ClientCommunicationManager {
  constructor() {
    this.init();
  }

  init() {
    this.bindEvents();
    this.setupExpandableDescriptions();
  }

  bindEvents() {
    document.querySelectorAll('.communication-item').forEach(item => {
      item.addEventListener('click', (e) => {
        this.toggleDescription(item);
      });
    });
  }

  setupExpandableDescriptions() {
    document.querySelectorAll('.communication-item').forEach(item => {
      const description = item.querySelector('.communication-description');
      if (description && description.textContent.length > 100) {
        description.style.maxHeight = '60px';
        description.style.overflow = 'hidden';
        item.style.cursor = 'pointer';
        item.title = 'Click para expandir/contraer';
        
        // Añadir indicador de expansión
        const indicator = document.createElement('span');
        indicator.className = 'expand-indicator';
        indicator.innerHTML = '<i class="fas fa-chevron-down"></i>';
        indicator.style.cssText = `
          position: absolute;
          bottom: 8px;
          right: 8px;
          color: var(--verde-oliva);
          font-size: 12px;
          transition: transform 0.3s ease;
        `;
        item.appendChild(indicator);
      }
    });
  }

  toggleDescription(item) {
    const description = item.querySelector('.communication-description');
    const indicator = item.querySelector('.expand-indicator');
    
    if (description.style.maxHeight === 'none') {
      description.style.maxHeight = '60px';
      description.style.overflow = 'hidden';
      if (indicator) {
        indicator.style.transform = 'rotate(0deg)';
      }
    } else {
      description.style.maxHeight = 'none';
      description.style.overflow = 'visible';
      if (indicator) {
        indicator.style.transform = 'rotate(180deg)';
      }
    }
  }

  addCommunication(communication) {
    const communicationHTML = `
      <div class="communication-item ${communication.estado}">
        <div class="communication-icon">${communication.icono}</div>
        <div class="communication-content">
          <div class="communication-header">
            <span class="communication-type">${communication.tipo}</span>
            <span class="communication-date">${communication.fecha}</span>
          </div>
          <div class="communication-description">${communication.descripcion}</div>
          <div class="communication-footer">
            <span class="communication-employee">👤 ${communication.empleado}</span>
            <span class="communication-status status-${communication.estado}">${communication.estado}</span>
          </div>
        </div>
      </div>
    `;
    
    const timeline = document.querySelector('.communications-timeline');
    if (timeline) {
      timeline.insertAdjacentHTML('afterbegin', communicationHTML);
    }
  }
}

// Clase para manejar toasts del cliente
class ClientToastManager {
  static show(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `client-toast toast-${type}`;
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
    
    setTimeout(() => {
      toast.style.transform = 'translateX(0)';
    }, 100);
    
    setTimeout(() => {
      if (toast.parentElement) {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
          toast.remove();
        }, 300);
      }
    }, CLIENT_DASHBOARD_CONFIG.toastDuration);
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

// Clase para manejar métricas y estadísticas del cliente
class ClientMetricsManager {
  constructor() {
    this.init();
  }

  init() {
    this.animateCounters();
    this.addHoverEffects();
  }

  animateCounters() {
    document.querySelectorAll('.stat-number').forEach(counter => {
      const target = parseInt(counter.textContent.replace(/[^0-9]/g, '')) || 0;
      if (target > 0) {
        let current = 0;
        const increment = target / 30;
        const timer = setInterval(() => {
          current += increment;
          if (current >= target) {
            current = target;
            clearInterval(timer);
          }
          
          if (counter.textContent.includes('$')) {
            counter.textContent = '$' + Math.floor(current).toLocaleString();
          } else if (counter.textContent.includes('⭐')) {
            counter.textContent = '⭐';
          } else {
            counter.textContent = Math.floor(current);
          }
        }, 50);
      }
    });
  }

  addHoverEffects() {
    document.querySelectorAll('.stat-card').forEach(card => {
      card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-8px) scale(1.03)';
      });

      card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0) scale(1)';
      });
    });
  }
}

// Clase principal del dashboard de cliente
class ClientDashboard {
  constructor() {
    this.notificationManager = new ClientNotificationManager();
    this.communicationManager = new ClientCommunicationManager();
    this.metricsManager = new ClientMetricsManager();
    this.init();
  }

  init() {
    this.bindGlobalEvents();
    this.initializeFeatures();
  }

  bindGlobalEvents() {
    document.addEventListener('DOMContentLoaded', () => {
      this.initAnimations();
      this.addKeyboardShortcuts();
      this.showWelcomeMessage();
    });
  }

  initAnimations() {
    const cards = document.querySelectorAll('.pedido-card, .stat-card, .client-notification-card, .communication-item');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      setTimeout(() => {
        card.style.transition = 'all 0.6s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, index * CLIENT_DASHBOARD_CONFIG.animationDelay);
    });
  }

  addKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
      // Ctrl + N para nueva actividad (si estuviera disponible)
      if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        ClientToastManager.show('Funcionalidad de nueva actividad próximamente', 'info');
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

  showWelcomeMessage() {
    setTimeout(() => {
      ClientToastManager.show('¡Bienvenido a tu dashboard mejorado!', 'success');
    }, 1000);
  }

  initializeFeatures() {
    // Inicializar tooltips
    this.initTooltips();
    
    // Inicializar efectos de hover
    this.initHoverEffects();
  }

  initTooltips() {
    document.querySelectorAll('[title]').forEach(element => {
      element.addEventListener('mouseenter', (e) => {
        const tooltip = document.createElement('div');
        tooltip.className = 'client-tooltip';
        tooltip.textContent = element.title;
        tooltip.style.cssText = `
          position: absolute;
          background: var(--gris-carbon);
          color: var(--blanco-hueso);
          padding: 8px 12px;
          border-radius: 8px;
          font-size: 12px;
          z-index: 10000;
          pointer-events: none;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
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

  initHoverEffects() {
    // Efectos especiales para tarjetas de estadísticas
    document.querySelectorAll('.stat-card').forEach(card => {
      card.addEventListener('mouseenter', () => {
        const icon = card.querySelector('.stat-icon');
        if (icon) {
          icon.style.transform = 'scale(1.1) rotate(5deg)';
        }
      });

      card.addEventListener('mouseleave', () => {
        const icon = card.querySelector('.stat-icon');
        if (icon) {
          icon.style.transform = 'scale(1) rotate(0deg)';
        }
      });
    });

    // Efectos para notificaciones
    document.querySelectorAll('.client-notification-card').forEach(card => {
      card.addEventListener('mouseenter', () => {
        const icon = card.querySelector('.notification-icon');
        if (icon) {
          icon.style.transform = 'scale(1.2)';
        }
      });

      card.addEventListener('mouseleave', () => {
        const icon = card.querySelector('.notification-icon');
        if (icon) {
          icon.style.transform = 'scale(1)';
        }
      });
    });
  }
}

// Funciones de utilidad para el cliente

// Funciones de utilidad para el cliente
class ClientUtils {
  static formatCurrency(amount) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP'
    }).format(amount);
  }

  static formatDate(dateString) {
    return new Intl.DateTimeFormat('es-CO', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    }).format(new Date(dateString));
  }

  static getRelativeTime(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInHours = Math.abs(now - date) / (1000 * 60 * 60);
    
    if (diffInHours < 1) {
      return 'Hace menos de 1 hora';
    } else if (diffInHours < 24) {
      return `Hace ${Math.floor(diffInHours)} horas`;
    } else {
      const diffInDays = Math.floor(diffInHours / 24);
      return `Hace ${diffInDays} días`;
    }
  }
}

// Inicializar dashboard cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
  window.clientDashboard = new ClientDashboard();
  console.log('🎉 Dashboard de cliente inicializado correctamente');
});

// Exportar para uso global
window.ClientToastManager = ClientToastManager;
window.ClientNotificationManager = ClientNotificationManager;
window.ClientCommunicationManager = ClientCommunicationManager;
window.ClientUtils = ClientUtils;