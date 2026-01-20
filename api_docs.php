<?php
// api_docs.php - Documentação Completa para Desenvolvimento Mobile
if (!isset($_SESSION['user_id'])) exit;

$baseUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
?>

<div class="h-full flex flex-col bg-slate-50">
    <div class="bg-white border-b border-slate-200 px-8 py-6 shadow-sm z-10">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">API Reference <span class="text-indigo-600 text-lg font-mono bg-indigo-50 px-2 py-1 rounded ml-2">v1.0 Mobile</span></h1>
                <p class="text-slate-500 mt-2">Documentação oficial para desenvolvimento do App Mobile Whitelabel (iOS/Android).</p>
            </div>
            <div class="flex gap-3">
                <span class="px-4 py-2 bg-green-100 text-green-700 rounded-lg text-xs font-bold border border-green-200 uppercase">Live Environment</span>
            </div>
        </div>
    </div>

    <div class="flex-1 flex overflow-hidden">
        <div class="w-64 bg-white border-r border-slate-200 overflow-y-auto hidden md:block">
            <nav class="p-4 space-y-1">
                <a href="#auth" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">1. Autenticação</a>
                <a href="#monitoring" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">2. Monitoramento & Mapa</a>
                <a href="#commands" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">3. Comandos & Segurança</a>
                <a href="#users" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">4. Usuários & Equipe</a>
                <a href="#profiles" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">5. Perfis de Acesso</a>
                <a href="#reports" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">6. Relatórios & Histórico</a>
                <a href="#financial" class="block px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded">7. Financeiro (Asaas)</a>
            </nav>
        </div>

        <div class="flex-1 overflow-y-auto p-8 scroll-smooth">
            
            <div class="mb-12">
                <h2 class="text-xl font-bold text-slate-800 mb-4">Visão Geral</h2>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded text-blue-900 text-sm">
                    <p class="font-bold mb-2">Base URL: <span class="font-mono bg-white px-1 rounded"><?php echo $baseUrl; ?></span></p>
                    <p>O aplicativo deve manter o cookie de sessão (PHPSESSID) retornado no login para autenticar as requisições subsequentes. Todas as respostas são em JSON.</p>
                </div>
            </div>

            <section id="auth" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">1</span> Autenticação</h3>
                
                <?php renderEndpoint('POST', '/api_login.php', 'Login no App', 
                    'Autentica o usuário (Admin, Cliente ou Motorista) e inicia a sessão.',
                    [
                        'email' => 'string (Obrigatório)',
                        'password' => 'string (Obrigatório)',
                        'tenant' => 'string (Opcional - Slug da empresa, padrão: admin)'
                    ],
                    [
                        'success' => true,
                        'session_id' => 'abc12345...',
                        'user' => ['id' => 1, 'name' => 'Fulano', 'role' => 'admin', 'tenant_id' => 1]
                    ]
                ); ?>
            </section>

            <section id="monitoring" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">2</span> Monitoramento</h3>

                <?php renderEndpoint('GET', '/api_dados.php?endpoint=/devices', 'Listar Veículos', 
                    'Retorna a lista de veículos com status online/offline atualizado.',
                    [],
                    [
                        [
                            'id' => 12,
                            'name' => 'Caminhão 01',
                            'status' => 'online',
                            'lastUpdate' => '2023-10-25T14:00:00.000+00:00'
                        ]
                    ]
                ); ?>

                <?php renderEndpoint('GET', '/api_dados.php?endpoint=/positions', 'Posições em Tempo Real', 
                    'Retorna as últimas coordenadas GPS de todos os veículos permitidos. Usar em polling (a cada 5s) ou WebSocket.',
                    [],
                    [
                        [
                            'deviceId' => 12,
                            'latitude' => -23.5505,
                            'longitude' => -46.6333,
                            'speed' => 15.5,
                            'course' => 180,
                            'attributes' => ['ignition' => true, 'batteryLevel' => 100]
                        ]
                    ]
                ); ?>

                <?php renderEndpoint('GET', '/api_dados.php?action=get_kpis', 'KPIs do Dashboard', 
                    'Contadores rápidos para a tela inicial do App.',
                    [],
                    [
                        'total_vehicles' => 50,
                        'online' => 45,
                        'offline' => 5,
                        'moving' => 30,
                        'stopped' => 20
                    ]
                ); ?>
            </section>

            <section id="commands" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">3</span> Comandos & Segurança</h3>

                <?php renderEndpoint('POST', '/api_dados.php?action=secure_command', 'Enviar Bloqueio/Comando', 
                    'Envia comando GPRS para o rastreador. Exige senha do usuário logado para segurança.',
                    [
                        'deviceId' => 'int (ID do veículo)',
                        'type' => 'string (lock, unlock, engineStop, engineResume)',
                        'password' => 'string (Senha atual do usuário)'
                    ],
                    ['success' => true, 'message' => 'Comando enviado!']
                ); ?>
            </section>

            <section id="users" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">4</span> Gestão de Usuários</h3>

                <?php renderEndpoint('GET', '/api_dados.php?action=get_users', 'Listar Usuários', 
                    'Lista todos os usuários da empresa (ou todos se for SuperAdmin).',
                    [],
                    [['id' => 1, 'name' => 'Gestor', 'email' => 'gestor@empresa.com', 'role_name' => 'Gerente']]
                ); ?>

                <?php renderEndpoint('POST', '/api_dados.php?action=save_user', 'Criar/Editar Usuário', 
                    'Cria ou atualiza um usuário.',
                    [
                        'id' => 'int (Opcional, se null cria novo)',
                        'name' => 'string',
                        'email' => 'string',
                        'password' => 'string (Opcional na edição)',
                        'role_id' => 'int (ID do Perfil)',
                        'active' => 'boolean'
                    ],
                    ['success' => true]
                ); ?>

                <?php renderEndpoint('POST', '/api_dados.php?action=delete_user', 'Excluir Usuário', 
                    'Remove um usuário do sistema.',
                    ['id' => 'int'],
                    ['success' => true]
                ); ?>
            </section>

            <section id="profiles" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">5</span> Perfis de Acesso</h3>

                <?php renderEndpoint('GET', '/api_perfis.php?action=get_profiles', 'Listar Perfis', 
                    'Retorna lista de perfis e permissões.',
                    [],
                    [['id' => 1, 'name' => 'Admin', 'permissions' => '["map_view", "users_manage"]']]
                ); ?>

                <?php renderEndpoint('GET', '/api_dados.php?action=get_roles&tenant_id=X', 'Listar Perfis (SuperAdmin)', 
                    'Busca perfis de um tenant específico (usado no cadastro de usuários pelo SuperAdmin).',
                    ['tenant_id' => 'int'],
                    [['id' => 1, 'name' => 'Admin Global']]
                ); ?>
            </section>

            <section id="reports" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">6</span> Relatórios & Histórico</h3>

                <?php renderEndpoint('GET', '/api_dados.php?action=get_history_positions', 'Rota Percorrida (Replay)', 
                    'Retorna array de posições para desenhar o trajeto no mapa.',
                    [
                        'deviceId' => 'int',
                        'date' => 'string (YYYY-MM-DD)'
                    ],
                    [
                        ['latitude' => -10.0, 'longitude' => -50.0, 'speed' => 20, 'fixTime' => '2023...']
                    ]
                ); ?>

                <?php renderEndpoint('GET', '/api_dados.php?action=geocode', 'Geocoding Reverso', 
                    'Converte Lat/Lon em Endereço legível.',
                    ['lat' => 'float', 'lon' => 'float'],
                    ['address' => 'Rua Exemplo, 123 - Centro, Cidade - UF']
                ); ?>
            </section>

            <section id="financial" class="mb-16">
                <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><span class="bg-slate-200 text-slate-600 w-8 h-8 flex items-center justify-center rounded text-sm">7</span> Financeiro (Asaas)</h3>

                <?php renderEndpoint('POST', '/api_dados.php?action=asaas_proxy&asaas_endpoint=/payments', 'Criar Cobrança', 
                    'Proxy seguro para API do Asaas. Repassa a requisição usando o Token do Tenant salvo no banco.',
                    [
                        'customer' => 'cus_1234',
                        'billingType' => 'BOLETO',
                        'value' => 100.00,
                        'dueDate' => '2023-12-31'
                    ],
                    ['id' => 'pay_1234', 'netValue' => 99.00, 'invoiceUrl' => 'https://...']
                ); ?>
            </section>

        </div>
    </div>
