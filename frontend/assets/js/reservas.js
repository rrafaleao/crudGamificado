/**
 * JavaScript para gerenciamento de reservas
 * Sistema de Restaurante Gamificado
 */

// Estado global das reservas
let reservasData = [];
let proximasReservas = [];
let reservaEditando = null;

/**
 * Carregar estatísticas do usuário
 */
async function carregarEstatisticas() {
    try {
        const userData = getUserData();
        if (!userData) return;

        const response = await apiRequest('reservas.php?action=estatisticas&usuario_id=' + userData.id);
        
        if (response.success) {
            const stats = response.data;
            
            // Animar contadores
            animateCounter(document.getElementById('total-reservas'), stats.total_reservas);
            animateCounter(document.getElementById('confirmadas'), stats.confirmadas);
            animateCounter(document.getElementById('finalizadas'), stats.finalizadas);
            animateCounter(document.getElementById('mes-atual'), stats.mes_atual);
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

/**
 * Carregar próximas reservas
 */
async function carregarProximasReservas() {
    try {
        const userData = getUserData();
        if (!userData) return;

        const response = await apiRequest('reservas.php?action=proximas&usuario_id=' + userData.id);
        
        if (response.success && response.data.length > 0) {
            proximasReservas = response.data;
            exibirProximasReservas(proximasReservas);
        }
    } catch (error) {
        console.error('Erro ao carregar próximas reservas:', error);
    }
}

/**
 * Exibir próximas reservas
 */
function exibirProximasReservas(reservas) {
    const container = document.getElementById('proximas-reservas');
    const section = document.getElementById('proximas-section');
    
    if (reservas.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    
    container.innerHTML = reservas.map(reserva => `
        <div class="reserva-card proxima">
            <div class="reserva-header">
                <div class="reserva-data">
                    <i class="fas fa-calendar"></i>
                    <span>${formatDate(reserva.data_reserva)} às ${formatTime(reserva.horario)}</span>
                </div>
                <div class="reserva-status status-${reserva.status}">
                    ${getStatusText(reserva.status)}
                </div>
            </div>
            <div class="reserva-content">
                <div class="reserva-info">
                    <p><i class="fas fa-table"></i> Mesa ${reserva.numero_mesa} (${reserva.localizacao})</p>
                    <p><i class="fas fa-users"></i> ${reserva.quantidade_pessoas} pessoa(s)</p>
                </div>
                <div class="reserva-actions">
                    <button onclick="verDetalhes(${reserva.id})" class="btn btn-sm btn-outline">
                        <i class="fas fa-eye"></i>
                        Ver Detalhes
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

/**
 * Carregar todas as reservas
 */
async function carregarReservas(status = null) {
    try {
        const loading = document.getElementById('loading');
        const lista = document.getElementById('reservas-lista');
        const emptyState = document.getElementById('empty-state');
        
        // Mostrar loading
        loading.classList.remove('hidden');
        lista.style.display = 'none';
        emptyState.classList.add('hidden');

        const userData = getUserData();
        if (!userData) return;

        let url = `reservas.php?action=minhas&usuario_id=${userData.id}`;
        if (status) {
            url += `&status=${status}`;
        }

        const response = await apiRequest(url);
        
        // Esconder loading
        loading.classList.add('hidden');
        
        if (response.success) {
            reservasData = response.data;
            
            if (reservasData.length > 0) {
                exibirReservas(reservasData);
                lista.style.display = 'block';
            } else {
                emptyState.classList.remove('hidden');
            }
        } else {
            showToast('Erro ao carregar reservas: ' + response.error, 'error');
            emptyState.classList.remove('hidden');
        }
        
    } catch (error) {
        console.error('Erro ao carregar reservas:', error);
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('empty-state').classList.remove('hidden');
        showToast('Erro ao carregar reservas', 'error');
    }
}

/**
 * Exibir lista de reservas - Versão Simplificada
 */
function exibirReservas(reservas) {
    const container = document.getElementById('reservas-lista');
    
    container.innerHTML = reservas.map(reserva => `
        <div class="reserva-item status-${reserva.status}">
            <h4>Reserva #${reserva.id}</h4>
            <p><strong>Data:</strong> ${formatDate(reserva.data_reserva)} às ${formatTime(reserva.horario)}</p>
            <p><strong>Mesa:</strong> ${reserva.numero_mesa} (${reserva.localizacao})</p>
            <p><strong>Pessoas:</strong> ${reserva.quantidade_pessoas}</p>
            <p><strong>Status:</strong> ${getStatusText(reserva.status)}</p>
            <p><strong>Pontos:</strong> +${reserva.pontos_ganhos}</p>
            ${reserva.observacoes ? `<p><strong>Obs:</strong> ${reserva.observacoes}</p>` : ''}
            
            <div style="margin-top: 10px;">
                <button onclick="verDetalhes(${reserva.id})" class="btn btn-secondary">Ver Detalhes</button>
                
                ${reserva.status === 'confirmada' ? `
                    <button onclick="editarReserva(${reserva.id})" class="btn btn-primary">Editar</button>
                    <button onclick="cancelarReserva(${reserva.id})" class="btn btn-danger">Cancelar</button>
                ` : ''}
                
                ${reserva.status === 'finalizada' && !reserva.avaliacao_id ? `
                    <a href="avaliar.html?reserva=${reserva.id}" class="btn btn-success">Avaliar (+100 pts)</a>
                ` : ''}
                
                ${reserva.status === 'finalizada' && reserva.avaliacao_id ? `
                    <span style="color: green;">✓ Avaliado (${reserva.nota}/5)</span>
                ` : ''}
            </div>
            
            <small style="display: block; margin-top: 10px; color: #666;">
                Reservado em: ${formatDateTime(reserva.created_at)}
            </small>
        </div>
    `).join('');
}

/**
 * Ver detalhes da reserva
 */
async function verDetalhes(reservaId) {
    try {
        const response = await apiRequest(`reservas.php?action=buscar&id=${reservaId}`);
        
        if (response.success) {
            const reserva = response.data;
            exibirDetalhesModal(reserva);
        } else {
            showToast('Erro ao carregar detalhes da reserva', 'error');
        }
    } catch (error) {
        console.error('Erro ao buscar detalhes:', error);
        showToast('Erro ao carregar detalhes', 'error');
    }
}

/**
 * Exibir modal com detalhes da reserva - Versão Simplificada
 */
function exibirDetalhesModal(reserva) {
    const content = document.getElementById('detalhes-content');
    
    content.innerHTML = `
        <p><strong>ID:</strong> ${reserva.id}</p>
        <p><strong>Data:</strong> ${formatDate(reserva.data_reserva)} às ${formatTime(reserva.horario)}</p>
        <p><strong>Mesa:</strong> ${reserva.numero_mesa} - ${reserva.localizacao} (${reserva.capacidade} lugares)</p>
        <p><strong>Pessoas:</strong> ${reserva.quantidade_pessoas}</p>
        <p><strong>Status:</strong> ${getStatusText(reserva.status)}</p>
        <p><strong>Pontos:</strong> +${reserva.pontos_ganhos}</p>
        <p><strong>Nome:</strong> ${reserva.nome_usuario}</p>
        <p><strong>Email:</strong> ${reserva.email}</p>
        <p><strong>Telefone:</strong> ${reserva.telefone}</p>
        ${reserva.observacoes ? `<p><strong>Observações:</strong> ${reserva.observacoes}</p>` : ''}
        <p><strong>Reservado em:</strong> ${formatDateTime(reserva.created_at)}</p>
        ${reserva.updated_at !== reserva.created_at ? `<p><strong>Atualizado em:</strong> ${formatDateTime(reserva.updated_at)}</p>` : ''}
    `;
    
    showModal('modal-detalhes');
}

/**
 * Editar reserva
 */
async function editarReserva(reservaId) {
    try {
        const response = await apiRequest(`reservas.php?action=buscar&id=${reservaId}`);
        
        if (response.success) {
            const reserva = response.data;
            reservaEditando = reserva;
            preencherFormEdicao(reserva);
            showModal('modal-editar');
        } else {
            showToast('Erro ao carregar dados da reserva', 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar reserva para edição:', error);
        showToast('Erro ao carregar dados', 'error');
    }
}

/**
 * Preencher formulário de edição
 */
function preencherFormEdicao(reserva) {
    document.getElementById('edit-reserva-id').value = reserva.id;
    document.getElementById('edit-data').value = reserva.data_reserva;
    document.getElementById('edit-horario').value = reserva.horario;
    document.getElementById('edit-pessoas').value = reserva.quantidade_pessoas;
    document.getElementById('edit-observacoes').value = reserva.observacoes || '';
    
    // Carregar mesas disponíveis
    carregarMesasDisponiveis(reserva.data_reserva, reserva.horario, reserva.quantidade_pessoas, reserva.mesa_id);
}

/**
 * Carregar mesas disponíveis para edição
 */
async function carregarMesasDisponiveis(data, horario, pessoas, mesaAtual = null) {
    try {
        const select = document.getElementById('edit-mesa');
        select.innerHTML = '<option value="">Carregando...</option>';
        
        const response = await apiRequest(`reservas.php?action=mesas-disponiveis&data=${data}&horario=${horario}&quantidade_pessoas=${pessoas}`);
        
        if (response.success) {
            const mesas = response.data;
            
            select.innerHTML = mesas.map(mesa => `
                <option value="${mesa.id}" ${mesa.id == mesaAtual ? 'selected' : ''}>
                    Mesa ${mesa.numero} - ${mesa.localizacao} (${mesa.capacidade} lugares)
                </option>
            `).join('');
            
            // Se a mesa atual não está nas opções, adicionar ela
            if (mesaAtual && !mesas.find(m => m.id == mesaAtual)) {
                const optionAtual = document.createElement('option');
                optionAtual.value = mesaAtual;
                optionAtual.selected = true;
                optionAtual.textContent = `Mesa ${reservaEditando.numero_mesa} - ${reservaEditando.localizacao} (atual)`;
                select.insertBefore(optionAtual, select.firstChild);
            }
            
        } else {
            select.innerHTML = '<option value="">Nenhuma mesa disponível</option>';
        }
    } catch (error) {
        console.error('Erro ao carregar mesas:', error);
        document.getElementById('edit-mesa').innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

/**
 * Salvar edição da reserva
 */
async function salvarEdicao() {
    try {
        const form = document.getElementById('form-editar-reserva');
        const formData = new FormData(form);
        
        const dados = {
            data_reserva: document.getElementById('edit-data').value,
            horario: document.getElementById('edit-horario').value,
            quantidade_pessoas: parseInt(document.getElementById('edit-pessoas').value),
            mesa_id: parseInt(document.getElementById('edit-mesa').value),
            observacoes: document.getElementById('edit-observacoes').value
        };
        
        // Validar campos
        if (!dados.data_reserva || !dados.horario || !dados.quantidade_pessoas || !dados.mesa_id) {
            showToast('Por favor, preencha todos os campos obrigatórios', 'error');
            return;
        }
        
        const reservaId = document.getElementById('edit-reserva-id').value;
        
        const response = await apiRequest(`reservas.php?action=atualizar&id=${reservaId}`, {
            method: 'PUT',
            body: JSON.stringify(dados)
        });
        
        if (response.success) {
            showToast('Reserva atualizada com sucesso!', 'success');
            fecharModal('modal-editar');
            carregarReservas();
            carregarProximasReservas();
        } else {
            showToast('Erro ao atualizar reserva: ' + response.error, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao salvar edição:', error);
        showToast('Erro ao salvar alterações', 'error');
    }
}

/**
 * Cancelar reserva
 */
function cancelarReserva(reservaId) {
    showConfirmar(
        'Cancelar Reserva',
        'Tem certeza que deseja cancelar esta reserva? Os pontos ganhos serão removidos.',
        () => confirmarCancelamento(reservaId)
    );
}

/**
 * Confirmar cancelamento
 */
async function confirmarCancelamento(reservaId) {
    try {
        const userData = getUserData();
        const response = await apiRequest(`reservas.php?action=cancelar&id=${reservaId}&usuario_id=${userData.id}`, {
            method: 'DELETE'
        });
        
        if (response.success) {
            showToast('Reserva cancelada com sucesso', 'success');
            carregarReservas();
            carregarProximasReservas();
            carregarEstatisticas();
        } else {
            showToast('Erro ao cancelar reserva: ' + response.error, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao cancelar reserva:', error);
        showToast('Erro ao cancelar reserva', 'error');
    }
    
    fecharModal('modal-confirmar');
}

/**
 * Aplicar filtros
 */
function aplicarFiltros() {
    const status = document.getElementById('filtro-status').value;
    carregarReservas(status);
}

/**
 * Event listeners para o formulário de edição
 */
document.addEventListener('DOMContentLoaded', function() {
    // Atualizar mesas quando mudar data, horário ou pessoas
    const editData = document.getElementById('edit-data');
    const editHorario = document.getElementById('edit-horario');
    const editPessoas = document.getElementById('edit-pessoas');
    
    if (editData && editHorario && editPessoas) {
        [editData, editHorario, editPessoas].forEach(campo => {
            campo.addEventListener('change', function() {
                const data = editData.value;
                const horario = editHorario.value;
                const pessoas = editPessoas.value;
                
                if (data && horario && pessoas) {
                    carregarMesasDisponiveis(data, horario, pessoas);
                }
            });
        });
    }
});

/**
 * Funções auxiliares
 */

function getStatusText(status) {
    const statusMap = {
        'pendente': 'Pendente',
        'confirmada': 'Confirmada', 
        'finalizada': 'Finalizada',
        'cancelada': 'Cancelada'
    };
    return statusMap[status] || status;
}

function getStatusIcon(status) {
    const iconMap = {
        'pendente': 'fa-clock',
        'confirmada': 'fa-check-circle',
        'finalizada': 'fa-flag-checkered', 
        'cancelada': 'fa-times-circle'
    };
    return iconMap[status] || 'fa-question-circle';
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function formatTime(timeString) {
    return timeString.substring(0, 5);
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleString('pt-BR');
}

// Funções modais simplificadas
function showModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function fecharModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function showConfirmar(titulo, mensagem, callback) {
    document.getElementById('confirmar-titulo').textContent = titulo;
    document.getElementById('confirmar-mensagem').textContent = mensagem;
    document.getElementById('btn-confirmar-acao').onclick = callback;
    showModal('modal-confirmar');
}