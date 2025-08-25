/**
 * AUTH.JS - Sistema de Autenticação
 * Gerencia login, cadastro, logout e sessão do usuário
 */

// Estado da autenticação
const AuthState = {
    user: null,
    isLoggedIn: false,
    loginAttempts: 0,
    maxLoginAttempts: 3
};

// Elementos do DOM
const authElements = {
    loginModal: null,
    registerModal: null,
    loginForm: null,
    registerForm: null,
    logoutBtn: null,
    userInfo: null
};

/**
 * Inicializa o sistema de autenticação
 */
function initAuth() {
    // Cache dos elementos DOM
    authElements.loginModal = document.getElementById('loginModal');
    authElements.registerModal = document.getElementById('registerModal');
    authElements.loginForm = document.getElementById('loginForm');
    authElements.registerForm = document.getElementById('registerForm');
    authElements.logoutBtn = document.getElementById('logoutBtn');
    authElements.userInfo = document.getElementById('userInfo');

    // Event listeners
    setupAuthEventListeners();
    
    // Verificar sessão existente
    checkExistingSession();
    
    // Atualizar UI baseada no estado
    updateAuthUI();
}

/**
 * Configura todos os event listeners de autenticação
 */
function setupAuthEventListeners() {
    // Formulário de login
    if (authElements.loginForm) {
        authElements.loginForm.addEventListener('submit', handleLogin);
    }
    
    // Formulário de cadastro
    if (authElements.registerForm) {
        authElements.registerForm.addEventListener('submit', handleRegister);
    }
    
    // Botão de logout
    if (authElements.logoutBtn) {
        authElements.logoutBtn.addEventListener('click', handleLogout);
    }
    
    // Links para alternar entre modais
    document.addEventListener('click', (e) => {
        if (e.target.matches('[data-toggle-modal]')) {
            const targetModal = e.target.getAttribute('data-toggle-modal');
            closeAllModals();
            showModal(targetModal);
        }
    });
    
    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

/**
 * Manipula o envio do formulário de login
 */
async function handleLogin(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const loginData = {
        email: formData.get('email'),
        senha: formData.get('senha')
    };
    
    // Validação client-side
    if (!validateLoginData(loginData)) {
        return;
    }
    
    // Verificar tentativas de login
    if (AuthState.loginAttempts >= AuthState.maxLoginAttempts) {
        showToast('Muitas tentativas de login. Tente novamente em 5 minutos.', 'error');
        return;
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    setButtonLoading(submitBtn, true);
    
    try {
        const response = await apiRequest('auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify(loginData)
        });
        
        if (response.success) {
            // Login bem-sucedido
            AuthState.user = response.data.user;
            AuthState.isLoggedIn = true;
            AuthState.loginAttempts = 0;
            
            // Salvar dados da sessão
            saveUserSession(response.data);
            
            // Fechar modal e atualizar UI
            closeModal('loginModal');
            updateAuthUI();
            
            // Animação de pontos se aplicável
            if (response.data.user.pontos_mes_atual > 0) {
                animatePointsGain(response.data.user.pontos_mes_atual, 0);
            }
            
            showToast(`Bem-vindo(a), ${response.data.user.nome}!`, 'success');
            
            // Redirecionar para dashboard se não estiver na landing page
            if (window.location.pathname !== '/index.html') {
                window.location.href = 'pages/dashboard.html';
            }
            
        } else {
            AuthState.loginAttempts++;
            showToast(response.error || 'Erro ao fazer login', 'error');
        }
        
    } catch (error) {
        console.error('Erro no login:', error);
        AuthState.loginAttempts++;
        showToast('Erro de conexão. Tente novamente.', 'error');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

/**
 * Manipula o envio do formulário de cadastro
 */
async function handleRegister(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const registerData = {
        nome: formData.get('nome'),
        email: formData.get('email'),
        telefone: formData.get('telefone'),
        senha: formData.get('senha'),
        confirmarSenha: formData.get('confirmarSenha')
    };
    
    // Validação client-side
    if (!validateRegisterData(registerData)) {
        return;
    }
    
    const submitBtn = event.target.querySelector('button[type="submit"]');
    setButtonLoading(submitBtn, true);
    
    try {
        const response = await apiRequest('auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(registerData)
        });
        
        if (response.success) {
            // Cadastro bem-sucedido - fazer login automático
            AuthState.user = response.data.user;
            AuthState.isLoggedIn = true;
            
            // Salvar dados da sessão
            saveUserSession(response.data);
            
            // Fechar modal e atualizar UI
            closeModal('registerModal');
            updateAuthUI();
            
            showToast(`Conta criada com sucesso! Bem-vindo(a), ${response.data.user.nome}!`, 'success');
            
            // Mostrar explicação do sistema de pontos para novos usuários
            setTimeout(() => {
                showWelcomeMessage();
            }, 2000);
            
        } else {
            showToast(response.error || 'Erro ao criar conta', 'error');
        }
        
    } catch (error) {
        console.error('Erro no cadastro:', error);
        showToast('Erro de conexão. Tente novamente.', 'error');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

/**
 * Manipula o logout do usuário
 */
async function handleLogout() {
    try {
        // Chamar endpoint de logout no backend (se necessário)
        await apiRequest('auth.php?action=logout', { method: 'POST' });
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
    } finally {
        // Limpar estado local independente da resposta do servidor
        clearUserSession();
        AuthState.user = null;
        AuthState.isLoggedIn = false;
        
        updateAuthUI();
        showToast('Logout realizado com sucesso!', 'success');
        
        // Redirecionar para home se estiver em página protegida
        if (window.location.pathname.includes('/pages/')) {
            window.location.href = '../index.html';
        }
    }
}

/**
 * Verifica se existe uma sessão válida ao carregar a página
 */
async function checkExistingSession() {
    const userData = getUserSession();
    
    if (!userData || !userData.token) {
        return;
    }
    
    try {
        // Validar token no servidor
        const response = await apiRequest('auth.php?action=validate', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${userData.token}`
            }
        });
        
        if (response.success && response.data.user) {
            AuthState.user = response.data.user;
            AuthState.isLoggedIn = true;
            updateAuthUI();
        } else {
            // Token inválido - limpar sessão
            clearUserSession();
        }
        
    } catch (error) {
        console.error('Erro ao validar sessão:', error);
        clearUserSession();
    }
}

/**
 * Atualiza a interface baseada no estado de autenticação
 */
function updateAuthUI() {
    const authLinks = document.querySelectorAll('[data-auth-required]');
    const guestLinks = document.querySelectorAll('[data-guest-only]');
    const userNameElements = document.querySelectorAll('[data-user-name]');
    const userPointsElements = document.querySelectorAll('[data-user-points]');
    
    if (AuthState.isLoggedIn && AuthState.user) {
        // Mostrar elementos para usuários logados
        authLinks.forEach(el => el.style.display = 'block');
        guestLinks.forEach(el => el.style.display = 'none');
        
        // Atualizar informações do usuário
        userNameElements.forEach(el => el.textContent = AuthState.user.nome);
        userPointsElements.forEach(el => {
            el.textContent = formatPoints(AuthState.user.pontos_mes_atual);
            el.setAttribute('data-points', AuthState.user.pontos_mes_atual);
        });
        
    } else {
        // Mostrar elementos para visitantes
        authLinks.forEach(el => el.style.display = 'none');
        guestLinks.forEach(el => el.style.display = 'block');
    }
}

/**
 * Valida dados de login
 */
function validateLoginData(data) {
    const errors = [];
    
    if (!data.email || !isValidEmail(data.email)) {
        errors.push('Email válido é obrigatório');
    }
    
    if (!data.senha || data.senha.length < 6) {
        errors.push('Senha deve ter pelo menos 6 caracteres');
    }
    
    if (errors.length > 0) {
        showToast(errors.join('<br>'), 'error');
        return false;
    }
    
    return true;
}

/**
 * Valida dados de cadastro
 */
function validateRegisterData(data) {
    const errors = [];
    
    if (!data.nome || data.nome.length < 2) {
        errors.push('Nome deve ter pelo menos 2 caracteres');
    }
    
    if (!data.email || !isValidEmail(data.email)) {
        errors.push('Email válido é obrigatório');
    }
    
    if (!data.telefone || !isValidPhone(data.telefone)) {
        errors.push('Telefone válido é obrigatório');
    }
    
    if (!data.senha || data.senha.length < 6) {
        errors.push('Senha deve ter pelo menos 6 caracteres');
    }
    
    if (data.senha !== data.confirmarSenha) {
        errors.push('Senhas não coincidem');
    }
    
    if (errors.length > 0) {
        showToast(errors.join('<br>'), 'error');
        return false;
    }
    
    return true;
}

/**
 * Valida formato de email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Valida formato de telefone brasileiro
 */
function isValidPhone(phone) {
    const cleanPhone = phone.replace(/\D/g, '');
    return cleanPhone.length >= 10 && cleanPhone.length <= 11;
}

/**
 * Salva dados da sessão no localStorage
 */
function saveUserSession(data) {
    const sessionData = {
        user: data.user,
        token: data.token,
        expiresAt: Date.now() + (24 * 60 * 60 * 1000) // 24 horas
    };
    
    localStorage.setItem('restaurante_session', JSON.stringify(sessionData));
}

/**
 * Recupera dados da sessão do localStorage
 */
function getUserSession() {
    const sessionData = localStorage.getItem('restaurante_session');
    
    if (!sessionData) {
        return null;
    }
    
    try {
        const data = JSON.parse(sessionData);
        
        // Verificar se não expirou
        if (Date.now() > data.expiresAt) {
            clearUserSession();
            return null;
        }
        
        return data;
    } catch (error) {
        console.error('Erro ao ler sessão:', error);
        clearUserSession();
        return null;
    }
}

/**
 * Limpa dados da sessão
 */
function clearUserSession() {
    localStorage.removeItem('restaurante_session');
}

/**
 * Define estado de carregamento no botão
 */
function setButtonLoading(button, loading) {
    if (loading) {
        button.classList.add('btn-loading');
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Carregando...';
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        }
    }
}

/**
 * Fecha todos os modais abertos
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.classList.remove('show');
    });
}

/**
 * Mostra mensagem de boas-vindas para novos usuários
 */
function showWelcomeMessage() {
    const welcomeHtml = `
        <div class="alert alert-info">
            <h4>🎉 Bem-vindo ao nosso sistema gamificado!</h4>
            <p><strong>Como ganhar pontos:</strong></p>
            <ul>
                <li>50 pontos para cada reserva confirmada</li>
                <li>100 pontos para cada avaliação após a visita</li>
                <li>O usuário com mais pontos no mês ganha um jantar grátis!</li>
            </ul>
            <p>Seus pontos são resetados todo mês. Boa sorte na competição!</p>
        </div>
    `;
    
    // Criar modal temporário para exibir a mensagem
    const modal = document.createElement('div');
    modal.className = 'modal show';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Sistema de Pontos</h3>
                <button type="button" class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="modal-body">
                ${welcomeHtml}
                <div class="text-center">
                    <button class="btn btn-primary" onclick="this.closest('.modal').remove()">
                        Entendi, vamos começar!
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Auto-remover após 10 segundos
    setTimeout(() => {
        if (document.body.contains(modal)) {
            modal.remove();
        }
    }, 10000);
}

/**
 * Obtém dados do usuário atual
 */
function getCurrentUser() {
    return AuthState.user;
}

/**
 * Verifica se o usuário está logado
 */
function isUserLoggedIn() {
    return AuthState.isLoggedIn;
}

/**
 * Atualiza dados do usuário no estado
 */
function updateUserData(newUserData) {
    if (AuthState.isLoggedIn && AuthState.user) {
        AuthState.user = { ...AuthState.user, ...newUserData };
        
        // Atualizar sessão no localStorage
        const sessionData = getUserSession();
        if (sessionData) {
            sessionData.user = AuthState.user;
            localStorage.setItem('restaurante_session', JSON.stringify(sessionData));
        }
        
        updateAuthUI();
    }
}

/**
 * Middleware para verificar autenticação em páginas protegidas
 */
function requireAuth() {
    if (!AuthState.isLoggedIn) {
        showToast('Você precisa estar logado para acessar esta página', 'error');
        window.location.href = '../index.html';
        return false;
    }
    return true;
}

/**
 * Anima ganho de pontos
 */
function animatePointsGain(newPoints, oldPoints = 0) {
    const pointsElements = document.querySelectorAll('[data-user-points]');
    
    pointsElements.forEach(element => {
        animateCounter(element, oldPoints, newPoints);
        
        // Adicionar efeito visual
        element.style.transform = 'scale(1.2)';
        element.style.color = 'var(--success-color)';
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = '';
        }, 1000);
    });
    
    // Mostrar toast com ganho de pontos
    if (newPoints > oldPoints) {
        const gained = newPoints - oldPoints;
        showToast(`+${gained} pontos ganhos! 🎉`, 'success');
    }
}

/**
 * Obtém header de autorização para requisições
 */
function getAuthHeaders() {
    const sessionData = getUserSession();
    
    if (sessionData && sessionData.token) {
        return {
            'Authorization': `Bearer ${sessionData.token}`
        };
    }
    
    return {};
}

/**
 * Intercepta requisições para adicionar autenticação automaticamente
 */
const originalApiRequest = window.apiRequest;
window.apiRequest = async function(endpoint, options = {}) {
    // Adicionar headers de autenticação se disponível
    const authHeaders = getAuthHeaders();
    
    if (Object.keys(authHeaders).length > 0) {
        options.headers = {
            ...options.headers,
            ...authHeaders
        };
    }
    
    try {
        return await originalApiRequest(endpoint, options);
    } catch (error) {
        // Se receber 401 (não autorizado), fazer logout automático
        if (error.status === 401) {
            console.warn('Token expirado ou inválido, fazendo logout automático');
            clearUserSession();
            AuthState.user = null;
            AuthState.isLoggedIn = false;
            updateAuthUI();
            
            showToast('Sua sessão expirou. Faça login novamente.', 'warning');
        }
        
        throw error;
    }
};

// Exportar funções para uso global
window.AuthState = AuthState;
window.initAuth = initAuth;
window.handleLogin = handleLogin;
window.handleRegister = handleRegister;
window.handleLogout = handleLogout;
window.getCurrentUser = getCurrentUser;
window.isUserLoggedIn = isUserLoggedIn;
window.updateUserData = updateUserData;
window.requireAuth = requireAuth;
window.animatePointsGain = animatePointsGain;
window.getAuthHeaders = getAuthHeaders;

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuth);
} else {
    initAuth();
}