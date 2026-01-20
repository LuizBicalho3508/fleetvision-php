<?php
if (!isset($_SESSION['user_id'])) exit;
?>

<div class="flex flex-col h-screen bg-slate-50 overflow-hidden font-inter">
    
    <div class="bg-white border-b border-gray-200 px-8 py-5 flex justify-between items-center flex-shrink-0 shadow-sm z-20">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
                <div class="p-2 bg-blue-50 text-blue-600 rounded-lg"><i class="fas fa-stopwatch"></i></div>
                Controle de Jornada (Lei 13.103)
            </h2>
            <p class="text-sm text-slate-500 mt-1">Monitoramento de fadiga e tempos de direção em tempo real.</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="hidden lg:flex items-center gap-4 bg-slate-50 px-4 py-2 rounded-lg border border-slate-200">
                <div class="text-right border-r border-slate-200 pr-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Limite Contínuo</p>
                    <p class="text-sm font-bold text-slate-700">5h 30m</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jornada Diária</p>
                    <p class="text-sm font-bold text-slate-700">10h 00m</p>
                </div>
            </div>
            <button onclick="loadJornada()" class="w-10 h-10 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition shadow-lg shadow-blue-100 flex items-center justify-center">
                <i class="fas fa-sync-alt" id="refresh-icon"></i>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 px-8 py-6 flex-shrink-0">
        <div onclick="filterStatus('all')" class="cursor-pointer bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md hover:border-blue-300 transition group relative overflow-hidden">
            <div class="flex justify-between items-start z-10 relative">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Total Monitorado</p>
                    <h3 class="text-3xl font-bold text-slate-700" id="count-total">0</h3>
                </div>
                <div class="p-2 bg-slate-100 text-slate-500 rounded-lg group-hover:bg-blue-50 group-hover:text-blue-600 transition"><i class="fas fa-users"></i></div>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-slate-200 group-hover:bg-blue-500 transition-colors"></div>
        </div>

        <div onclick="filterStatus('ok')" class="cursor-pointer bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md hover:border-emerald-300 transition group relative overflow-hidden">
            <div class="flex justify-between items-start z-10 relative">
                <div>
                    <p class="text-xs font-bold text-emerald-600/70 uppercase tracking-wider mb-1">Situação Regular</p>
                    <h3 class="text-3xl font-bold text-emerald-600" id="count-ok">0</h3>
                </div>
                <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-emerald-200 group-hover:bg-emerald-500 transition-colors"></div>
        </div>

        <div onclick="filterStatus('warning')" class="cursor-pointer bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md hover:border-amber-300 transition group relative overflow-hidden">
            <div class="flex justify-between items-start z-10 relative">
                <div>
                    <p class="text-xs font-bold text-amber-600/70 uppercase tracking-wider mb-1">Atenção (>4h)</p>
                    <h3 class="text-3xl font-bold text-amber-600" id="count-warn">0</h3>
                </div>
                <div class="p-2 bg-amber-50 text-amber-600 rounded-lg"><i class="fas fa-clock"></i></div>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-amber-200 group-hover:bg-amber-500 transition-colors"></div>
        </div>

        <div onclick="filterStatus('critical')" class="cursor-pointer bg-white p-5 rounded-2xl shadow-sm border border-slate-200 hover:shadow-md hover:border-red-300 transition group relative overflow-hidden">
            <div class="flex justify-between items-start z-10 relative">
                <div>
                    <p class="text-xs font-bold text-red-600/70 uppercase tracking-wider mb-1">Violações</p>
                    <h3 class="text-3xl font-bold text-red-600" id="count-crit">0</h3>
                </div>
                <div class="p-2 bg-red-50 text-red-600 rounded-lg animate-pulse"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
            <div class="absolute bottom-0 left-0 w-full h-1 bg-red-200 group-hover:bg-red-500 transition-colors"></div>
        </div>
    </div>

    <div class="px-8 pb-4 flex justify-between items-center gap-4">
        <div class="relative w-full max-w-md">
            <i class="fas fa-search absolute left-4 top-3.5 text-slate-400"></i>
            <input type="text" id="search-driver" onkeyup="filterTable()" placeholder="Buscar motorista, CNH ou placa..." class="w-full pl-11 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 outline-none text-sm transition bg-white shadow-sm">
        </div>
        <div id="active-filter-badge" class="hidden px-3 py-1 bg-slate-200 text-slate-600 rounded-full text-xs font-bold items-center gap-2 cursor-pointer hover:bg-slate-300" onclick="filterStatus('all')">
            Filtro: <span id="filter-name">Todos</span> <i class="fas fa-times"></i>
        </div>
    </div>

    <div class="flex-1 overflow-auto px-8 pb-8 custom-scroll">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 text-slate-500 font-bold text-xs uppercase tracking-wider border-b border-slate-200 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-4">Motorista</th>
                        <th class="px-6 py-4">Status Atual</th>
                        <th class="px-6 py-4 w-1/4">Tempo Contínuo (Max 5h30)</th>
                        <th class="px-6 py-4 w-1/4">Jornada Dia (Max 10h)</th>
                        <th class="px-6 py-4 text-center">Saúde</th>
                        <th class="px-6 py-4 text-right">Ação</th>
                    </tr>
                </thead>
                <tbody id="jornada-list" class="divide-y divide-slate-100 text-sm text-slate-600">
                    <tr><td colspan="6" class="p-12 text-center text-slate-400"><i class="fas fa-circle-notch fa-spin text-2xl mb-2"></i><br>Carregando dados...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal-details" class="fixed inset-0 bg-slate-900/50 hidden z-50 flex items-center justify-center backdrop-blur-sm p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl p-6 transform transition-all scale-95 opacity-0" id="modal-content">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2"><i class="fas fa-id-card text-blue-500"></i> Detalhes da Jornada</h3>
            <button onclick="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-red-100 hover:text-red-500 transition flex items-center justify-center"><i class="fas fa-times"></i></button>
        </div>
        <div id="modal-body" class="space-y-4">
            </div>
    </div>
