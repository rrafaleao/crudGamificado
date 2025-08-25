// frontend/assets/js/main.js
// Configurações gerais e funções utilitárias

// Configuração da API
const API_BASE_URL = 'backend/api/';

// Estado global da aplicação
const AppState = {
    currentUser: null,
    isLoggedIn: false
};

// Inicialização da aplicação
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Verificar se usuário está logado (localStorage)
    checkAuthStatus();
    
    // Carregar ranking inicial
    if (document.getElementById('rankingContainer')) {
        loadPublicRanking();
    }
    
    // Configurar event listeners
    setupEventListeners();
    
    console.log('🚀 Aplicação inicializada');
}

function checkAuthStatus() {
    const userData = localStorage.getItem('bella_vista_user');
    if (userData) {
        try {
            AppState.currentUser = JSON.parse(userData);
            AppState.isLoggedIn = true;
            
            // Redirecionar para dashboard se estiver na página inicial
            if (window.location.pathname.endsWith('index.html') || window.location.pathname === '/') {
                window.location.href = 'pages/dashboard.html';
            }
        } catch (error) {
            console.error('Erro ao recuperar dados do usuário:', error);
            localStorage.removeItem('bella_vista_user');
        }
    }
}

function setupEventListeners() {
    // Modal handlers
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    
    // Escape key para fechar modais
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllModals();
        }
    });
    
    // Smooth scroll para links internos
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// === FUNÇÕES DE API ===

async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;
    
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    try {
        showLoading(true);
        const response = await fetch(url, finalOptions);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro desconhecido');
        }
        
        return data;
        
    } catch (error) {
        console.error('Erro na API:', error);
        showToast('Erro: ' + error.message, 'error');
        throw error;
    } finally {
        showLoading(false);
    }
}

// === FUNÇÕES DE MODAL ===

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Focus no primeiro input
        const firstInput = modal.querySelector('input');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Limpar formulários
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
}

function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
    document.body.style.overflow = 'auto';
}

function showLoginModal() {
    showModal('loginModal');
}

function showRegisterModal() {
    showModal('registerModal');
}

function switchToLogin() {
    closeModal('registerModal');
    showModal('loginModal');
}

function switchToRegister() {
    closeModal('loginModal');
    showModal('registerModal');
}

// === FUNÇÕES DE TOAST/NOTIFICAÇÃO ===

function showToast(message, type = 'info', duration = 4000) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    // Remover classes anteriores
    toast.className = 'toast';
    
    // Adicionar classe do tipo
    toast.classList.add(`toast-${type}`);
    
    // Definir ícone baseado no tipo
    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };
    
    const icon = icons[type] || icons.info;
    
    toast.innerHTML = `
        <i class="${icon}"></i>
        <span>${message}</span>
        <button onclick="hideToast()" class="toast-close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Mostrar toast
    toast.classList.add('toast-show');
    
    // Auto-hide
    setTimeout(() => {
        hideToast();
    }, duration);
}

function hideToast() {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.classList.remove('toast-show');
    }
}

// === FUNÇÕES DE LOADING ===

let loadingCount = 0;

function showLoading(show = true) {
    if (show) {
        loadingCount++;
        document.body.classList.add('loading-active');
    } else {
        loadingCount--;
        if (loadingCount <= 0) {
            loadingCount = 0;
            document.body.classList.remove('loading-active');
        }
    }
}

// === FUNÇÕES DE RANKING PÚBLICO ===

async function loadPublicRanking() {
    const container = document.getElementById('rankingContainer');
    if (!container) return;
    
    try {
        const response = await apiRequest('usuarios.php?action=ranking');
        const ranking = response.data;
        
        if (ranking.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-trophy"></i>
                    <h3>Nenhum participante ainda</h3>
                    <p>Seja o primeiro a entrar na competição!</p>
                </div>
            `;
            return;
        }
        
        let html = `
            <div class="ranking-header">
                <h3><i class="fas fa-medal"></i> Top ${ranking.length} do Mês</h3>
            </div>
            <div class="ranking-list">
        `;
        
        ranking.forEach((user, index) => {
            const positionClass = index < 3 ? `top-${index + 1}` : '';
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '';
            
            html += `
                <div class="ranking-item ${positionClass}">
                    <div class="ranking-position">
                        ${medal || `#${user.posicao}`}
                    </div>
                    <div class="ranking-user">
                        <span class="user-name">${user.nome}</span>
                        <small>${user.total_reservas} reservas • ${user.total_avaliacoes} avaliações</small>
                    </div>
                    <div class="ranking-points">
                        ${user.pontos_mes_atual} <small>pts</small>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        // Adicionar info do prêmio
        if (ranking.length > 0) {
            html += `
                <div class="ranking-footer">
                    <div class="prize-info">
                        <i class="fas fa-gift"></i>
                        <span><strong>${ranking[0].nome}</strong> está na liderança para ganhar o jantar grátis!</span>
                    </div>
                </div>
            `;
        }
        
        container.innerHTML = html;
        
    } catch (error) {
        container.innerHTML = `
            <div class="error-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Erro ao carregar ranking</h3>
                <p>Tente novamente em alguns instantes.</p>
                <button class="btn btn-outline btn-sm" onclick="loadPublicRanking()">
                    <i class="fas fa-redo"></i> Tentar Novamente
                </button>
            </div>
        `;
    }
}

