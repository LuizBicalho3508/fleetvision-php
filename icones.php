<?php if (!isset($_SESSION['user_id'])) exit; ?>
<div class="p-8 max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Ícones de Veículos</h1>
        <button onclick="document.getElementById('modal-icon').classList.remove('hidden')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg font-bold shadow hover:bg-indigo-700">
            <i class="fas fa-upload mr-2"></i> Enviar Ícone
        </button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-4" id="icon-grid">
        </div>
</div>

<div id="modal-icon" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6">
        <h3 class="font-bold text-lg mb-4">Novo Ícone</h3>
        <form onsubmit="uploadIcon(event)" class="space-y-4">
            <div><label class="text-xs font-bold text-gray-500">Nome</label><input type="text" name="name" class="w-full border p-2 rounded" required></div>
            <div><label class="text-xs font-bold text-gray-500">Arquivo (PNG/SVG)</label><input type="file" name="icon" class="w-full border p-2 rounded" accept="image/*" required></div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="document.getElementById('modal-icon').classList.add('hidden')" class="px-4 py-2 text-gray-600">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded font-bold">Enviar</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', loadIcons);
    async function loadIcons() {
        const res = await fetch('api_icones.php', { method: 'POST', body: new URLSearchParams({action:'list'}) });
        const json = await res.json();
        document.getElementById('icon-grid').innerHTML = json.data.map(i => `
            <div class="bg-white p-4 rounded-xl border border-gray-100 flex flex-col items-center gap-2 group relative hover:shadow-md transition">
                <img src="${i.url}" class="w-12 h-12 object-contain">
                <span class="text-xs font-bold text-gray-600">${i.name}</span>
                ${i.tenant_id ? `<button onclick="delIcon(${i.id})" class="absolute top-2 right-2 text-red-400 hover:text-red-600 hidden group-hover:block"><i class="fas fa-trash"></i></button>` : ''}
            </div>
        `).join('');
    }
    async function uploadIcon(e) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('action', 'upload');
        const res = await fetch('api_icones.php', { method: 'POST', body: fd });
        const json = await res.json();
        if(json.success) { document.getElementById('modal-icon').classList.add('hidden'); loadIcons(); e.target.reset(); }
        else alert(json.error);
    }
    async function delIcon(id) {
        if(confirm('Excluir ícone?')) {
            await fetch('api_icones.php?action=delete', { method:'POST', body:JSON.stringify({id}) });
            loadIcons();
        }
    }
</script>