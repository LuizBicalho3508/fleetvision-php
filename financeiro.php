<?php
if (!isset($_SESSION['user_id'])) exit;
?>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="h-full flex flex-col bg-slate-50 relative overflow-hidden font-inter">
    
    <div class="px-8 py-6 bg-white border-b border-slate-200 flex justify-between items-center shadow-sm z-20 shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <div class="p-2 bg-indigo-100 text-indigo-600 rounded-lg"><i class="fas fa-wallet"></i></div>
                Gestão Financeira
            </h1>
            <p class="text-sm text-slate-500 mt-1 ml-11">Controle de faturamento, clientes e integrações.</p>
        </div>
        
        <div class="flex items-center gap-4">
            <div id="api-status" class="hidden px-4 py-2 rounded-full bg-red-50 text-red-600 text-xs font-bold border border-red-100 flex items-center gap-2 animate-pulse shadow-sm">
                <div class="w-2 h-2 rounded-full bg-red-500"></div> API Desconectada
            </div>
            
            <div id="balance-card" class="hidden group relative bg-slate-900 text-white pl-6 pr-8 py-3 rounded-2xl shadow-xl shadow-slate-200 border border-slate-700 flex flex-col items-end min-w-[200px] cursor-default transition-all hover:translate-y-[-2px]">
                <div class="absolute -left-6 -bottom-6 w-20 h-20 bg-white/5 rounded-full blur-2xl group-hover:bg-white/10 transition"></div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1 z-10">Saldo Disponível (Asaas)</span>
                <div class="flex items-center gap-3 z-10">
                    <span class="text-2xl font-bold text-emerald-400 tracking-tight" id="balance-value">R$ ...</span>
                    <button onclick="loadBalance()" class="text-slate-500 hover:text-white transition"><i class="fas fa-sync-alt text-xs"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto custom-scroll p-8">
        <div class="max-w-[1600px] mx-auto space-y-8">

            <div class="bg-white p-1.5 rounded-xl border border-slate-200 inline-flex shadow-sm sticky top-0 z-30">
                <button onclick="switchTab('charges')" class="tab-btn px-6 py-2.5 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2 bg-indigo-50 text-indigo-700 shadow-sm" id="tab-charges">
                    <i class="fas fa-chart-pie"></i> Visão Geral
                </button>
                <button onclick="switchTab('customers')" class="tab-btn px-6 py-2.5 rounded-lg text-sm font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all duration-200 flex items-center gap-2" id="tab-customers">
                    <i class="fas fa-users"></i> Clientes
                </button>
                <button onclick="switchTab('config')" class="tab-btn px-6 py-2.5 rounded-lg text-sm font-medium text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all duration-200 flex items-center gap-2" id="tab-config">
                    <i class="fas fa-cogs"></i> Configurações
                </button>
            </div>

            <div id="view-charges" class="tab-content space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div onclick="openCardDetailModal('RECEIVED', 'Cobranças Recebidas')" class="cursor-pointer bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-lg hover:-translate-y-1 transition relative overflow-hidden group">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Recebidas</p>
                                <h3 class="text-3xl font-bold text-slate-800" id="kpi-received-count">-</h3>
                            </div>
                            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition shadow-sm">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 w-3/4 rounded-full"></div> 
                        </div>
                    </div>

                    <div onclick="openCardDetailModal('PENDING', 'Cobranças Pendentes')" class="cursor-pointer bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-lg hover:-translate-y-1 transition relative overflow-hidden group">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Pendentes</p>
                                <h3 class="text-3xl font-bold text-slate-800" id="kpi-pending-count">-</h3>
                            </div>
                            <div class="p-3 bg-amber-50 text-amber-500 rounded-xl group-hover:bg-amber-500 group-hover:text-white transition shadow-sm">
                                <i class="fas fa-clock text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500 w-1/2 rounded-full"></div> 
                        </div>
                    </div>

                    <div onclick="openCardDetailModal('OVERDUE', 'Cobranças Vencidas')" class="cursor-pointer bg-white p-6 rounded-2xl border border-slate-100 shadow-sm hover:shadow-lg hover:-translate-y-1 transition relative overflow-hidden group">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-slate-400 text-xs font-bold uppercase tracking-wider mb-2">Vencidas</p>
                                <h3 class="text-3xl font-bold text-slate-800" id="kpi-overdue-count">-</h3>
                            </div>
                            <div class="p-3 bg-red-50 text-red-500 rounded-xl group-hover:bg-red-500 group-hover:text-white transition shadow-sm">
                                <i class="fas fa-exclamation-triangle text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 h-1 w-full bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-red-500 w-1/4 rounded-full"></div> 
                        </div>
                    </div>

                    <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 text-white p-6 rounded-2xl shadow-lg shadow-indigo-200 relative overflow-hidden flex flex-col justify-between">
                        <div class="absolute right-0 top-0 p-6 opacity-10"><i class="fas fa-wallet text-6xl"></i></div>
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider mb-1">Total na Tela</p>
                            <h3 class="text-3xl font-bold" id="kpi-total-val">R$ 0,00</h3>
                        </div>
                        <div class="mt-4 text-xs font-medium bg-white/10 w-fit px-3 py-1 rounded-lg border border-white/10">
                            <i class="fas fa-info-circle mr-1"></i> Baseado nos filtros
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 hidden md:block">
                    <h3 class="text-sm font-bold text-slate-700 mb-4 flex items-center gap-2"><i class="fas fa-chart-area text-indigo-500"></i> Fluxo de Recebimentos (Últimos 30 dias)</h3>
                    <div id="chart-revenue" class="w-full h-64 bg-slate-50 rounded-xl flex items-center justify-center text-slate-400 text-sm">
                        <i class="fas fa-circle-notch fa-spin mr-2"></i> Carregando dados...
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col">
                    <div class="p-5 border-b border-slate-100 flex flex-col lg:flex-row justify-between items-center gap-4 bg-slate-50/30">
                        <div class="flex flex-col md:flex-row gap-3 w-full lg:w-auto">
                            <div class="relative group w-full md:w-64">
                                <i class="fas fa-search absolute left-4 top-3.5 text-slate-400 group-focus-within:text-indigo-500 transition"></i>
                                <input type="text" id="charge-search" placeholder="Buscar cliente..." 
                                       class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none text-sm transition shadow-sm"
                                       oninput="debouncedSearchCharges()">
                            </div>
                            <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-2 shadow-sm">
                                <input type="date" id="filter-date-start" class="text-xs text-slate-600 outline-none bg-transparent" onchange="loadCharges()">
                                <span class="text-slate-300 text-xs">até</span>
                                <input type="date" id="filter-date-end" class="text-xs text-slate-600 outline-none bg-transparent" onchange="loadCharges()">
                            </div>
                        </div>
                        
                        <button onclick="openModalCharge()" class="w-full lg:w-auto bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-lg shadow-indigo-100 transition flex items-center justify-center gap-2 transform active:scale-95">
                            <i class="fas fa-plus"></i> <span class="hidden sm:inline">Nova Cobrança</span>
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-100">
                                <tr>
                                    <th class="p-5 pl-6 w-1/3">Cliente / Descrição</th>
                                    <th class="p-5">Valor</th>
                                    <th class="p-5">Vencimento</th>
                                    <th class="p-5 text-center">Meio</th>
                                    <th class="p-5 text-center">Status</th>
                                    <th class="p-5 pr-6 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody id="list-charges" class="text-sm divide-y divide-slate-50 text-slate-600"></tbody>
                        </table>
                    </div>

                    <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center rounded-b-2xl">
                        <span class="text-xs text-slate-500 font-medium bg-white px-3 py-1.5 rounded border border-slate-200 shadow-sm" id="page-info">Página 1</span>
                        <div class="flex gap-2">
                            <button onclick="prevPage()" id="btn-prev" class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-bold hover:bg-slate-50 disabled:opacity-50 transition shadow-sm flex items-center gap-1"><i class="fas fa-chevron-left"></i> Anterior</button>
                            <button onclick="nextPage()" id="btn-next" class="px-4 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-xs font-bold hover:bg-slate-50 disabled:opacity-50 transition shadow-sm flex items-center gap-1">Próximo <i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-customers" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col min-h-[500px]">
                    <div class="p-5 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center gap-4 bg-slate-50/30">
                        <div class="relative w-full md:w-96 group">
                            <i class="fas fa-search absolute left-4 top-3.5 text-slate-400 z-10"></i>
                            <input type="text" id="customer-search" placeholder="Nome, CPF ou CNPJ..." 
                                   class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none text-sm transition shadow-sm"
                                   oninput="debouncedSearchCustomers()">
                        </div>
                        <button onclick="openModalCustomer()" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-bold text-sm shadow-md transition flex items-center justify-center gap-2 active:scale-95">
                            <i class="fas fa-user-plus"></i> Novo Cliente
                        </button>
                    </div>
                    
                    <div class="flex-1 overflow-x-auto custom-scroll">
                        <table class="w-full text-left border-collapse">
                            <thead class="bg-slate-50 text-[11px] uppercase text-slate-500 font-bold border-b border-slate-100">
                                <tr>
                                    <th class="p-5 pl-6">Nome</th>
                                    <th class="p-5">Documento</th>
                                    <th class="p-5">Email</th>
                                    <th class="p-5 pr-6 text-right">ID Sistema</th>
                                </tr>
                            </thead>
                            <tbody id="list-customers" class="text-sm divide-y divide-slate-50 text-slate-600"></tbody>
                        </table>
                    </div>
                    <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center rounded-b-2xl">
                        <span class="text-xs text-slate-500 font-medium bg-white px-3 py-1 rounded border border-slate-200" id="cust-page-info">Página 1</span>
                        <div class="flex gap-2">
                            <button onclick="custPrevPage()" id="btn-cust-prev" class="px-3 py-1.5 rounded border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 text-xs font-bold disabled:opacity-50">Ant.</button>
                            <button onclick="custNextPage()" id="btn-cust-next" class="px-3 py-1.5 rounded border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 text-xs font-bold disabled:opacity-50">Prox.</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="view-config" class="tab-content hidden animate-in zoom-in-95 duration-300">
                <div class="bg-white max-w-3xl mx-auto rounded-2xl shadow-lg border border-slate-200 p-10 text-center">
                    <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-key text-3xl text-slate-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-2">Integração Asaas</h3>
                    <p class="text-slate-500 mb-8 max-w-md mx-auto">Insira sua chave de API do Asaas para habilitar a emissão de boletos e gestão financeira automática.</p>
                    
                    <div class="mb-8 text-left max-w-lg mx-auto">
                        <label class="block text-xs font-bold text-slate-700 uppercase mb-2 ml-1">Chave de API (Produção)</label>
                        <div class="relative group">
                            <input type="password" id="config-apikey" class="w-full pl-12 pr-12 py-4 rounded-xl border border-slate-300 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none font-mono text-sm bg-slate-50 transition text-slate-700 shadow-sm">
                            <div class="absolute left-0 top-0 h-full w-12 flex items-center justify-center text-slate-400"><i class="fas fa-lock"></i></div>
                            <button onclick="toggleKeyVisibility()" class="absolute right-0 top-0 h-full w-12 flex items-center justify-center text-slate-400 hover:text-indigo-600 transition cursor-pointer"><i class="far fa-eye"></i></button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2 ml-1"><i class="fas fa-shield-alt mr-1"></i> Sua chave é armazenada de forma segura.</p>
                    </div>
                    
                    <button onclick="saveConfig()" class="bg-slate-900 hover:bg-black text-white px-10 py-3.5 rounded-xl font-bold transition shadow-xl shadow-slate-200 active:scale-95 flex items-center gap-2 mx-auto">
                        <i class="fas fa-save"></i> Salvar Configuração
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="modal-charge" class="fixed inset-0 bg-slate-900/60 hidden z-[100] flex items-center justify-center p-4 backdrop-blur-md transition-opacity opacity-0">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-all duration-300" id="modal-charge-content">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <div>
                <h3 class="font-bold text-lg text-slate-800">Nova Cobrança</h3>
                <p class="text-xs text-slate-500">Emita boletos ou PIX rapidamente.</p>
            </div>
            <button onclick="closeModal('modal-charge')" class="w-8 h-8 rounded-full hover:bg-red-50 text-slate-400 hover:text-red-500 flex items-center justify-center transition"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-5">
            
            <div class="relative">
                <label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">CLIENTE</label>
                <div class="relative">
                    <input type="text" id="charge-customer-search" placeholder="Digite para buscar..." class="w-full pl-10 p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 outline-none transition text-sm font-semibold text-slate-700" autocomplete="off">
                    <i class="fas fa-user absolute left-3.5 top-3.5 text-slate-400 pointer-events-none"></i>
                    <input type="hidden" id="charge-customer-id">
                    
                    <div id="customer-dropdown-list" class="absolute w-full bg-white border border-slate-200 rounded-xl mt-1 shadow-2xl max-h-48 overflow-y-auto hidden z-50 divide-y divide-slate-50"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">VALOR (R$)</label>
                    <div class="relative">
                        <input type="number" id="charge-value" step="0.01" class="w-full pl-10 p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-indigo-500 outline-none transition font-bold text-slate-700" placeholder="0.00">
                        <span class="absolute left-3.5 top-3 text-slate-400 font-bold text-sm">R$</span>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">VENCIMENTO</label>
                    <input type="date" id="charge-duedate" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-indigo-500 outline-none transition text-slate-600 cursor-pointer font-medium">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">FORMA DE PAGAMENTO</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer relative">
                        <input type="radio" name="payType" value="BOLETO" class="peer hidden" checked>
                        <div class="p-3 rounded-xl border border-slate-200 bg-white peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 text-center transition hover:bg-slate-50 font-bold text-sm flex items-center justify-center gap-2 shadow-sm">
                            <i class="fas fa-barcode"></i> Boleto
                        </div>
                        <div class="absolute top-2 right-2 text-indigo-600 opacity-0 peer-checked:opacity-100 transition"><i class="fas fa-check-circle"></i></div>
                    </label>
                    <label class="cursor-pointer relative">
                        <input type="radio" name="payType" value="PIX" class="peer hidden">
                        <div class="p-3 rounded-xl border border-slate-200 bg-white peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 text-center transition hover:bg-slate-50 font-bold text-sm flex items-center justify-center gap-2 shadow-sm">
                            <i class="brands fa-pix"></i> PIX
                        </div>
                        <div class="absolute top-2 right-2 text-indigo-600 opacity-0 peer-checked:opacity-100 transition"><i class="fas fa-check-circle"></i></div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">DESCRIÇÃO</label>
                <input type="text" id="charge-desc" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-indigo-500 outline-none transition text-sm" placeholder="Ex: Mensalidade Rastreamento">
            </div>

            <button onclick="createCharge()" class="w-full bg-indigo-600 text-white font-bold py-3.5 rounded-xl hover:bg-indigo-700 mt-2 shadow-lg shadow-indigo-100 transition active:scale-95 flex items-center justify-center gap-2">
                <span>Emitir Cobrança</span> <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<div id="modal-customer" class="fixed inset-0 bg-slate-900/60 hidden z-[100] flex items-center justify-center p-4 backdrop-blur-md transition-opacity opacity-0">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-all duration-300" id="modal-customer-content">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
            <h3 class="font-bold text-lg text-slate-800">Novo Cliente</h3>
            <button onclick="closeModal('modal-customer')" class="w-8 h-8 rounded-full hover:bg-red-50 text-slate-400 hover:text-red-500 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <div class="p-6 space-y-5">
            <div><label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">NOME COMPLETO</label><input type="text" id="cust-name" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-blue-500 outline-none transition text-sm"></div>
            <div><label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">CPF OU CNPJ</label><input type="text" id="cust-cpf" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-blue-500 outline-none transition text-sm"></div>
            <div><label class="block text-xs font-bold text-slate-600 mb-1.5 ml-1">EMAIL</label><input type="email" id="cust-email" class="w-full p-3 border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:border-blue-500 outline-none transition text-sm"></div>
            <button onclick="createCustomer()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-xl hover:bg-blue-700 mt-2 shadow-lg shadow-blue-100 transition">Salvar Cliente</button>
        </div>
    </div>
