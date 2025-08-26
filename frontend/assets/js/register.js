// register.js - Script para o formulário de registro
// Este arquivo deve ser incluído na página de registro

// Função para fazer requisições à API
async function apiRequest(endpoint, options = {}) {
    const baseUrl = '/backend/api/'; // Ajuste conforme sua estrutura
    const url = baseUrl + endpoint;
    
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    };
    
    const config = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, config);
        const data = await response.json();
        
        if (!response.ok && !data.success) {
            throw new Error(data.error || 'Erro na requisição');
        }
        
        return data;
    } catch (error) {
        console.error('Erro na API:', error);
        throw error;
    }
}

// Função para exibir mensagens (toast)
function showToast(message, type = 'info') {
    // Criar elemento do toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <span>${message}</span>
            <button class="toast-close">&times;</button>
        </div>
    `;
    
    // Adicionar estilos se não existirem
    if (!document.getElementById('toast-styles')) {
        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = `
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                z-index: 9999;
                min-width: 300px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            .toast.show {
                transform: translateX(0);
            }
            .toast-info { background: #3498db; }
            .toast-success { background: #27ae60; }
            .toast-error { background: #e74c3c; }
            .toast-warning { background: #f39c12; }
            .toast-content {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .toast-close {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                margin-left: 10px;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Adicionar ao DOM
    document.body.appendChild(toast);
    
    // Mostrar toast
    setTimeout(() => toast.classList.add('show'), 100);
    
    // Event listener para fechar
    toast.querySelector('.toast-close').addEventListener('click', () => {
        removeToast(toast);
    });
    
    // Auto-remover após 5 segundos
    setTimeout(() => removeToast(toast), 5000);
}

function removeToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => {
        if (document.body.contains(toast)) {
            document.body.removeChild(toast);
        }
    }, 300);
}

// Função principal para lidar com o registro
async function handleRegister(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Coletar dados do formulário
    const formData = {
        nome: document.getElementById('name').value.trim(),
        email: document.getElementById('email').value.trim(),
        senha: document.getElementById('password').value
    };
    
    // Validação básica
    if (!validateForm(formData)) {
        return;
    }
    
    // Estado de carregamento
    setButtonLoading(submitBtn, true);
    
    try {
        const response = await apiRequest('auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(formData)
        });
        
        if (response.success) {
            showToast(`Conta criada com sucesso! Bem-vindo(a), ${response.data.user.nome}!`, 'success');
            
            // Opcional: salvar dados do usuário no localStorage
            if (response.data.token) {
                localStorage.setItem('user_token', response.data.token);
                localStorage.setItem('user_data', JSON.stringify(response.data.user));
            }
            
            // Redirecionar após 2 segundos
            setTimeout(() => {
                window.location.href = '../index.html'; // ou para onde quiser redirecionar
            }, 2000);
            
        } else {
            showToast(response.error || 'Erro ao criar conta', 'error');
        }
        
    } catch (error) {
        console.error('Erro no registro:', error);
        showToast('Erro de conexão. Tente novamente.', 'error');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

// Validação do formulário
function validateForm(data) {
    const errors = [];
    
    if (!data.nome || data.nome.length < 2) {
        errors.push('Nome deve ter pelo menos 2 caracteres');
    }
    
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

// Validar email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Controlar estado de carregamento do botão
function setButtonLoading(button, loading) {
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Registrando...';
        button.style.opacity = '0.7';
    } else {
        button.disabled = false;
        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        }
        button.style.opacity = '1';
    }
}

// Inicializar quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    // Encontrar o formulário e adicionar event listener
    const registerForm = document.querySelector('.auth-form');
    
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    
    // Remover a validação antiga que estava no HTML
    const oldScript = document.querySelector('script:last-of-type');
    if (oldScript && oldScript.textContent.includes('confirm-password')) {
        oldScript.remove();
    }
});