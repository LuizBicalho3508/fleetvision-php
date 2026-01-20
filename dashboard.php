<?php
// Debug silencioso
ini_set('display_errors', 0); error_reporting(E_ALL);

if (!isset($_SESSION['user_id'])) exit("Sessão expirada.");

$userNameFull = $_SESSION['user_name'] ?? 'Gestor';
$firstName = explode(' ', $userNameFull)[0];

// --- BUSCA EVENTOS NO BANCO (SQL) ---
// Usamos SQL para o histórico de eventos, pois a API de eventos as vezes é lenta para buscar histórico
try {
    $isAdmin = ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'superadmin');
    $whereUser = $isAdmin ? "" : " AND v.user_id = " . intval($_SESSION['user_id']);

    // 1. Busca IDs dos veículos do usuário
    $sqlIds = "SELECT v.traccar_device_id FROM saas_vehicles v WHERE v.tenant_id = ? $whereUser";
    $stmtIds = $pdo->prepare($sqlIds);
    $stmtIds->execute([$tenant['id']]);
    $veiculos = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
    
    $events = [];
    $totalAlerts24h = 0;

    if (!empty($veiculos)) {
        // Filtra apenas IDs numéricos válidos
        $validIds = array_filter($veiculos, 'is_numeric');
        
        if(!empty($validIds)){
            $idList = implode(',', $validIds);
            
            // Busca Últimos 7 Eventos
            $sqlEvt = "SELECT e.type, e.eventtime as servertime, v.name as vehicle_name 
                       FROM tc_events e
                       JOIN saas_vehicles v ON e.deviceid = v.traccar_device_id
                       WHERE e.deviceid IN ($idList) 
                       ORDER BY e.eventtime DESC LIMIT 7";
            $events = $pdo->query($sqlEvt)->fetchAll(PDO::FETCH_ASSOC);

            // Conta alertas das últimas 24h
            $sqlCount = "SELECT COUNT(*) FROM tc_events e 
                         WHERE e.deviceid IN ($idList) 
                         AND e.eventtime > NOW() - INTERVAL '24 hours' 
                         AND e.type NOT IN ('deviceOnline', 'deviceOffline')"; // Ignora on/off na contagem de alertas críticos
            $totalAlerts24h = $pdo->query($sqlCount)->fetchColumn();
        }
    }

} catch (Exception $e) {
    $events = [];
    $totalAlerts24h = 0;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="p-6 space-y-6 bg-slate-50 min-h-full font-sans text-slate-800">

    <div class="flex flex-col md:flex-row justify-between items-end mb-2">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Visão Geral</h1>
            <p class="text-sm text-slate-500">Monitoramento em tempo real da operação.</p>
        </div>
        <div class="text-right">
            <span id="last-update" class="text-xs text-slate-400 font-mono">Atualizado: --:--</span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500 rounded-bl-full opacity-10 group-hover:scale-110 transition"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center"><i class="fas fa-truck"></i></div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Frota Total</span>
                </div>
                <h3 class="text-4xl font-bold text-slate-800" id="kpi-total">-</h3>
                <p class="text-xs text-slate-400 mt-1">Veículos cadastrados</p>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute right-0 top-0 w-24 h-24 bg-green-500 rounded-bl-full opacity-10 group-hover:scale-110 transition"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-green-100 text-green-600 flex items-center justify-center"><i class="fas fa-wifi"></i></div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Online Agora</span>
                </div>
                <div class="flex items-end gap-2">
                    <h3 class="text-4xl font-bold text-green-600" id="kpi-online">-</h3>
                    <span class="text-sm text-slate-400 mb-1">/ <span id="kpi-total-sub">0</span></span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 mt-3 rounded-full overflow-hidden">
                    <div id="bar-online" class="h-full bg-green-500 transition-all duration-1000" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 relative overflow-hidden group hover:shadow-md transition">
            <div class="absolute right-0 top-0 w-24 h-24 bg-orange-500 rounded-bl-full opacity-10 group-hover:scale-110 transition"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center"><i class="fas fa-bell"></i></div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Alertas (24h)</span>
                </div>
                <h3 class="text-4xl font-bold text-orange-600"><?php echo $totalAlerts24h; ?></h3>
                <p class="text-xs text-slate-400 mt-1">Infrações ou eventos</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        
        <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col justify-between hover:border-blue-300 transition">
            <span class="text-[10px] uppercase font-bold text-slate-400">Em Movimento</span>
            <div class="flex justify-between items-center mt-2">
                <h4 class="text-2xl font-bold text-slate-700" id="kpi-moving">-</h4>
                <i class="fas fa-key text-slate-200 text-xl"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col justify-between hover:border-blue-300 transition">
            <span class="text-[10px] uppercase font-bold text-slate-400">Km Percorrido (Total)</span>
            <div class="flex justify-between items-center mt-2">
                <h4 class="text-xl font-bold text-slate-700 truncate" id="kpi-odo">-</h4>
                <i class="fas fa-road text-slate-200 text-xl"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col justify-between hover:border-blue-300 transition">
            <span class="text-[10px] uppercase font-bold text-slate-400">Com Delay (>5min)</span>
            <div class="flex justify-between items-center mt-2">
                <h4 class="text-2xl font-bold text-slate-700" id="kpi-delay">-</h4>
                <i class="fas fa-clock text-slate-200 text-xl"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-xl border border-slate-200 flex flex-col justify-between hover:border-blue-300 transition">
            <span class="text-[10px] uppercase font-bold text-slate-400">Bateria Crítica</span>
            <div class="flex justify-between items-center mt-2">
                <h4 class="text-2xl font-bold text-red-500" id="kpi-battery">-</h4>
                <i class="fas fa-car-battery text-slate-200 text-xl"></i>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 mb-4">Disponibilidade da Frota</h3>
            <div class="relative h-56">
                <canvas id="chartStatus"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <span class="text-3xl font-bold text-slate-800" id="chart-center-val">0%</span>
                    <span class="text-[10px] text-slate-400 uppercase">Online</span>
                </div>
            </div>
            
            <div class="mt-6 space-y-3">
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-500">Qualidade de Sinal (GSM)</span>
                    </div>
                    <div class="flex h-2 rounded-full overflow-hidden bg-slate-100">
                        <div id="sig-good" class="bg-green-500 h-full" style="width: 0%" title="Bom"></div>
                        <div id="sig-mid" class="bg-yellow-400 h-full" style="width: 0%" title="Médio"></div>
                        <div id="sig-bad" class="bg-red-400 h-full" style="width: 0%" title="Ruim"></div>
                    </div>
                    <div class="flex justify-between text-[10px] text-slate-400 mt-1">
                        <span>Forte</span> <span>Médio</span> <span>Fraco</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 flex flex-col">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-sm font-bold text-slate-700">Feed de Atividades</h3>
                <a href="/<?php echo $slug; ?>/relatorios" class="text-xs text-blue-600 font-bold hover:underline">Ver Histórico</a>
            </div>
            
            <div class="flex-1 overflow-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold sticky top-0">
                        <tr>
                            <th class="px-5 py-3">Evento</th>
                            <th class="px-5 py-3">Veículo</th>
                            <th class="px-5 py-3 text-right">Horário</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($events)): ?>
                            <tr><td colspan="3" class="p-8 text-center text-slate-400">Nenhuma atividade recente.</td></tr>
                        <?php else: ?>
                            <?php foreach($events as $e): 
                                $type = $e['type'];
                                $icon = 'circle'; $color = 'slate'; $label = $type;
                                
                                // Mapeamento de Ícones
                                if($type=='deviceOnline'){$icon='wifi';$color='green';$label='Conectou';}
                                elseif($type=='deviceOffline'){$icon='power-off';$color='red';$label='Desconectou';}
                                elseif($type=='deviceOverspeed'){$icon='tachometer-alt';$color='orange';$label='Velocidade';}
                                elseif($type=='geofenceEnter'){$icon='map-marker-alt';$color='blue';$label='Entrou Cerca';}
                                elseif($type=='geofenceExit'){$icon='sign-out-alt';$color='purple';$label='Saiu Cerca';}
                                elseif($type=='ignitionOn'){$icon='key';$color='green';$label='Ignição Ligada';}
                                elseif($type=='ignitionOff'){$icon='key';$color='slate';$label='Ignição Desligada';}
                                
                                $time = date('H:i', strtotime($e['servertime']));
                                $date = date('d/m', strtotime($e['servertime']));
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-<?php echo $color; ?>-100 text-<?php echo $color; ?>-600 flex items-center justify-center">
                                            <i class="fas fa-<?php echo $icon; ?> text-xs"></i>
                                        </div>
                                        <span class="font-bold text-slate-700 text-xs"><?php echo $label; ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-xs text-slate-600"><?php echo $e['vehicle_name']; ?></td>
                                <td class="px-5 py-3 text-right">
                                    <div class="text-xs font-bold text-slate-700"><?php echo $time; ?></div>
                                    <div class="text-[10px] text-slate-400"><?php echo $date; ?></div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// --- MOTOR DE DADOS CLIENT-SIDE ---
