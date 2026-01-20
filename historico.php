<?php
// historico.php
if (!isset($_SESSION['user_id'])) exit;

$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$user_role  = $_SESSION['user_role'] ?? 'user';
$user_email = $_SESSION['user_email'] ?? ''; 

// --- 1. LÓGICA DE FILTRO DE VEÍCULOS ---
$restrictionSQL = "";
$params = [$tenant_id];

if ($user_role != 'admin' && $user_role != 'superadmin') {
    $stmtUserCheck = $pdo->prepare("SELECT customer_id FROM saas_users WHERE id = ?");
    $stmtUserCheck->execute([$user_id]);
    $userDirectCustomer = $stmtUserCheck->fetchColumn();

    if (!$userDirectCustomer && !empty($user_email)) {
        $stmtEmail = $pdo->prepare("SELECT id FROM saas_customers WHERE email = ? AND tenant_id = ?");
        $stmtEmail->execute([$user_email, $tenant_id]);
        $userDirectCustomer = $stmtEmail->fetchColumn();
    }

    if ($userDirectCustomer) {
        $restrictionSQL = " AND (v.client_id = ? OR v.user_id = ?)";
        $params[] = $userDirectCustomer;
        $params[] = $user_id;
    } else {
        $restrictionSQL = " AND v.user_id = ?";
        $params[] = $user_id;
    }
}

// --- 2. BUSCA VEÍCULOS COM ÍCONES ---
$sql = "SELECT v.id, v.name, v.plate, v.traccar_device_id, v.category, i.url as icon_url
        FROM saas_vehicles v 
        LEFT JOIN saas_custom_icons i ON CAST(v.category AS VARCHAR) = CAST(i.id AS VARCHAR)
        WHERE v.tenant_id = ? $restrictionSQL 
        ORDER BY v.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cria mapa de ícones (ID -> URL)
$vehicleIcons = [];
foreach($vehicles as $v) {
    $vehicleIcons[$v['traccar_device_id']] = $v['icon_url'] ?? null;
}
$jsVehicleIcons = json_encode($vehicleIcons);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function() {
    var _oldIconInit = L.Marker.prototype._initIcon;
    var _oldSetPos = L.Marker.prototype._setPos;

    L.Marker.include({
        _initIcon: function() {
            _oldIconInit.apply(this, arguments);
            if (this.options.rotationAngle) {
                this._icon.style.transform += ' rotate(' + this.options.rotationAngle + 'deg)';
            }
        },
        _setPos: function(pos) {
            _oldSetPos.apply(this, arguments);
            if (this.options.rotationAngle) {
                this._icon.style.transform += ' rotate(' + this.options.rotationAngle + 'deg)';
            }
        },
        setRotationAngle: function(angle) {
            this.options.rotationAngle = angle;
            this.update();
            return this;
        }
    });
})();
</script>

