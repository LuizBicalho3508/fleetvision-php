<?php
// mapa.php
if (session_status() === PHP_SESSION_NONE) {} 
if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='/admin/login';</script>"; exit; }
$wsUrl = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'wss' : 'ws') . "://" . $_SERVER['HTTP_HOST'] . "/api/socket";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Monitoramento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; font-family: 'Inter', sans-serif; }
        #map { width: 100vw; height: 100vh; z-index: 0; }
        .map-btn { width: 40px; height: 40px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.15); cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; color: #475569; }
        .map-btn:hover { background: #f8fafc; color: #3b82f6; }
        .map-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
        #bottom-drawer { position: absolute; bottom: 0; left: 0; right: 0; background: white; z-index: 800; border-top-left-radius: 16px; border-top-right-radius: 16px; box-shadow: 0 -5px 20px rgba(0,0,0,0.1); transition: height 0.3s ease; display: flex; flex-direction: column; }
        .drawer-open { height: 45vh; }
        .drawer-closed { height: 50px; }
        .map-table { width: 100%; border-collapse: collapse; }
        .map-table th { position: sticky; top: 0; background: #f8fafc; color: #64748b; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; padding: 12px 16px; border-bottom: 2px solid #e2e8f0; z-index: 10; text-align: left; }
        .map-table td { padding: 10px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 0.75rem; white-space: nowrap; vertical-align: middle; }
        .map-table tr:hover { background-color: #f1f5f9; cursor: pointer; }
        .row-selected { background-color: #eff6ff !important; border-left: 4px solid #3b82f6; }
        .custom-popup .leaflet-popup-content-wrapper { padding: 0; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2); }
        .custom-popup .leaflet-popup-content { margin: 0; width: 280px !important; }
        .custom-popup .leaflet-popup-tip { background: white; }
    </style>
</head>
<body>
    <div id="map-loading" class="absolute inset-0 z-[2000] bg-white flex flex-col items-center justify-center">
        <i class="fas fa-circle-notch fa-spin text-4xl text-blue-600 mb-4"></i>
        <p class="text-slate-500 font-bold">Carregando Frota...</p>
    </div>
    <div id="map"></div>

    <div class="absolute top-4 left-4 z-[900] w-72">
        <div class="bg-white p-2 rounded-lg shadow-md border border-gray-200 flex items-center gap-2">
            <i class="fas fa-search text-gray-400 ml-2"></i>
            
            <input type="text" name="fake_email_avoid_auto" style="position:absolute; top:-1000px; opacity:0; pointer-events:none;" tabindex="-1">
            <input type="password" name="fake_pass_avoid_auto" style="position:absolute; top:-1000px; opacity:0; pointer-events:none;" tabindex="-1">
            
            <input type="text" id="map-search" onkeyup="renderMap()" 
                   placeholder="Buscar Placa, Motorista..." 
                   class="w-full outline-none text-sm text-gray-700 bg-transparent"
                   autocomplete="new-password" name="search_rnd_<?php echo uniqid(); ?>">
            
            <span id="follow-badge" class="hidden text-[10px] bg-blue-600 text-white px-2 py-1 rounded font-bold uppercase cursor-pointer" onclick="stopFollowing()">SEGUINDO</span>
            <div id="ws-status" class="w-2 h-2 rounded-full bg-gray-300 mr-1" title="Status Conexão"></div>
        </div>
    </div>

    <div class="absolute top-4 right-4 z-[900] flex flex-col gap-2">
        <div class="bg-white p-1 rounded-lg shadow-md border border-gray-200 flex flex-col gap-1">
            <button onclick="setLayer('streets')" class="map-btn active" id="btn-streets"><i class="fas fa-road"></i></button>
            <button onclick="setLayer('satellite')" class="map-btn" id="btn-sat"><i class="fas fa-satellite"></i></button>
        </div>
        <button onclick="fitAll()" class="map-btn"><i class="fas fa-expand-arrows-alt"></i></button>
    </div>

    <div id="bottom-drawer" class="drawer-closed">
        <div class="h-[50px] border-b border-gray-200 flex justify-between items-center px-4 cursor-pointer hover:bg-gray-50" onclick="toggleDrawer()">
            <div class="flex items-center gap-3">
                <i class="fas fa-chevron-up text-gray-400 transition-transform duration-300" id="drawer-icon"></i>
                <h3 class="font-bold text-gray-700 text-sm">Veículos (<span id="total-count">0</span>)</h3>
            </div>
            <div class="flex gap-4 text-xs font-mono">
                <span class="text-emerald-600 font-bold"><i class="fas fa-wifi text-[10px] mr-1"></i> <span id="cnt-on">0</span></span>
                <span class="text-red-500 font-bold"><i class="fas fa-power-off text-[10px] mr-1"></i> <span id="cnt-off">0</span></span>
            </div>
        </div>
        <div class="flex-1 overflow-auto bg-white">
            <table class="map-table">
                <thead>
                    <tr>
                        <th class="pl-4">Placa</th>
                        <th>IMEI</th>
                        <th>Motorista</th>
                        <th>Cliente</th>
                        <th>Endereço</th>
                        <th class="text-center">Data</th>
                        <th class="text-center">Velocidade</th>
                        <th class="text-center">Ign.</th>
                        <th class="text-center">Sats</th>
                        <th class="text-center">Volt.</th>
                        <th class="text-center">Bat. Int.</th>
                        <th class="text-center">Ação</th>
                    </tr>
                </thead>
                <tbody id="grid-body"></tbody>
            </table>
        </div>
    </div>

    <div id="modal-security" class="fixed inset-0 bg-black/60 hidden z-[2000] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-96 overflow-hidden">
            <div class="bg-red-50 p-4 border-b border-red-100 flex justify-between items-center">
                <h3 class="text-red-700 font-bold flex items-center gap-2"><i class="fas fa-shield-alt"></i> Segurança</h3>
                <button onclick="document.getElementById('modal-security').classList.add('hidden')" class="text-gray-400">&times;</button>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-600 mb-4">Confirmar comando: <strong id="sec-cmd-name" class="uppercase"></strong> para <strong id="sec-veh-name"></strong>?</p>
                <input type="hidden" id="sec-dev-id"><input type="hidden" id="sec-cmd-type">
                <input type="password" id="sec-password" class="w-full border p-2 rounded mb-4 outline-none focus:border-red-500" placeholder="Sua Senha">
                <button onclick="executeCommand()" id="btn-sec-confirm" class="w-full bg-red-600 text-white font-bold py-2 rounded shadow hover:bg-red-700">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        const WS_URL = "<?php echo $wsUrl; ?>";
        let map, markers={}, isDrawerOpen=false, followingId=null;
        let lastDevices=[], positionsMap={}, vehicleState={}, allowedDeviceIds=[];
        let staticInfo={}, iconData={}, customerData={};
        let geoCache = JSON.parse(sessionStorage.getItem('geoCache')||'{}'), geoQueue=[];

        document.addEventListener('DOMContentLoaded', () => { initMap(); loadInitialData(); });

        async function loadInitialData() {
            try {
                const res = await fetch('/api_mapa.php?action=get_initial_data');
                const json = await res.json();
                if (json.success) {
                    staticInfo = json.config.staticInfo;
                    iconData = json.config.icons;
                    customerData = json.config.customers;
                    allowedDeviceIds = json.config.allowedIds;
                    lastDevices = json.data.devices;
                    
                    json.data.positions.forEach(p => { 
                        if(allowedDeviceIds.includes(p.deviceId)) {
                            positionsMap[p.deviceId] = p;
                            if(p.saas_state) vehicleState[p.deviceId] = p.saas_state;
                        }
                    });

                    document.getElementById('map-loading').classList.add('hidden');
                    renderMap(); fitAll();
                    connectWS(json.config.wsToken);
                    startGeocoding();
                } else alert(json.error);
            } catch(e) { console.error(e); }
        }

        // --- NORMALIZAÇÃO JS (Refletindo PHP) ---
        function normalizeStateJS(p) {
            const did = p.deviceId;
            const old = vehicleState[did] || {};
            const attr = p.attributes || {};

            let ign = attr.ignition ?? attr.motion ?? old.ignition;
            let pwr = attr.power ?? attr.adc1 ?? attr.extBatt;
            if(!pwr && attr.charge) pwr=12.0;
            if(pwr > 1000) pwr = pwr / 1000;
            if(pwr === undefined) pwr = old.power;

            let bat = attr.batteryLevel;
            if(bat === undefined && attr.battery !== undefined) {
                let b = attr.battery;
                if(b > 100) b /= 1000;
                if(b > 0) bat = Math.min(100, Math.max(0, Math.round((b-3.6)/(4.2-3.6)*100)));
            }
            if(bat === undefined) bat = old.battery;

            let sat = attr.sat ?? old.sat;
            // IMPORTANTE: Check 'out1' aqui também para o tempo real
            let blk = attr.blocked ?? attr.out1 ?? old.blocked;

            // Motorista: Suntech Serial Parser JS
            let drv = old.driver_name;
            if(attr.serial) {
                const parts = attr.serial.split('|');
                // Se tiver ID no indice 4, precisaríamos de um mapa de ID->Nome no JS
                // Como isso é complexo de manter sincronizado, confiamos que o PHP 
                // vai atualizar o 'driver_name' no próximo refresh ou polling.
                // Mas podemos mostrar o ID cru se quisermos:
                if(parts.length >= 5 && parts[4] > 0) {
                    // drv = "ID: " + parts[4]; // Opcional, melhor deixar o PHP resolver nomes
                }
            }

            return { ignition:!!ign, battery:bat, power:pwr, sat:sat, blocked:!!blk, driver_name:drv };
        }

        function initMap() {
            const street = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {maxZoom:20});
            const sat = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {maxZoom:19});
            map = L.map('map', {layers:[street], zoomControl:false}).setView([-14.235,-51.925],4);
            L.control.zoom({position:'bottomright'}).addTo(map);
            map.on('dragstart', stopFollowing);
            map.layersControl = {street, sat};
        }

        function setLayer(t) {
            document.querySelectorAll('.map-btn').forEach(b=>b.classList.remove('active'));
            if(t==='streets') { map.addLayer(map.layersControl.street); map.removeLayer(map.layersControl.sat); document.getElementById('btn-streets').classList.add('active'); }
            if(t==='satellite') { map.addLayer(map.layersControl.sat); map.removeLayer(map.layersControl.street); document.getElementById('btn-sat').classList.add('active'); }
        }

        function renderMap() {
            const tbody = document.getElementById('grid-body');
            const filter = document.getElementById('map-search').value.toLowerCase();
            let html='', on=0, off=0;

            lastDevices.forEach(d => {
                if(!allowedDeviceIds.includes(d.id)) return;
                const info = staticInfo[d.id] || {plate:d.name, driver:'-', name:d.name};
                const st = vehicleState[d.id] || {}; 
                // Se o PHP resolveu motorista via serial, usa ele. Senão, usa estático.
                const displayDriver = st.driver_name || info.driver;

                if(filter && !(info.plate+d.name+displayDriver).toLowerCase().includes(filter)) return;
                
                if(d.status==='online') on++; else off++;
                const p = positionsMap[d.id];
                
                let speed='-', date='-', addr='...', ign='-', volt='-', sats='-', batHtml='-', lat=0, lon=0;

                if(p) {
                    lat = p.latitude; lon = p.longitude;
                    speed = Math.round(p.speed*1.852);
                    date = new Date(p.fixTime).toLocaleString('pt-BR');
                    
                    const k = lat.toFixed(5)+','+lon.toFixed(5);
                    if(geoCache[k]) addr=geoCache[k]; else { addr='...'; geoQueue.push({id:d.id, lat, lon}); }

                    ign = st.ignition 
                        ? '<i class="fas fa-key text-emerald-500 text-lg" title="Ligada"></i>' 
                        : '<i class="fas fa-key text-red-500 text-lg" title="Desligada"></i>';
                    
                    if(st.power) volt = `<span class="font-mono font-bold text-slate-700">${parseFloat(st.power).toFixed(1)}v</span> <i class="fas fa-bolt text-amber-500 text-[10px]"></i>`;
                    sats = `<span class="font-mono text-slate-600">${st.sat||0}</span> <i class="fas fa-satellite text-blue-400 text-[10px]"></i>`;

                    if(st.battery !== undefined && st.battery !== null) {
                        let val = parseInt(st.battery);
                        let icon = 'fa-battery-empty text-red-500';
                        if(val > 90) icon = 'fa-battery-full text-emerald-500';
                        else if(val > 60) icon = 'fa-battery-three-quarters text-emerald-500';
                        else if(val > 30) icon = 'fa-battery-half text-amber-500';
                        else if(val > 10) icon = 'fa-battery-quarter text-red-500';
                        batHtml = `<span class="font-mono font-bold text-xs">${val}%</span> <i class="fas ${icon} ml-1"></i>`;
                    }
                }

                // Lógica de Botão: Se Bloqueado (blocked=true) -> Mostra DESBLOQUEAR
                const lockBtn = st.blocked 
                    ? `<button onclick="openLockModal(event, ${d.id}, '${info.plate}', true)" class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold border border-emerald-200 hover:bg-emerald-200 w-24 flex items-center justify-center gap-1"><i class="fas fa-unlock"></i> DESBLOQ.</button>`
                    : `<button onclick="openLockModal(event, ${d.id}, '${info.plate}', false)" class="px-2 py-1 bg-red-100 text-red-700 rounded text-[10px] font-bold border border-red-200 hover:bg-red-200 w-24 flex items-center justify-center gap-1"><i class="fas fa-lock"></i> BLOQUEAR</button>`;

                const isSel = (followingId === d.id) ? 'row-selected' : '';
                html += `<tr onclick="focusDev(${lat},${lon},${d.id})" class="border-b border-gray-100 transition ${isSel}" id="row-${d.id}">
                    <td class="pl-4 font-bold text-slate-700 text-xs">${info.plate}</td>
                    <td class="text-[10px] font-mono text-gray-500">${d.uniqueId||'-'}</td>
                    <td class="text-indigo-600 font-bold text-[10px] uppercase">${displayDriver||'-'}</td>
                    <td class="text-xs text-gray-500">${customerData[d.id]||'-'}</td>
                    <td class="text-xs text-gray-500 truncate max-w-[150px]" id="addr-${d.id}">${addr}</td>
                    <td class="text-center text-[10px] font-mono text-gray-400">${date}</td>
                    <td class="text-center font-bold text-blue-600 text-xs">${speed} <small class="text-gray-400 font-normal">km/h</small></td>
                    <td class="text-center">${ign}</td>
                    <td class="text-center text-xs">${sats}</td>
                    <td class="text-center text-xs">${volt}</td>
                    <td class="text-center text-xs">${batHtml}</td>
                    <td class="text-center">${lockBtn}</td>
                </tr>`;

                if(lat && lon) updateMarker(d, lat, lon, speed, st.ignition, info, addr, st, displayDriver);
            });

            tbody.innerHTML = html || '<tr><td colspan="12" class="p-4 text-center">Nenhum veículo encontrado.</td></tr>';
            document.getElementById('cnt-on').innerText = on;
            document.getElementById('cnt-off').innerText = off;
            document.getElementById('total-count').innerText = on+off;
        }

        function updateMarker(d, lat, lon, speed, ign, info, addr, st, displayDriver) {
            const latlng = [lat, lon];
            let iconHtml;
            const iconUrl = iconData[d.id] ? (iconData[d.id].startsWith('/') ? iconData[d.id] : '/'+iconData[d.id]) : null;
            const course = positionsMap[d.id]?.course || 0;

            if(iconUrl) {
                iconHtml = `<div style="transform: rotate(${course}deg); transition: transform 0.5s; filter: drop-shadow(0 3px 5px rgba(0,0,0,0.3));"><img src="${iconUrl}" style="width:40px; height:40px; object-fit:contain;"></div>`;
            } else {
                const color = d.status==='online'?'#10b981':'#ef4444';
                iconHtml = `<div style="transform: rotate(${course}deg); background:${color}; width:30px; height:30px; border-radius:50%; border:2px solid white; display:flex; justify-content:center; align-items:center; box-shadow:0 2px 5px rgba(0,0,0,0.3);"><i class="fas fa-chevron-up text-white text-[10px]"></i></div>`;
            }
            const icon = L.divIcon({className:'bg-transparent border-0', html:iconHtml, iconSize:[40,40]});

            const statusBadge = st.blocked 
                ? '<span class="bg-red-500 text-white px-2 py-0.5 rounded text-[10px] font-bold">BLOQUEADO</span>' 
                : '<span class="bg-emerald-500 text-white px-2 py-0.5 rounded text-[10px] font-bold">LIBERADO</span>';
            const ignText = ign ? '<span class="text-emerald-600 font-bold">LIGADA</span>' : '<span class="text-red-500 font-bold">DESLIGADA</span>';

            const popup = `
                <div class="font-sans text-gray-700 min-w-[260px]">
                    <div class="bg-slate-800 text-white p-3 flex justify-between items-center rounded-t-xl">
                        <div>
                            <div class="font-bold text-sm">${info.plate}</div>
                            <div class="text-[10px] text-slate-300 font-mono">${d.uniqueId || '-'}</div>
                        </div>
                        ${statusBadge}
                    </div>
                    <div class="p-4 bg-white rounded-b-xl">
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div><span class="text-[9px] text-gray-400 uppercase font-bold">Velocidade</span><br><span class="text-xl font-bold text-blue-600">${speed}</span> <small class="text-gray-400">km/h</small></div>
                            <div><span class="text-[9px] text-gray-400 uppercase font-bold">Ignição</span><br>${ignText}</div>
                        </div>
                        <div class="flex justify-between border-t border-b border-gray-100 py-2 mb-3 text-xs text-gray-600">
                            <div class="flex items-center gap-1" title="Bat. Int"><i class="fas fa-battery-half text-emerald-500"></i> <b>${st.battery||0}%</b></div>
                            <div class="flex items-center gap-1" title="Volt"><i class="fas fa-bolt text-amber-500"></i> <b>${parseFloat(st.power||0).toFixed(1)}v</b></div>
                            <div class="flex items-center gap-1" title="Sats"><i class="fas fa-satellite text-blue-400"></i> <b>${st.sat||0}</b></div>
                        </div>
                        <div class="mb-3">
                            <span class="text-[9px] text-gray-400 uppercase font-bold">Motorista</span><br>
                            <div class="flex items-center gap-2 font-bold text-indigo-600 text-sm"><i class="fas fa-user-circle text-lg"></i> ${displayDriver || 'Não identificado'}</div>
                        </div>
                        <div class="bg-slate-50 p-2 rounded border border-slate-100 text-[10px] text-gray-500 flex gap-2 items-start">
                            <i class="fas fa-map-marker-alt text-red-400 mt-0.5"></i>
                            <span id="pop-addr-${d.id}" class="leading-tight">${addr}</span>
                        </div>
                    </div>
                </div>`;

            if(markers[d.id]) {
                markers[d.id].setLatLng(latlng).setIcon(icon);
                if(!markers[d.id].isPopupOpen()) markers[d.id].bindPopup(popup, {className:'custom-popup'});
            } else {
                markers[d.id] = L.marker(latlng, {icon}).addTo(map).bindPopup(popup, {className:'custom-popup'});
            }
        }

        function connectWS(token) {
            if(token) document.cookie = "JSESSIONID="+token+"; path=/; SameSite=Lax";
            try {
                const socket = new WebSocket(WS_URL);
                socket.onopen = () => document.getElementById('ws-status').className="w-2 h-2 rounded-full bg-emerald-500 shadow-md";
                socket.onmessage = (e) => {
                    const d = JSON.parse(e.data);
                    if(d.devices) { 
                        const m = new Map(lastDevices.map(i=>[i.id,i]));
                        d.devices.forEach(u=>m.set(u.id,{...m.get(u.id),...u}));
                        lastDevices=Array.from(m.values());
                        renderMap();
                    }
                    if(d.positions) {
                        d.positions.forEach(p => {
                            if(allowedDeviceIds.includes(p.deviceId)) {
                                positionsMap[p.deviceId]=p;
                                vehicleState[p.deviceId] = normalizeStateJS(p); 
                            }
                        });
                        renderMap();
                    }
                };
                socket.onclose = () => { document.getElementById('ws-status').className="w-2 h-2 rounded-full bg-red-500"; setTimeout(()=>connectWS(token), 3000); };
            } catch(e){}
        }

        function startGeocoding() {
            setInterval(() => {
                if(geoQueue.length===0) return;
                const item = geoQueue.shift();
                const k = item.lat.toFixed(5)+','+item.lon.toFixed(5);
                if(geoCache[k]) { updateDomAddr(item.id, geoCache[k]); return; }
                fetch(`/api_mapa.php?action=geocode&lat=${item.lat}&lon=${item.lon}`)
                    .then(r=>r.json()).then(d=>{
                        const txt = d.address || 'Local desconhecido';
                        geoCache[k] = txt; sessionStorage.setItem('geoCache', JSON.stringify(geoCache));
                        updateDomAddr(item.id, txt);
                    }).catch(()=>{});
            }, 1200);
        }

        function updateDomAddr(id, txt) {
            const el = document.getElementById('addr-'+id); if(el) el.innerText=txt;
            const pop = document.getElementById('pop-addr-'+id); if(pop) pop.innerText=txt;
        }

        function fitAll() { followingId=null; document.getElementById('follow-badge').classList.add('hidden'); const g = new L.featureGroup(Object.values(markers)); if(g.getLayers().length) map.fitBounds(g.getBounds(), {padding:[50,50]}); }
        function focusDev(lat,lon,id) { followingId=id; document.getElementById('follow-badge').classList.remove('hidden'); map.flyTo([lat,lon], 17); if(markers[id]) markers[id].openPopup(); renderMap(); }
        function stopFollowing() { followingId=null; document.getElementById('follow-badge').classList.add('hidden'); renderMap(); }
        function toggleDrawer() { 
            isDrawerOpen = !isDrawerOpen;
            const d = document.getElementById('bottom-drawer');
            const i = document.getElementById('drawer-icon');
            d.className = isDrawerOpen ? "absolute bottom-0 left-0 right-0 z-[600] bg-white rounded-t-xl flex flex-col transition-all duration-300 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] drawer-open" : "absolute bottom-0 left-0 right-0 z-[600] bg-white rounded-t-xl flex flex-col transition-all duration-300 shadow-[0_-5px_20px_rgba(0,0,0,0.1)] drawer-closed";
            i.style.transform = isDrawerOpen ? 'rotate(180deg)' : 'rotate(0deg)';
        }
        
        function openLockModal(e, id, plate, isBlocked) { 
            e.stopPropagation(); document.getElementById('modal-security').classList.remove('hidden');
            document.getElementById('sec-dev-id').value = id; document.getElementById('sec-veh-name').innerText = plate;
            document.getElementById('sec-cmd-type').value = isBlocked ? 'engineResume' : 'engineStop';
            document.getElementById('sec-cmd-name').innerText = isBlocked ? "DESBLOQUEAR" : "BLOQUEAR";
        }
        
        async function executeCommand() {
            const id = document.getElementById('sec-dev-id').value;
            const type = document.getElementById('sec-cmd-type').value;
            const pass = document.getElementById('sec-password').value;
            if(!pass) return alert('Senha obrigatória');
            try {
                const res = await fetch('/api_mapa.php?action=secure_command', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({deviceId:id, type:type, password:pass})
                });
                if(res.ok) { alert('Comando enviado!'); document.getElementById('modal-security').classList.add('hidden'); }
                else alert('Erro ao enviar');
            } catch(e) { alert('Erro conexão'); }
        }
    </script>
</body>
</html>