</div>

<script>
let allData = [];
let currentFilter = 'all';

function secToTime(seconds) {
    if(seconds < 0) return "00h 00m";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return `${h}h ${m < 10 ? '0'+m : m}m`;
}

// Gera barra de progresso com "Tempo Restante"
function getProgressBar(seconds, limit, colorClass, label) {
    let pct = (seconds / limit) * 100;
    if (pct > 100) pct = 100;
    
    const remaining = limit - seconds;
    const remainingText = remaining > 0 
        ? `<span class="text-slate-400 font-normal ml-1">Resta ${secToTime(remaining)}</span>` 
        : `<span class="text-red-500 font-bold ml-1">EXCEDIDO (+${secToTime(Math.abs(remaining))})</span>`;

    return `
        <div class="flex justify-between text-[11px] font-bold text-slate-600 mb-1.5">
            <span>${secToTime(seconds)} ${remainingText}</span>
            <span>${Math.round(pct)}%</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-100 shadow-inner">
            <div class="${colorClass} h-full rounded-full transition-all duration-700 ease-out relative group" style="width: ${pct}%">
                <div class="absolute inset-0 bg-white/20 group-hover:bg-white/0 transition"></div>
            </div>
        </div>
    `;
}

async function loadJornada() {
    const btnIcon = document.getElementById('refresh-icon');
    btnIcon.classList.add('fa-spin');
    
    try {
        const res = await fetch('/api_jornada.php');
        allData = await res.json();
        renderTable();
        updateKPIs();
    } catch(e) { 
        console.error("Erro:", e); 
        document.getElementById('jornada-list').innerHTML = `<tr><td colspan="6" class="p-8 text-center text-red-500"><i class="fas fa-exclamation-circle mb-2 text-2xl"></i><br>Erro ao carregar dados.</td></tr>`;
    } finally {
        setTimeout(() => btnIcon.classList.remove('fa-spin'), 500);
    }
}

function updateKPIs() {
    let ok = 0, warn = 0, crit = 0;
    allData.forEach(d => {
        if (d.health === 'ok') ok++;
        else if (d.health === 'warning') warn++;
        else crit++;
    });
    document.getElementById('count-total').innerText = allData.length;
    document.getElementById('count-ok').innerText = ok;
    document.getElementById('count-warn').innerText = warn;
    document.getElementById('count-crit').innerText = crit;
}

function filterStatus(status) {
    currentFilter = status;
    const badge = document.getElementById('active-filter-badge');
    const label = document.getElementById('filter-name');
    
    if(status === 'all') {
        badge.classList.add('hidden');
    } else {
        badge.classList.remove('hidden');
        label.innerText = status === 'ok' ? 'Regular' : (status === 'warning' ? 'Atenção' : 'Violação');
        badge.className = `px-3 py-1 rounded-full text-xs font-bold items-center gap-2 cursor-pointer transition flex ${
            status === 'ok' ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 
            (status === 'warning' ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-red-100 text-red-700 hover:bg-red-200')
        }`;
    }
    renderTable();
}

function filterTable() {
    renderTable();
}