</div>

<?php
// Função Helper para renderizar blocos de endpoint
function renderEndpoint($method, $url, $title, $desc, $params, $response) {
    $methodColor = match($method) {
        'GET' => 'text-blue-700 bg-blue-100 border-blue-200',
        'POST' => 'text-green-700 bg-green-100 border-green-200',
        'DELETE' => 'text-red-700 bg-red-100 border-red-200',
        'PUT' => 'text-orange-700 bg-orange-100 border-orange-200',
        default => 'text-gray-700 bg-gray-100'
    };
    
    $jsonParams = json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $jsonResponse = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $id = md5($url . $method);

    echo "
    <div class='bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm mb-8 hover:shadow-md transition'>
        <div class='p-5 border-b border-slate-100 cursor-pointer bg-slate-50/50' onclick=\"toggleCode('$id')\">
            <div class='flex items-center gap-3 mb-2'>
                <span class='px-3 py-1 rounded text-xs font-mono font-bold border $methodColor'>$method</span>
                <span class='font-mono text-sm text-slate-600 break-all'>$url</span>
            </div>
            <h4 class='text-lg font-bold text-slate-800'>$title</h4>
            <p class='text-slate-500 text-sm mt-1'>$desc</p>
        </div>
        
        <div id='code-$id' class='hidden bg-slate-900 text-slate-300 font-mono text-xs p-5'>
            <div class='grid md:grid-cols-2 gap-8'>
                <div>
                    <div class='flex justify-between mb-2 text-slate-500 font-bold uppercase text-[10px]'>
                        <span>Payload (Body/Query)</span>
                        <button onclick='copyToClip(this)' class='hover:text-white transition'><i class='far fa-copy'></i></button>
                    </div>
                    <pre class='bg-black/30 p-3 rounded border border-white/10 overflow-auto max-h-64'>$jsonParams</pre>
                </div>
                <div>
                    <div class='flex justify-between mb-2 text-slate-500 font-bold uppercase text-[10px]'>
                        <span>Exemplo de Resposta (200 OK)</span>
                        <button onclick='copyToClip(this)' class='hover:text-white transition'><i class='far fa-copy'></i></button>
                    </div>
                    <pre class='bg-black/30 p-3 rounded border border-white/10 overflow-auto max-h-64 text-green-400'>$jsonResponse</pre>
                </div>
            </div>
        </div>
    </div>";
}
?>

<script>
function toggleCode(id) {
    const el = document.getElementById('code-' + id);
    el.classList.toggle('hidden');
}

function copyToClip(btn) {
    const pre = btn.parentElement.nextElementSibling;
    navigator.clipboard.writeText(pre.innerText);
    const icon = btn.querySelector('i');
    icon.className = 'fas fa-check text-green-400';
    setTimeout(() => icon.className = 'far fa-copy', 1500);
}
</script>