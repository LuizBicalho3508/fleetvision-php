<?php
//usuarios.php
$tenant_id = $_SESSION['tenant_id'];
$isSuperAdmin = ($_SESSION['user_role'] === 'superadmin');

// URL da API
$baseUrl = str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
if($baseUrl == '/') $baseUrl = ''; 
$apiUrl = $baseUrl . '/api_dados.php';

try {
    // Listas auxiliares (Filiais e Clientes)
    $branches = $pdo->query("SELECT id, name FROM saas_branches WHERE tenant_id = $tenant_id ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $customers = $pdo->query("SELECT id, name FROM saas_customers WHERE tenant_id = $tenant_id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Lista de Empresas (Apenas para SuperAdmin)
    $tenantsList = [];
    if ($isSuperAdmin) {
        $tenantsList = $pdo->query("SELECT id, name FROM saas_tenants ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) { $branches = []; $customers = []; $tenantsList = []; }
?>

<div class="max-w-7xl mx-auto p-8">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Usuários</h1>
            <p class="text-slate-500 mt-1">Gerencie a equipe <?php echo $isSuperAdmin ? '(Modo Global)' : ''; ?>.</p>
        </div>
        <button onclick="openModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg transition flex items-center gap-2">
            <i class="fas fa-user-plus"></i> Novo Usuário
        </button>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase text-slate-500 font-bold">
                <tr>
                    <th class="p-5">Nome</th>
                    <th class="p-5">Email</th>
                    <?php if($isSuperAdmin): ?><th class="p-5">Empresa</th><?php endif; ?>
                    <th class="p-5">Perfil</th>
                    <th class="p-5">Vínculo</th>
                    <th class="p-5 text-center">Status</th>
                    <th class="p-5 text-right">Ações</th>
                </tr>
            </thead>
            <tbody id="users-list" class="divide-y divide-slate-100 text-sm text-slate-600">
                <tr><td colspan="<?php echo $isSuperAdmin?7:6; ?>" class="p-10 text-center text-slate-400"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div id="modal-user" class="fixed inset-0 bg-black/80 hidden z-[60] flex items-center justify-center backdrop-blur-sm transition-opacity opacity-0">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl flex flex-col transform scale-95 transition-transform duration-300" id="modal-content">
        <div class="px-8 py-5 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-2xl">
            <h3 class="text-xl font-bold text-slate-800" id="modal-title">Novo Usuário</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-red-500 text-2xl">&times;</button>
        </div>
        <div class="p-8 space-y-4 max-h-[80vh] overflow-y-auto">
            <input type="hidden" id="user_id">
            
            <?php if ($isSuperAdmin): ?>
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200 mb-4">
                <label class="block text-xs font-bold text-yellow-700 mb-1 uppercase">Empresa (Tenant)</label>
                <select id="user_tenant_id" onchange="loadRoles(this.value)" class="w-full px-4 py-2 rounded-lg border border-yellow-300 focus:border-yellow-500 outline-none bg-white font-bold text-slate-700">
                    <?php foreach($tenantsList as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $tenant_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" id="user_tenant_id" value="<?php echo $tenant_id; ?>">
            <?php endif; ?>

            <div><label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Nome</label><input type="text" id="user_name" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-indigo-500 outline-none" required></div>
            <div><label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Email</label><input type="email" id="user_email" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-indigo-500 outline-none" required></div>
            
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Senha</label><input type="password" id="user_pass" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-indigo-500 outline-none" placeholder="••••••"></div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Perfil de Acesso</label>
                    <select id="user_role" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 focus:border-indigo-500 outline-none bg-white">
                        <option value="">Carregando...</option>
                    </select>
                </div>
            </div>

            <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-100">
                <label class="block text-xs font-bold text-indigo-800 mb-1 uppercase"><i class="fas fa-link mr-1"></i> Vincular Cliente (Opcional)</label>
                <select id="user_customer" class="w-full px-4 py-2.5 rounded-lg border border-indigo-200 focus:border-indigo-500 outline-none bg-white">
                    <option value="">-- Acesso Geral / Interno --</option>
                    <?php foreach($customers as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1 uppercase">Filial</label>
                    <select id="user_branch" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 bg-white">
                        <option value="">Todas (Matriz)</option>
                        <?php foreach($branches as $b) echo "<option value='{$b['id']}'>{$b['name']}</option>"; ?>
                    </select>
                </div>
                <label class="flex items-center gap-2 cursor-pointer mt-5">
                    <input type="checkbox" id="user_active" class="w-5 h-5 text-indigo-600 rounded" checked>
                    <span class="text-sm font-bold text-slate-700">Usuário Ativo</span>
                </label>
            </div>
        </div>
        <div class="px-8 py-5 border-t border-slate-200 bg-slate-50 rounded-b-2xl flex justify-between items-center">
            <button type="button" onclick="deleteUser()" id="btn-delete" class="text-red-500 hover:text-red-700 text-sm font-bold hidden"><i class="fas fa-trash mr-1"></i> Excluir</button>
            <div class="flex gap-3 ml-auto">
                <button onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-bold hover:bg-white transition">Cancelar</button>
                <button onclick="saveUser()" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold hover:bg-indigo-700 shadow-lg transition">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const API_URL = '<?php echo $apiUrl; ?>';
    const CURRENT_TENANT_ID = '<?php echo $tenant_id; ?>';

    document.addEventListener('DOMContentLoaded', loadUsers);

    // FUNÇÃO PARA CARREGAR PERFIS (ROLES)
    async function loadRoles(tenantId, selectedId = null) {
        const select = document.getElementById('user_role');
        select.innerHTML = '<option value="">Carregando...</option>';
        select.disabled = true;

        try {
            const res = await fetch(`${API_URL}?action=get_roles&tenant_id=${tenantId}`);
            if(!res.ok) throw new Error('Erro ao buscar perfis');
            
            const roles = await res.json();
            
            let html = '<option value="">Sem Perfil (Acesso Limitado)</option>';
            if(roles.length > 0) {
                roles.forEach(r => {
                    const sel = (selectedId && r.id == selectedId) ? 'selected' : '';
                    html += `<option value="${r.id}" ${sel}>${r.name}</option>`;
                });
            }
            select.innerHTML = html;
        } catch(e) {
            console.error(e);
            select.innerHTML = '<option value="">Erro ao carregar</option>';
        } finally {
            select.disabled = false;
        }
    }

    async function loadUsers() {
        const list = document.getElementById('users-list');
        try {
            const res = await fetch(API_URL + '?action=get_users');
            if(!res.ok) throw new Error("Erro HTTP " + res.status);
            const data = await res.json();
            const colSpan = <?php echo $isSuperAdmin?6:5; ?>;

            if(data.length === 0) { list.innerHTML = `<tr><td colspan="${colSpan}" class="p-10 text-center text-slate-400">Nenhum usuário cadastrado.</td></tr>`; return; }

            list.innerHTML = data.map(u => {
                const roleBadge = u.role_name 
                    ? `<span class="bg-indigo-100 text-indigo-700 px-2 py-1 rounded text-xs font-bold border border-indigo-200"><i class="fas fa-shield-alt mr-1"></i> ${u.role_name}</span>`
                    : `<span class="bg-slate-100 text-slate-500 px-2 py-1 rounded text-xs font-bold border border-slate-200">Sem Perfil</span>`;
                
                const tenantBadge = <?php echo $isSuperAdmin ? 'true' : 'false'; ?> ? `<td class="p-5 font-bold text-yellow-600 text-xs uppercase">${u.tenant_name || 'N/A'}</td>` : '';

                const statusBadge = (u.active == 1 || u.active === true || u.active === 't') 
                    ? `<span class="text-green-600 text-xs font-bold"><i class="fas fa-check-circle"></i> Ativo</span>`
                    : `<span class="text-red-500 text-xs font-bold"><i class="fas fa-ban"></i> Inativo</span>`;

                const safeUser = JSON.stringify(u).replace(/"/g, '&quot;');

                return `
                    <tr class="hover:bg-slate-50 transition border-b border-slate-50">
                        <td class="p-5 font-bold text-slate-700 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 font-bold text-xs">${u.name.substring(0,2).toUpperCase()}</div>
                            <div>${u.name}</div>
                        </td>
                        <td class="p-5 text-slate-600">${u.email}</td>
                        ${tenantBadge}
                        <td class="p-5">${roleBadge}</td>
                        <td class="p-5 text-center">${statusBadge}</td>
                        <td class="p-5 text-right">
                            <button onclick="editUser(${safeUser})" class="text-slate-400 hover:text-indigo-600 transition p-2 rounded-lg hover:bg-indigo-50"><i class="fas fa-pencil-alt"></i></button>
                        </td>
                    </tr>`;
            }).join('');
        } catch(e) { 
            console.error(e); 
            const colSpan = <?php echo $isSuperAdmin?6:5; ?>;
            list.innerHTML = `<tr><td colspan="${colSpan}" class="p-10 text-center text-red-500">
                <i class="fas fa-exclamation-triangle text-2xl mb-2"></i><br>
                <b>Erro ao conectar na API.</b><br>
                <small>${e.message}</small>
            </td></tr>`; 
        }
    }

    const modal = document.getElementById('modal-user'), content = document.getElementById('modal-content');

    function openModal() {
        document.getElementById('user_id').value = '';
        document.getElementById('user_name').value = '';
        document.getElementById('user_email').value = '';
        document.getElementById('user_pass').value = '';
        document.getElementById('user_customer').value = '';
        document.getElementById('user_branch').value = '';
        document.getElementById('user_active').checked = true;
        document.getElementById('modal-title').innerText = "Novo Usuário";
        document.getElementById('btn-delete').classList.add('hidden');
        
        // Carrega perfis do tenant atual ao abrir modal de criação
        const currentTenant = document.getElementById('user_tenant_id') ? document.getElementById('user_tenant_id').value : CURRENT_TENANT_ID;
        loadRoles(currentTenant);

        modal.classList.remove('hidden'); 
        setTimeout(() => { modal.classList.remove('opacity-0'); content.classList.remove('scale-95'); content.classList.add('scale-100'); }, 10);
    }

    function closeModal() { content.classList.remove('scale-100'); content.classList.add('scale-95'); modal.classList.add('opacity-0'); setTimeout(() => modal.classList.add('hidden'), 300); }

    function editUser(u) {
        openModal();
        document.getElementById('modal-title').innerText = "Editar Usuário";
        document.getElementById('btn-delete').classList.remove('hidden');
        document.getElementById('user_id').value = u.id;
        document.getElementById('user_name').value = u.name;
        document.getElementById('user_email').value = u.email;
        document.getElementById('user_customer').value = u.customer_id || '';
        document.getElementById('user_branch').value = u.branch_id || '';
        document.getElementById('user_active').checked = (u.active == 1 || u.active === true || u.active === 't');
        document.getElementById('user_pass').placeholder = "Deixe em branco para não alterar";
        
        if(document.getElementById('user_tenant_id')) {
            document.getElementById('user_tenant_id').value = u.tenant_id || CURRENT_TENANT_ID;
        }
        
        // Carrega perfis da empresa do usuário e seleciona o correto
        loadRoles(u.tenant_id || CURRENT_TENANT_ID, u.role_id);
    }

    async function saveUser() {
        const btn = document.querySelector('button[onclick="saveUser()"]');
        const oldTxt = btn.innerText; 
        btn.innerText = "Salvando..."; btn.disabled = true;

        const data = {
            id: document.getElementById('user_id').value,
            name: document.getElementById('user_name').value,
            email: document.getElementById('user_email').value,
            role_id: document.getElementById('user_role').value,
            customer_id: document.getElementById('user_customer').value,
            branch_id: document.getElementById('user_branch').value,
            password: document.getElementById('user_pass').value,
            active: document.getElementById('user_active').checked,
            tenant_id: document.getElementById('user_tenant_id') ? document.getElementById('user_tenant_id').value : null
        };

        try {
            const res = await fetch(API_URL + '?action=save_user', { 
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}, 
                body: JSON.stringify(data) 
            });
            
            const resp = await res.json();
            
            if(res.ok && resp.success) { 
                closeModal(); 
                loadUsers(); 
            } else { 
                alert('Erro: ' + (resp.error || 'Falha desconhecida no servidor.')); 
            }
        } catch(e) { 
            alert('Erro de conexão: ' + e.message); 
        } finally { 
            btn.innerText = oldTxt; btn.disabled = false; 
        }
    }

    async function deleteUser() {
        const id = document.getElementById('user_id').value;
        if(!id || !confirm("Excluir usuário?")) return;
        try {
            const res = await fetch(API_URL + '?action=delete_user', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id }) });
            if((await res.json()).success) { closeModal(); loadUsers(); } else { alert('Erro ao excluir.'); }
        } catch(e) { alert('Erro conexão.'); }
    }
</script>