// === FUNÇÕES DE NAVEGAÇÃO ===

function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function toggleMobileMenu() {
    const navMenu = document.getElementById('navMenu');
    const navToggle = document.querySelector('.nav-toggle');
    
    if (navMenu && navToggle) {
        navMenu.classList.toggle('nav-menu-open');
        navToggle.classList.toggle('nav-toggle-active');
    }
}

// === FUNÇÕES DE UTILIDADE ===

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatTime(timeString) {
    const time = new Date(`2000-01-01 ${timeString}`);
    return time.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatPoints(points) {
    return new Intl.NumberFormat('pt-BR').format(points);
}

function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

function validatePhone(phone) {
    const regex = /^[\d\s\-\(\)]+$/;
    return !phone || regex.test(phone);
}

function sanitizeInput(input) {
    return input.toString().trim();
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// === FUNÇÕES DE STORAGE ===

function saveUserData(userData) {
    localStorage.setItem('bella_vista_user', JSON.stringify(userData));
    AppState.currentUser = userData;
    AppState.isLoggedIn = true;
}

function clearUserData() {
    localStorage.removeItem('bella_vista_user');
    AppState.currentUser = null;
    AppState.isLoggedIn = false;
}

function getUserData() {
    const userData = localStorage.getItem('bella_vista_user');
    return userData ? JSON.parse(userData) : null;
}

// === FUNÇÕES DE LOGOUT ===

function logout() {
    clearUserData();
    showToast('Logout realizado com sucesso!', 'success');
    
    // Redirecionar para página inicial após um delay
    setTimeout(() => {
        window.location.href = '../index.html';
    }, 1000);
}

// === VALIDAÇÃO DE FORMULÁRIOS ===

function validateForm(form, rules) {
    const errors = [];
    const data = new FormData(form);
    const values = {};
    
    // Coletar valores
    for (let [key, value] of data.entries()) {
        values[key] = sanitizeInput(value);
    }
    
    // Aplicar regras de validação
    for (let field in rules) {
        const rule = rules[field];
        const value = values[field];
        
        // Required
        if (rule.required && (!value || value.length === 0)) {
            errors.push(`${rule.label || field} é obrigatório`);
            continue;
        }
        
        // Se não tem valor e não é required, pular outras validações
        if (!value) continue;
        
        // Min length
        if (rule.minLength && value.length < rule.minLength) {
            errors.push(`${rule.label || field} deve ter pelo menos ${rule.minLength} caracteres`);
        }
        
        // Max length
        if (rule.maxLength && value.length > rule.maxLength) {
            errors.push(`${rule.label || field} deve ter no máximo ${rule.maxLength} caracteres`);
        }
        
        // Email
        if (rule.email && !validateEmail(value)) {
            errors.push(`${rule.label || field} deve ser um email válido`);
        }
        
        // Phone
        if (rule.phone && !validatePhone(value)) {
            errors.push(`${rule.label || field} deve ser um telefone válido`);
        }
        
        // Custom validation
        if (rule.custom && typeof rule.custom === 'function') {
            const customError = rule.custom(value, values);
            if (customError) {
                errors.push(customError);
            }
        }
    }
    
    return {
        isValid: errors.length === 0,
        errors: errors,
        values: values
    };
}

// === HELPERS DE ANIMAÇÃO ===

function animateCounter(element, targetValue, duration = 1000) {
    const startValue = 0;
    const startTime = performance.now();
    
    function updateCounter(currentTime) {
        const elapsedTime = currentTime - startTime;
        const progress = Math.min(elapsedTime / duration, 1);
        
        const currentValue = Math.floor(startValue + (targetValue - startValue) * progress);
        element.textContent = formatPoints(currentValue);
        
        if (progress < 1) {
            requestAnimationFrame(updateCounter);
        }
    }
    
    requestAnimationFrame(updateCounter);
}

// === LOG DE DEBUG ===

function debugLog(message, data = null) {
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        console.log(`🐛 DEBUG: ${message}`, data || '');
    }
}

// === EXPORT GLOBAL FUNCTIONS ===

// Disponibilizar funções principais globalmente
window.AppState = AppState;
window.apiRequest = apiRequest;
window.showToast = showToast;
window.showModal = showModal;
window.closeModal = closeModal;
window.showLoginModal = showLoginModal;
window.showRegisterModal = showRegisterModal;
window.switchToLogin = switchToLogin;
window.switchToRegister = switchToRegister;
window.scrollToSection = scrollToSection;
window.toggleMobileMenu = toggleMobileMenu;
window.logout = logout;