function renderTable() {
    const tbody = document.getElementById('jornada-list');
    const search = document.getElementById('search-driver').value.toLowerCase();
    
    // Filtros
    const filtered = allData.filter(d => {
        const matchesSearch = d.name.toLowerCase().includes(search) || 
                              (d.current_vehicle && d.current_vehicle.toLowerCase().includes(search)) ||
                              (d.cnh && d.cnh.toLowerCase().includes(search));
        
        const matchesStatus = currentFilter === 'all' || d.health === currentFilter;
        return matchesSearch && matchesStatus;
    });

    tbody.innerHTML = '';

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="p-12 text-center text-slate-400 bg-slate-50/50 italic border-b border-slate-100">Nenhum motorista encontrado com os filtros atuais.</td></tr>`;
        return;
    }

    const LIMIT_CONT = 5.5 * 3600;
    const LIMIT_DAY = 10 * 3600;

    filtered.forEach(d => {
        // Status Badge
        let statusHtml = d.status === 'dirigindo' 
            ? `<div class="flex flex-col"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100 w-fit"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span> DIRIGINDO</span><span class="text-[10px] text-slate-400 mt-1 font-mono">Desde: ${new Date(d.last_start_time * 1000).toLocaleTimeString()}</span></div>`
            : `<div class="flex flex-col"><span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-500 text-xs font-bold border border-slate-200 w-fit"><i class="fas fa-parking"></i> PARADO</span><span class="text-[10px] text-slate-400 mt-1 font-mono">Pausa: ${secToTime(Date.now()/1000 - d.last_end_time)}</span></div>`;

        // Saúde Badge
        let healthBadge = '';
        if (d.health === 'critical') healthBadge = `<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-red-100 text-red-600 shadow-sm" title="${d.violations.join(', ')}"><i class="fas fa-times"></i></span>`;
        else if (d.health === 'warning') healthBadge = `<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-100 text-amber-600 shadow-sm"><i class="fas fa-exclamation"></i></span>`;
        else healthBadge = `<span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 shadow-sm"><i class="fas fa-check"></i></span>`;

        // Cores
        const contColor = d.continuous_driving > LIMIT_CONT ? 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]' : (d.continuous_driving > (LIMIT_CONT * 0.8) ? 'bg-amber-500' : 'bg-blue-500');
        const dayColor  = d.total_driving > LIMIT_DAY ? 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]' : (d.total_driving > (LIMIT_DAY * 0.8) ? 'bg-amber-500' : 'bg-emerald-500');

        const html = `
            <tr class="hover:bg-slate-50 transition group border-b border-slate-50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 text-slate-500 flex items-center justify-center font-bold text-sm shadow-sm border border-white">
                            ${d.name.substring(0,2).toUpperCase()}
                        </div>
                        <div>
                            <div class="font-bold text-slate-700 text-sm">${d.name}</div>
                            <div class="text-xs text-slate-400 font-medium flex items-center gap-1">
                                <i class="fas fa-truck text-[10px]"></i> ${d.current_vehicle || 'N/A'}
                                <span class="mx-1">•</span> CNH: ${d.cnh}
                            </div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">${statusHtml}</td>
                <td class="px-6 py-4">${getProgressBar(d.continuous_driving, LIMIT_CONT, contColor)}</td>
                <td class="px-6 py-4">${getProgressBar(d.total_driving, LIMIT_DAY, dayColor)}</td>
                <td class="px-6 py-4 text-center">${healthBadge}</td>
                <td class="px-6 py-4 text-right">
                    <button onclick="openDetails('${d.id}')" class="text-slate-400 hover:text-blue-600 transition p-2 rounded-lg hover:bg-blue-50">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `;
        tbody.innerHTML += html;
    });
}

function openDetails(driverId) {
    const driver = allData.find(d => d.id == driverId);
    if(!driver) return;

    const modal = document.getElementById('modal-details');
    const content = document.getElementById('modal-content');
    const body = document.getElementById('modal-body');

    // Monta detalhes
    let violationsHtml = driver.violations.length > 0 
        ? `<div class="bg-red-50 p-3 rounded-lg border border-red-200 text-xs text-red-700 font-medium space-y-1">
            ${driver.violations.map(v => `<div class="flex items-center gap-2"><i class="fas fa-circle text-[6px]"></i> ${v}</div>`).join('')}
           </div>`
        : `<div class="bg-emerald-50 p-3 rounded-lg border border-emerald-200 text-xs text-emerald-700 font-bold flex items-center gap-2"><i class="fas fa-check-circle"></i> Nenhuma infração detectada hoje.</div>`;

    body.innerHTML = `
        <div class="grid grid-cols-2 gap-4 mb-4">
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                <p class="text-[10px] uppercase text-slate-400 font-bold mb-1">Total Dirigido</p>
                <p class="text-xl font-bold text-slate-700">${secToTime(driver.total_driving)}</p>
            </div>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                <p class="text-[10px] uppercase text-slate-400 font-bold mb-1">Contínuo Atual</p>
                <p class="text-xl font-bold text-slate-700">${secToTime(driver.continuous_driving)}</p>
            </div>
        </div>
        ${violationsHtml}
        <div class="text-xs text-slate-400 text-center mt-4 border-t border-slate-100 pt-4">
            Jornada ID: #${driver.id} • Atualizado: ${new Date().toLocaleTimeString()}
        </div>
    `;

    modal.classList.remove('hidden');
    setTimeout(() => {
        content.classList.remove('opacity-0', 'scale-95');
        content.classList.add('opacity-100', 'scale-100');
    }, 10);
}

function closeModal() {
    const modal = document.getElementById('modal-details');
    const content = document.getElementById('modal-content');
    
    content.classList.remove('opacity-100', 'scale-100');
    content.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => modal.classList.add('hidden'), 300);
}

// Inicia
loadJornada();
setInterval(loadJornada, 30000); // Auto-refresh 30s
</script>