async function updateDashboard() {
    try {
        const res = await fetch('/api_dados.php?endpoint=/devices');
        if(!res.ok) throw new Error('API');
        const devices = await res.json();

        // Variáveis de Contagem
        let total = devices.length;
        let online = 0, offline = 0;
        let moving = 0, delay = 0, lowBat = 0;
        let odoSum = 0;
        let sigGood = 0, sigMid = 0, sigBad = 0, sigTotal = 0;

        const now = new Date();

        devices.forEach(d => {
            // Status
            if(d.status === 'online') online++; else offline++;

            const attr = d.attributes || {};
            
            // Ignição / Movimento
            if(attr.ignition) moving++;

            // Odometro (Soma total em KM)
            if(attr.totalDistance) odoSum += (attr.totalDistance / 1000);

            // Bateria (<20% critico)
            if(attr.batteryLevel !== undefined && attr.batteryLevel < 20) lowBat++;

            // Delay (> 5min e status online ou desconhecido)
            const lastUp = new Date(d.lastUpdate);
            const diffMin = (now - lastUp) / 1000 / 60;
            if(diffMin > 5) delay++;

            // Sinal (RSSI) - Normalização aproximada
            if(attr.rssi) {
                sigTotal++;
                if(attr.rssi >= 20) sigGood++; // Bom
                else if(attr.rssi >= 10) sigMid++; // Medio
                else sigBad++; // Ruim
            }
        });

        // --- ATUALIZA DOM ---
        
        // Cards Topo
        document.getElementById('kpi-total').innerText = total;
        document.getElementById('kpi-total-sub').innerText = total;
        document.getElementById('kpi-online').innerText = online;
        
        // Barra de Progresso Online
        const pctOnline = total > 0 ? Math.round((online / total) * 100) : 0;
        document.getElementById('bar-online').style.width = pctOnline + '%';
        document.getElementById('chart-center-val').innerText = pctOnline + '%';

        // Faixa Técnica
        document.getElementById('kpi-moving').innerText = moving;
        document.getElementById('kpi-odo').innerText = Math.floor(odoSum).toLocaleString() + ' km';
        document.getElementById('kpi-delay').innerText = delay;
        document.getElementById('kpi-battery').innerText = lowBat;

        // Barra de Sinal
        if(sigTotal > 0) {
            document.getElementById('sig-good').style.width = ((sigGood/sigTotal)*100) + '%';
            document.getElementById('sig-mid').style.width = ((sigMid/sigTotal)*100) + '%';
            document.getElementById('sig-bad').style.width = ((sigBad/sigTotal)*100) + '%';
        }

        // Hora
        document.getElementById('last-update').innerText = 'Atualizado: ' + now.toLocaleTimeString();

        // Chart Update
        updateChart(online, offline);

    } catch(e) { console.error(e); }
}

// Chart Instance
let chart = null;
function updateChart(on, off) {
    const ctx = document.getElementById('chartStatus').getContext('2d');
    if(chart) {
        chart.data.datasets[0].data = [on, off];
        chart.update();
    } else {
        chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Online', 'Offline'],
                datasets: [{
                    data: [on, off],
                    backgroundColor: ['#22c55e', '#ef4444'], // Tailwind green-500, red-500
                    borderWidth: 0,
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle' } } }
            }
        });
    }
}

// Init
updateDashboard();
setInterval(updateDashboard, 10000);
</script>