</div>

<div id="modal-card-detail" class="fixed inset-0 bg-slate-900/60 hidden z-[110] flex items-center justify-center p-4 backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 flex flex-col max-h-[90vh]" id="modal-card-detail-content">
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 shrink-0">
            <h3 class="font-bold text-lg text-slate-800" id="modal-card-title">Detalhes</h3>
            <button onclick="closeModal('modal-card-detail')" class="w-8 h-8 rounded-full hover:bg-red-50 text-slate-400 hover:text-red-500 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <div class="flex-1 overflow-auto custom-scroll p-0" id="modal-card-table-container"></div>
    </div>
</div>

<script>
    const API_URL = '../api_financeiro.php';
    const LIMIT = 10;
    
    let chargeOffset = 0; let chargeFilter = '';
    let custOffset = 0; let custFilter = '';
    const customerCache = {};

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    const debouncedSearchCharges = debounce(() => { chargeFilter = document.getElementById('charge-search').value; chargeOffset = 0; loadCharges(); }, 600);
    const debouncedSearchCustomers = debounce(() => { custFilter = document.getElementById('customer-search').value; custOffset = 0; loadCustomers(); }, 600);

    document.addEventListener('DOMContentLoaded', async () => {
        const hasConfig = await checkConfig();
        if(hasConfig) { 
            loadKPIs(); 
            loadCharges(); 
            renderChart(); // Inicializa gráfico vazio e depois popula
        }
        setupAutocomplete();
        
        // Seta data de hoje nos filtros para UX (opcional, pode deixar vazio para 'todos')
        // document.getElementById('filter-date-start').valueAsDate = new Date();
    });

    // --- GRÁFICO (APEXCHARTS) ---
    function renderChart() {
        const options = {
            series: [{ name: 'Recebimentos', data: [0,0,0,0,0,0] }], // Placeholder
            chart: { type: 'area', height: 250, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
            colors: ['#4f46e5'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'], axisBorder: {show: false}, axisTicks: {show: false} },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
            tooltip: { theme: 'light' }
        };
        const chart = new ApexCharts(document.querySelector("#chart-revenue"), options);
        chart.render();
        // TODO: Buscar dados reais via API (ex: endpoint /reports/finance) para atualizar o gráfico
    }

    // --- AUTOCOMPLETE CLIENTES ---
    function setupAutocomplete() {
        const input = document.getElementById('charge-customer-search');
        const list = document.getElementById('customer-dropdown-list');
        const hiddenId = document.getElementById('charge-customer-id');
        let timeout = null;

        input.addEventListener('input', () => {
            clearTimeout(timeout);
            hiddenId.value = ''; 
            const val = input.value.trim();
            if(val.length < 2) { list.classList.add('hidden'); return; }

            list.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center"><i class="fas fa-circle-notch fa-spin"></i> Buscando...</div>';
            list.classList.remove('hidden');

            timeout = setTimeout(async () => {
                try {
                    const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/customers&name=${encodeURIComponent(val)}&limit=5`);
                    const data = await res.json();
                    if(!data.data || data.data.length === 0) list.innerHTML = '<div class="p-3 text-xs text-slate-400 text-center">Nenhum cliente encontrado</div>';
                    else {
                        list.innerHTML = data.data.map(c => `
                            <div onclick="selectCustomer('${c.id}', '${c.name}')" class="p-3 hover:bg-indigo-50 cursor-pointer transition flex justify-between items-center group">
                                <div>
                                    <div class="font-bold text-slate-700 text-sm group-hover:text-indigo-700">${c.name}</div>
                                    <div class="text-[10px] text-slate-400 font-mono">${c.cpfCnpj || 'S/ Doc'}</div>
                                </div>
                                <i class="fas fa-check text-indigo-600 opacity-0 group-hover:opacity-100 transition"></i>
                            </div>
                        `).join('');
                    }
                } catch(e) { console.error(e); }
            }, 400);
        });

        document.addEventListener('click', (e) => {
            if(!input.contains(e.target) && !list.contains(e.target)) list.classList.add('hidden');
        });
    }

    function selectCustomer(id, name) {
        document.getElementById('charge-customer-id').value = id;
        document.getElementById('charge-customer-search').value = name;
        document.getElementById('customer-dropdown-list').classList.add('hidden');
    }

    // --- CARREGAMENTO ---
    async function checkConfig() {
        try {
            const res = await fetch(`${API_URL}?action=asaas_get_config`);
            const data = await res.json();
            if (!data.has_token) { switchTab('config'); document.getElementById('api-status').classList.remove('hidden'); return false; }
            loadBalance(); return true;
        } catch (e) { switchTab('config'); return false; }
    }

    async function loadBalance() {
        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/finance/balance`);
            const data = await res.json();
            if(data.balance !== undefined) {
                document.getElementById('balance-value').innerText = `R$ ${data.balance.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
                document.getElementById('balance-card').classList.remove('hidden');
            }
        } catch(e) {}
    }

    async function loadKPIs() {
        const getKpi = async (status) => {
            try {
                const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/payments&status=${status}&limit=1`);
                return (await res.json()).totalCount || 0;
            } catch(e) { return 0; }
        };
        const [rec, pend, over] = await Promise.all([getKpi('RECEIVED'), getKpi('PENDING'), getKpi('OVERDUE')]);
        document.getElementById('kpi-received-count').innerText = rec;
        document.getElementById('kpi-pending-count').innerText = pend;
        document.getElementById('kpi-overdue-count').innerText = over;
    }

    async function loadCharges() {
        const list = document.getElementById('list-charges');
        const start = document.getElementById('filter-date-start').value;
        const end = document.getElementById('filter-date-end').value;

        list.innerHTML = Array(5).fill('').map(() => `<tr><td colspan="6" class="p-5"><div class="h-10 bg-slate-100 rounded-lg animate-pulse w-full"></div></td></tr>`).join('');
        
        let endpoint = `/payments?limit=${LIMIT}&offset=${chargeOffset}`;
        if(chargeFilter) endpoint += `&description=${encodeURIComponent(chargeFilter)}`;
        if(start) endpoint += `&dueDate[ge]=${start}`; // Filtro Data Inicio
        if(end) endpoint += `&dueDate[le]=${end}`;     // Filtro Data Fim

        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=${endpoint}`);
            const data = await res.json();
            if(!data.data || data.data.length === 0) { 
                list.innerHTML = '<tr><td colspan="6" class="p-10 text-center text-slate-400 italic">Nenhuma cobrança encontrada.</td></tr>'; 
                document.getElementById('kpi-total-val').innerText = "R$ 0,00";
                return; 
            }

            let pageTotal = 0;
            const rows = data.data.map(c => {
                pageTotal += c.value;
                return renderChargeRow(c, true);
            }).join('');
            
            list.innerHTML = rows;
            document.getElementById('kpi-total-val').innerText = `R$ ${pageTotal.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`;
            document.getElementById('page-info').innerText = `Página ${Math.floor(chargeOffset/LIMIT) + 1}`;
            document.getElementById('btn-prev').disabled = (chargeOffset === 0);
            document.getElementById('btn-next').disabled = (!data.hasMore);
        } catch(e) { list.innerHTML = `<tr><td colspan="6" class="p-5 text-red-500 text-center">Erro ao carregar dados.</td></tr>`; }
    }

    function renderChargeRow(c, showLink = false) {
        let st = { cls: 'bg-slate-100 text-slate-600', icon: 'fa-circle', label: c.status };
        
        if(['RECEIVED', 'CONFIRMED'].includes(c.status)) st = { cls: 'bg-emerald-100 text-emerald-700', icon: 'fa-check', label: 'Recebido' };
        else if(c.status === 'OVERDUE') st = { cls: 'bg-red-100 text-red-700', icon: 'fa-exclamation-triangle', label: 'Vencido' };
        else if(c.status === 'PENDING') st = { cls: 'bg-amber-100 text-amber-700', icon: 'fa-clock', label: 'Pendente' };

        // Async customer name fetch
        setTimeout(() => fetchCustomerName(c.customer, `row-${c.id}${showLink?'':'-modal'}`), 0);

        const rowId = `row-${c.id}${showLink?'':'-modal'}`;
        const typeIcon = c.billingType === 'PIX' ? '<i class="brands fa-pix text-teal-500"></i>' : '<i class="fas fa-barcode text-slate-400"></i>';

        return `
        <tr class="hover:bg-indigo-50/30 border-b border-slate-50 transition group">
            <td class="p-5 pl-6">
                <div class="font-bold text-slate-700 text-sm" id="${rowId}-name"><div class="h-4 w-24 bg-slate-200 rounded animate-pulse"></div></div>
                <div class="text-xs text-slate-400 mt-0.5">${c.description || 'Cobrança Avulsa'}</div>
            </td>
            <td class="p-5 font-mono text-sm text-slate-700 font-bold">R$ ${c.value.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
            <td class="p-5 text-sm text-slate-500 font-medium">${new Date(c.dueDate).toLocaleDateString('pt-BR')}</td>
            <td class="p-5 text-center text-lg">${typeIcon}</td>
            <td class="p-5 text-center"><span class="${st.cls} px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase inline-flex items-center gap-1.5"><i class="fas ${st.icon}"></i> ${st.label}</span></td>
            <td class="p-5 pr-6 text-right">
                <a href="${c.invoiceUrl}" target="_blank" class="w-8 h-8 inline-flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 hover:shadow-md transition" title="Ver Boleto">
                    <i class="fas fa-external-link-alt"></i>
                </a>
            </td>
        </tr>`;
    }

    async function fetchCustomerName(id, elId) {
        const el = document.getElementById(`${elId}-name`);
        if(!el) return;

        if(customerCache[id]) { el.innerText = customerCache[id]; return; }
        
        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/customers/${id}`);
            const data = await res.json();
            if(data.name) { customerCache[id] = data.name; if(document.getElementById(`${elId}-name`)) document.getElementById(`${elId}-name`).innerText = data.name; }
        } catch(e){}
    }

    async function openCardDetailModal(status, title) {
        const modal = document.getElementById('modal-card-detail');
        document.getElementById('modal-card-title').innerText = title;
        const container = document.getElementById('modal-card-table-container');
        container.innerHTML = '<div class="p-10 text-center text-slate-400"><i class="fas fa-circle-notch fa-spin text-2xl"></i><br>Carregando detalhes...</div>';
        
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); document.getElementById('modal-card-detail-content').classList.add('scale-100'); }, 10);

        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/payments&status=${status}&limit=50`);
            const data = await res.json();
            if(!data.data || data.data.length === 0) { container.innerHTML = '<div class="p-10 text-center text-slate-500">Nenhum registro encontrado.</div>'; return; }
            
            container.innerHTML = `
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase sticky top-0 z-10 shadow-sm"><tr><th class="p-4 pl-6">Cliente</th><th class="p-4">Valor</th><th class="p-4">Vencimento</th><th class="p-4 text-center">Meio</th><th class="p-4 text-center">Status</th><th class="p-4 pr-6 text-right">Ação</th></tr></thead>
                    <tbody class="text-sm divide-y divide-slate-50">${data.data.map(c => renderChargeRow(c, false)).join('')}</tbody>
                </table>`;
        } catch(e) {}
    }

    async function loadCustomers() {
        const list = document.getElementById('list-customers');
        list.innerHTML = Array(5).fill('').map(() => `<tr><td colspan="4" class="p-5"><div class="h-10 bg-slate-100 rounded-lg animate-pulse"></div></td></tr>`).join('');
        
        let endpoint = `/customers?limit=${LIMIT}&offset=${custOffset}`;
        if(custFilter) endpoint += `&name=${encodeURIComponent(custFilter)}`; 

        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=${endpoint}`);
            const data = await res.json();
            if(!data.data || data.data.length === 0) { list.innerHTML = '<tr><td colspan="4" class="p-10 text-center text-slate-400 italic">Nenhum cliente encontrado.</td></tr>'; return; }

            list.innerHTML = data.data.map(c => {
                customerCache[c.id] = c.name;
                return `
                <tr class="hover:bg-slate-50 border-b border-slate-50 transition">
                    <td class="p-5 pl-6 font-bold text-slate-700">${c.name}</td>
                    <td class="p-5 text-slate-500 font-mono text-xs">${c.cpfCnpj || '-'}</td>
                    <td class="p-5 text-slate-500 text-sm">${c.email || '-'}</td>
                    <td class="p-5 pr-6 text-right text-[10px] font-mono text-slate-400">${c.id}</td>
                </tr>`;
            }).join('');
            
            document.getElementById('cust-page-info').innerText = `Página ${Math.floor(custOffset/LIMIT) + 1}`;
            document.getElementById('btn-cust-prev').disabled = (custOffset === 0);
            document.getElementById('btn-cust-next').disabled = (!data.hasMore);
        } catch(e) {}
    }

    // --- AÇÕES CRUD ---
    async function createCharge() {
        const customerId = document.getElementById('charge-customer-id').value;
        if(!customerId) return alert('Selecione um cliente válido da lista.');
        
        const btn = document.querySelector('button[onclick="createCharge()"]');
        const oldHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processando...';
        btn.disabled = true;

        const body = {
            customer: customerId,
            billingType: document.querySelector('input[name="payType"]:checked').value,
            value: parseFloat(document.getElementById('charge-value').value),
            dueDate: document.getElementById('charge-duedate').value,
            description: document.getElementById('charge-desc').value
        };
        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/payments`, { method: 'POST', body: JSON.stringify(body) });
            const json = await res.json();
            if(json.id) { 
                alert('Cobrança emitida com sucesso!'); 
                closeModal('modal-charge'); 
                loadCharges(); 
                loadKPIs();
            } else {
                alert('Erro ao emitir: ' + (json.error || 'Verifique os dados.'));
            }
        } catch(e) { alert('Erro de conexão.'); } finally {
            btn.innerHTML = oldHtml;
            btn.disabled = false;
        }
    }

    async function createCustomer() {
        const body = { name: document.getElementById('cust-name').value, cpfCnpj: document.getElementById('cust-cpf').value, email: document.getElementById('cust-email').value };
        if(!body.name || !body.cpfCnpj) return alert('Preencha nome e documento.');

        try {
            const res = await fetch(`${API_URL}?action=asaas_proxy&asaas_endpoint=/customers`, { method: 'POST', body: JSON.stringify(body) });
            if((await res.json()).id) { alert('Cliente salvo!'); closeModal('modal-customer'); loadCustomers(); } else alert('Erro ao salvar cliente.');
        } catch(e) {}
    }

    async function saveConfig() {
        const key = document.getElementById('config-apikey').value;
        if(!key) return alert('Informe a chave.');
        try {
            await fetch(`${API_URL}?action=asaas_save_config`, { method: 'POST', body: JSON.stringify({ apiKey: key }) });
            alert('Configuração salva com sucesso!'); location.reload();
        } catch(e) {}
    }

    // --- UX HELPERS ---
    function toggleKeyVisibility() {
        const input = document.getElementById('config-apikey');
        const icon = document.querySelector('button[onclick="toggleKeyVisibility()"] i');
        if(input.type === 'password') { input.type = 'text'; icon.className = 'far fa-eye-slash'; }
        else { input.type = 'password'; icon.className = 'far fa-eye'; }
    }

    function nextPage() { chargeOffset += LIMIT; loadCharges(); }
    function prevPage() { if(chargeOffset >= LIMIT) { chargeOffset -= LIMIT; loadCharges(); } }
    function custNextPage() { custOffset += LIMIT; loadCustomers(); }
    function custPrevPage() { if(custOffset >= LIMIT) { custOffset -= LIMIT; loadCustomers(); } }

    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.tab-btn').forEach(el => { el.classList.remove('bg-indigo-50', 'text-indigo-700', 'shadow-sm'); el.classList.add('text-slate-500', 'hover:bg-slate-50'); });
        document.getElementById(`view-${tab}`).classList.remove('hidden');
        document.getElementById(`tab-${tab}`).classList.add('bg-indigo-50', 'text-indigo-700', 'shadow-sm');
        document.getElementById(`tab-${tab}`).classList.remove('text-slate-500', 'hover:bg-slate-50');
        if(tab === 'customers') loadCustomers();
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        modal.classList.remove('hidden');
        setTimeout(() => { modal.classList.remove('opacity-0'); document.getElementById(id + '-content').classList.remove('scale-95'); document.getElementById(id + '-content').classList.add('scale-100'); }, 10);
    }
    function openModalCharge() { openModal('modal-charge'); }
    function openModalCustomer() { openModal('modal-customer'); }
    function closeModal(id) {
        document.getElementById(id+'-content').classList.remove('scale-100'); document.getElementById(id+'-content').classList.add('scale-95');
        document.getElementById(id).classList.add('opacity-0'); setTimeout(() => document.getElementById(id).classList.add('hidden'), 300);
    }
</script>