<div class="h-full flex flex-col bg-slate-50 font-inter relative">
    
    <div class="bg-white border-b border-slate-200 px-6 py-4 shadow-sm z-20 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg"><i class="fas fa-history text-lg"></i></div>
            <div>
                <h1 class="text-xl font-bold text-slate-800">Histórico de Rotas</h1>
                <p class="text-xs text-slate-500">Replay e análise de percurso.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 bg-slate-50 p-1.5 rounded-xl border border-slate-200">
            <div class="relative">
                <i class="fas fa-truck absolute left-3 top-3 text-slate-400 text-xs"></i>
                <select id="hist-device" class="pl-8 pr-4 py-2 rounded-lg border border-slate-200 text-sm bg-white focus:border-indigo-500 outline-none w-48">
                    <option value="">Selecione o Veículo</option>
                    <?php foreach($vehicles as $v): ?>
                        <option value="<?php echo $v['traccar_device_id']; ?>"><?php echo $v['name']; ?> (<?php echo $v['plate'] ?? ''; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <input type="datetime-local" id="hist-from" class="px-3 py-2 rounded-lg border border-slate-200 text-sm bg-white outline-none" value="<?php echo date('Y-m-d 00:00'); ?>">
            <span class="text-slate-400 text-xs">até</span>
            <input type="datetime-local" id="hist-to" class="px-3 py-2 rounded-lg border border-slate-200 text-sm bg-white outline-none" value="<?php echo date('Y-m-d 23:59'); ?>">

            <button onclick="loadHistory()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition flex items-center gap-2">
                <i class="fas fa-search"></i> <span class="hidden sm:inline">Buscar</span>
            </button>
        </div>
    </div>

    <div class="flex-1 relative z-0">
        <div id="map-history" class="w-full h-full bg-slate-100"></div>
        
        <div id="player-controls" class="absolute bottom-8 left-1/2 transform -translate-x-1/2 bg-white/90 backdrop-blur-md p-4 rounded-2xl shadow-2xl border border-slate-200 w-[90%] max-w-2xl hidden flex-col gap-3 z-[1000]">
            
            <div class="w-full bg-slate-200 rounded-full h-2 cursor-pointer group relative" id="progress-container" onclick="seek(event)">
                <div id="progress-bar" class="bg-indigo-600 h-2 rounded-full w-0 relative transition-all duration-100">
                    <div class="absolute -right-1.5 -top-1.5 w-5 h-5 bg-white border-2 border-indigo-600 rounded-full shadow cursor-pointer transform scale-0 group-hover:scale-100 transition"></div>
                </div>
            </div>

            <div class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <button onclick="togglePlay()" id="btn-play" class="w-10 h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center hover:bg-indigo-700 shadow transition">
                        <i class="fas fa-play ml-1"></i>
                    </button>
                    <div>
                        <div class="text-xs text-slate-400 font-bold uppercase tracking-wide">Horário</div>
                        <div class="font-mono text-slate-800 font-bold" id="current-time">--:--:--</div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 mx-2"></div>
                    <div>
                        <div class="text-xs text-slate-400 font-bold uppercase tracking-wide">Velocidade</div>
                        <div class="font-mono text-slate-800 font-bold" id="current-speed">0 km/h</div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-500 font-bold">Velocidade:</label>
                    <select id="playback-speed" class="bg-slate-100 border border-slate-200 text-xs rounded px-2 py-1 outline-none font-bold text-slate-700">
                        <option value="2000">Lento</option>
                        <option value="1000">Normal</option>
                        <option value="500" selected>Rápido</option>
                        <option value="100">Turbo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- CONFIGURAÇÃO ---
    const API_URL = '../api_dados.php'; // Caminho relativo para subir um nível
    const vehicleIconsMap = <?php echo $jsVehicleIcons; ?>;
    
    let map, polyline, marker;
    let routeData = [];
    
    // Animação
    let isPlaying = false;
    let currentIndex = 0;
    let animationFrameId;
    let lastTimestamp = 0;
    let progressBetweenPoints = 0; // 0.0 a 1.0 entre pontos
    
    // Inicializa Mapa
    document.addEventListener('DOMContentLoaded', () => {
        map = L.map('map-history', { zoomControl: false }).setView([-14.235, -51.925], 4);
        L.control.zoom({ position: 'topright' }).addTo(map);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; CARTO'
        }).addTo(map);
    });

    async function loadHistory() {
        const deviceId = document.getElementById('hist-device').value;
        const from = new Date(document.getElementById('hist-from').value).toISOString();
        const to = new Date(document.getElementById('hist-to').value).toISOString();

        if (!deviceId) return alert("Selecione um veículo.");

        // Reset
        stopPlay();
        currentIndex = 0;
        progressBetweenPoints = 0;
        if(polyline) map.removeLayer(polyline);
        if(marker) map.removeLayer(marker);
        document.getElementById('player-controls').classList.add('hidden');
        
        const btn = document.querySelector('button[onclick="loadHistory()"]');
        const oldTxt = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
        btn.disabled = true;

        try {
            const url = `${API_URL}?endpoint=/reports/route&deviceId=${deviceId}&from=${from}&to=${to}`;
            const res = await fetch(url);
            if (!res.ok) throw new Error("Erro API: " + res.status);

            const data = await res.json();

            if (!Array.isArray(data) || data.length === 0) {
                alert("Nenhum histórico encontrado.");
                return;
            }

            routeData = data;
            drawRoute(deviceId); // Passa o deviceId para pegar o ícone certo
            document.getElementById('player-controls').classList.remove('hidden');
            document.getElementById('player-controls').classList.add('flex');

        } catch (e) {
            console.error(e);
            alert("Erro ao buscar histórico.");
        } finally {
            btn.innerHTML = oldTxt;
            btn.disabled = false;
        }
    }

    function drawRoute(deviceId) {
        const latlngs = routeData.map(p => [p.latitude, p.longitude]);
        
        // Desenha Rota
        polyline = L.polyline(latlngs, {color: '#4f46e5', weight: 4, opacity: 0.8, lineJoin: 'round'}).addTo(map);
        map.fitBounds(polyline.getBounds(), {padding: [50, 50]});

        const start = routeData[0];
        const iconUrl = vehicleIconsMap[deviceId];
        let customIcon;

        if (iconUrl) {
            // CORREÇÃO: Removemos a barra inicial "/" para usar caminho relativo
            // Isso conserta o erro 404 se o sistema estiver em subpasta
            customIcon = L.divIcon({
                className: 'custom-vehicle-icon',
                html: `<img src="${iconUrl}" style="width:40px; height:40px; object-fit:contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));">`,
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
        } else {
            // Ícone Padrão
            customIcon = L.divIcon({
                className: 'default-icon',
                html: '<div class="w-8 h-8 bg-indigo-600 rounded-full border-2 border-white shadow-lg flex items-center justify-center text-white"><i class="fas fa-arrow-up text-xs"></i></div>',
                iconSize: [32, 32],
                iconAnchor: [16, 16]
            });
        }

        marker = L.marker([start.latitude, start.longitude], {
            icon: customIcon,
            rotationAngle: start.course || 0
        }).addTo(map);

        updateInfo(0);
    }

    function togglePlay() {
        if (isPlaying) stopPlay();
        else startPlay();
    }

    function startPlay() {
        if (routeData.length < 2) return;
        isPlaying = true;
        document.getElementById('btn-play').innerHTML = '<i class="fas fa-pause"></i>';
        lastTimestamp = performance.now();
        
        if (currentIndex >= routeData.length - 1) {
            currentIndex = 0;
            progressBetweenPoints = 0;
        }
        animationFrameId = requestAnimationFrame(animateStep);
    }

    function animateStep(timestamp) {
        if (!isPlaying) return;

        const elapsed = timestamp - lastTimestamp;
        lastTimestamp = timestamp;
        
        // Velocidade da interpolação
        const stepDuration = parseInt(document.getElementById('playback-speed').value);
        
        progressBetweenPoints += elapsed / stepDuration;

        if (progressBetweenPoints >= 1) {
            progressBetweenPoints = 0;
            currentIndex++;
            
            if (currentIndex >= routeData.length - 1) {
                currentIndex = routeData.length - 1;
                updateMarkerPosition(currentIndex, 0);
                stopPlay();
                return;
            }
            updateInfo(currentIndex);
        }

        updateMarkerPosition(currentIndex, progressBetweenPoints);
        animationFrameId = requestAnimationFrame(animateStep);
    }

    function updateMarkerPosition(idx, progress) {
        const p1 = routeData[idx];
        const p2 = routeData[idx + 1] || p1;

        // Interpolação de Posição
        const lat = p1.latitude + (p2.latitude - p1.latitude) * progress;
        const lng = p1.longitude + (p2.longitude - p1.longitude) * progress;
        
        // Interpolação de Rotação (Suaviza curvas)
        let angle1 = p1.course || 0;
        let angle2 = p2.course || 0;
        if (Math.abs(angle1 - angle2) > 180) {
            if (angle1 > angle2) angle1 -= 360; else angle2 -= 360;
        }
        const currentAngle = angle1 + (angle2 - angle1) * progress;

        const newPos = [lat, lng];
        marker.setLatLng(newPos);
        marker.setRotationAngle(currentAngle);

        if (!map.getBounds().pad(-0.1).contains(marker.getLatLng())) {
            map.panTo(newPos, { animate: true, duration: 0.5 });
        }

        const totalProgress = (idx + progress) / (routeData.length - 1);
        document.getElementById('progress-bar').style.width = (totalProgress * 100) + '%';
    }

    function stopPlay() {
        isPlaying = false;
        cancelAnimationFrame(animationFrameId);
        document.getElementById('btn-play').innerHTML = '<i class="fas fa-play ml-1"></i>';
    }

    function updateInfo(index) {
        const point = routeData[index];
        const date = new Date(point.fixTime);
        document.getElementById('current-time').innerText = date.toLocaleTimeString();
        const kmh = Math.round(point.speed * 1.852);
        document.getElementById('current-speed').innerText = kmh + ' km/h';
    }

    function seek(e) {
        if (!routeData.length) return;
        const container = document.getElementById('progress-container');
        const rect = container.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const pct = Math.max(0, Math.min(1, clickX / rect.width));
        
        const totalPoints = routeData.length - 1;
        const exactIndex = pct * totalPoints;
        
        currentIndex = Math.floor(exactIndex);
        progressBetweenPoints = exactIndex - currentIndex;
        
        updateMarkerPosition(currentIndex, progressBetweenPoints);
        updateInfo(currentIndex);
    }
</script>